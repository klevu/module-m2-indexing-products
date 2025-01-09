<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Test\Integration\Pipeline\Transformer;

use Klevu\IndexingProducts\Pipeline\Transformer\ToFinalPrice;
use Klevu\Pipelines\Exception\Transformation\InvalidInputDataException;
use Klevu\Pipelines\Exception\TransformationException;
use Klevu\Pipelines\Model\Argument;
use Klevu\Pipelines\Model\ArgumentIterator;
use Klevu\Pipelines\Transformer\TransformerInterface;
use Klevu\TestFixtures\Catalog\ProductTrait;
use Klevu\TestFixtures\Customer\CustomerGroupTrait;
use Klevu\TestFixtures\Customer\Group\CustomerGroupFixturePool;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Magento\Customer\Model\Group as CustomerGroup;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\DataObject;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use TddWizard\Fixtures\Catalog\ProductFixturePool;

/**
 * @covers ToFinalPrice::class
 * @method TransformerInterface instantiateTestObject(?array $arguments = null)
 * @method TransformerInterface instantiateTestObjectFromInterface(?array $arguments = null)
 */
class ToFinalPriceTransformerTest extends TestCase
{
    use CustomerGroupTrait;
    use ObjectInstantiationTrait;
    use ProductTrait;
    use TestImplementsInterfaceTrait;

    /**
     * @var ObjectManagerInterface|null
     */
    private ?ObjectManagerInterface $objectManager = null; // @phpstan-ignore-line

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->implementationFqcn = ToFinalPrice::class;
        $this->interfaceFqcn = TransformerInterface::class;
        $this->objectManager = Bootstrap::getObjectManager();

        $this->productFixturePool = $this->objectManager->get(ProductFixturePool::class);
        $this->customerGroupFixturePool = $this->objectManager->get(CustomerGroupFixturePool::class);
    }

    /**
     * @return void
     * @throws \Exception
     */
    protected function tearDown(): void
    {
        parent::tearDown();

        $this->productFixturePool->rollback();
        $this->customerGroupFixturePool->rollback();
    }

    /**
     * @dataProvider testTransform_ThrowsException_WhenInvalidDataType_dataProvider
     */
    public function testTransform_ThrowsException_WhenInvalidDataType(mixed $invalidData): void
    {
        $this->expectException(InvalidInputDataException::class);
        $transformer = $this->instantiateTestObject();
        $transformer->transform(data: $invalidData);
    }

    /**
     * @return mixed[]
     */
    public function testTransform_ThrowsException_WhenInvalidDataType_dataProvider(): array
    {
        return [
            [null],
            ['string'],
            [1],
            [1.23],
            [true],
            [new DataObject()],
        ];
    }

    /**
     * @magentoDbIsolation disabled
     * @dataProvider testTransform_ThrowsException_WhenCustomerGroupIdIsInvalid_dataProvider
     */
    public function testTransform_ThrowsException_WhenCustomerGroupIdIsInvalid(mixed $invalidCustomerGroupId): void
    {
        $this->expectException(TransformationException::class);
        $this->expectExceptionMessage('Invalid argument for transformation');

        $this->createProduct();
        $productFixture = $this->productFixturePool->get('test_product');

        $argument = $this->objectManager->create(
            type: Argument::class,
            arguments: [
                'value' => $invalidCustomerGroupId,
                'key' => 0,
            ],
        );
        $argumentIterator = $this->objectManager->create(
            type: ArgumentIterator::class,
            arguments: [
                'data' => [$argument],
            ],
        );

        $transformer = $this->instantiateTestObject();
        $transformer->transform(
            data: $productFixture->getProduct(),
            arguments: $argumentIterator,
        );
    }

    /**
     * @return mixed[]
     */
    public function testTransform_ThrowsException_WhenCustomerGroupIdIsInvalid_dataProvider(): array
    {
        return [
            [null],
            ['string'],
            ['1.3'],
            [1.23],
            [true],
            [new DataObject()],
        ];
    }

    /**
     * @magentoDbIsolation disabled
     */
    public function testTransform_ReturnsFinalPriceForProduct_WithTierPrices_ForCurrentCustomerGroup(): void
    {
        $this->createCustomerGroup([
            'key' => 'test_customer_group_1',
        ]);
        $customerGroupFixture1 = $this->customerGroupFixturePool->get('test_customer_group_1');
        $this->createCustomerGroup([
            'key' => 'test_customer_group_2',
            'code' => 'Klevu Test Customer Group 2',
        ]);
        $customerGroupFixture2 = $this->customerGroupFixturePool->get('test_customer_group_2');

        $this->createProduct([
            'tier_prices' => [
                [
                    'price' => 12.00,
                    'qty' => 1,
                    'customer_group_id' => CustomerGroup::NOT_LOGGED_IN_ID,
                ],
                [
                    'price' => 11.00,
                    'qty' => 1,
                    'customer_group_id' => CustomerGroup::CUST_GROUP_ALL,
                ],
                [
                    'price' => 10.00,
                    'qty' => 1,
                    'customer_group_id' => $customerGroupFixture1->getId(),
                ],
                [
                    'price' => 9.00,
                    'qty' => 2,
                    'customer_group_id' => $customerGroupFixture1->getId(),
                ],
                [
                    'price' => 8.00,
                    'qty' => 1,
                    'customer_group_id' => $customerGroupFixture2->getId(),
                ],
                [
                    'price' => 7.00,
                    'qty' => 2,
                    'customer_group_id' => $customerGroupFixture2->getId(),
                ],
            ],
        ]);
        $productFixture = $this->productFixturePool->get('test_product');

        $argument = $this->objectManager->create(
            type: Argument::class,
            arguments: [
                'value' => 1,
                'key' => 0,
            ],
        );
        $argumentIterator = $this->objectManager->create(
            type: ArgumentIterator::class,
            arguments: [
                'data' => [$argument],
            ],
        );

        $customerSession = $this->objectManager->get(CustomerSession::class);
        $transformer = $this->instantiateTestObject();

        $customerSession->setCustomerGroupId(id: $customerGroupFixture1->getId());
        $result = $transformer->transform(
            data: $productFixture->getProduct(),
            arguments: $argumentIterator,
        );
        $this->assertSame(
            expected: 10.00,
            actual: $result,
            message: sprintf('Customer Group %s', $customerGroupFixture1->getId()),
        );

        $customerSession->setCustomerGroupId(id: $customerGroupFixture2->getId());
        $result = $transformer->transform(
            data: $productFixture->getProduct(),
            arguments: $argumentIterator,
        );
        $this->assertSame(
            expected: 8.00,
            actual: $result,
            message: sprintf('Customer Group %s', $customerGroupFixture2->getId()),
        );
    }
}
