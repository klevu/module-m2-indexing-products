<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Test\Integration\Pipeline\Transformer;

use Klevu\IndexingProducts\Pipeline\Transformer\SetDataOnProduct;
use Klevu\Pipelines\Exception\Transformation\InvalidInputDataException;
use Klevu\Pipelines\Exception\TransformationException;
use Klevu\Pipelines\Model\Argument;
use Klevu\Pipelines\Model\ArgumentIterator;
use Klevu\Pipelines\Transformer\TransformerInterface;
use Klevu\TestFixtures\Catalog\ProductTrait;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Framework\DataObject;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use TddWizard\Fixtures\Catalog\ProductFixturePool;

/**
 * @covers SetDataOnProduct::class
 * @method TransformerInterface instantiateTestObject(?array $arguments = null)
 * @method TransformerInterface instantiateTestObjectFromInterface(?array $arguments = null)
 */
class SetDataOnProductTransformerTest extends TestCase
{
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

        $this->implementationFqcn = SetDataOnProduct::class;
        $this->interfaceFqcn = TransformerInterface::class;
        $this->objectManager = Bootstrap::getObjectManager();

        $this->productFixturePool = $this->objectManager->get(ProductFixturePool::class);
    }

    /**
     * @return void
     * @throws \Exception
     */
    protected function tearDown(): void
    {
        parent::tearDown();

        $this->productFixturePool->rollback();
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
     * @dataProvider testTransform_ThrowsException_WhenAttributeCodeIsInvalid_dataProvider
     */
    public function testTransform_ThrowsException_WhenAttributeCodeIsInvalid(mixed $invalidAttributeCode): void
    {
        $this->expectException(TransformationException::class);
        $this->expectExceptionMessage('Invalid argument for transformation');

        $this->createProduct();
        $productFixture = $this->productFixturePool->get('test_product');

        $argument0 = $this->objectManager->create(
            type: Argument::class,
            arguments: [
                'value' => $invalidAttributeCode,
                'key' => 0,
            ],
        );
        $argument1 = $this->objectManager->create(
            type: Argument::class,
            arguments: [
                'value' => 'some valid value',
                'key' => 1,
            ],
        );
        $argumentIterator = $this->objectManager->create(
            type: ArgumentIterator::class,
            arguments: [
                'data' => [$argument0, $argument1],
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
    public function testTransform_ThrowsException_WhenAttributeCodeIsInvalid_dataProvider(): array
    {
        return [
            [null],
            [1.23],
            [true],
            [['string']],
            [new DataObject()],
        ];
    }

    /**
     * @magentoDbIsolation disabled
     */
    public function testTransform_ThrowsException_WhenAttributeValueIsInvalid(): void
    {
        $this->expectException(TransformationException::class);
        $this->expectExceptionMessage('Invalid argument for transformation');

        $this->createProduct();
        $productFixture = $this->productFixturePool->get('test_product');

        $argument0 = $this->objectManager->create(
            type: Argument::class,
            arguments: [
                'value' => 'attribute_code',
                'key' => 0,
            ],
        );
        $argument1 = $this->objectManager->create(
            type: Argument::class,
            arguments: [
                'value' => null,
                'key' => 1,
            ],
        );
        $argumentIterator = $this->objectManager->create(
            type: ArgumentIterator::class,
            arguments: [
                'data' => [$argument0, $argument1],
            ],
        );

        $transformer = $this->instantiateTestObject();
        $transformer->transform(
            data: $productFixture->getProduct(),
            arguments: $argumentIterator,
        );
    }

    /**
     * @magentoDbIsolation disabled
     */
    public function testTransform_SetsDataOnProduct(): void
    {
        $this->createProduct();
        $productFixture = $this->productFixturePool->get('test_product');
        /** @var ProductInterface&DataObject $product */
        $product = $productFixture->getProduct();

        $this->assertNull(actual: $product->getData('attribute_code'));

        $argument0 = $this->objectManager->create(
            type: Argument::class,
            arguments: [
                'value' => 'attribute_code',
                'key' => 0,
            ],
        );
        $argument1 = $this->objectManager->create(
            type: Argument::class,
            arguments: [
                'value' => 'this value',
                'key' => 1,
            ],
        );
        $argumentIterator = $this->objectManager->create(
            type: ArgumentIterator::class,
            arguments: [
                'data' => [$argument0, $argument1],
            ],
        );

        $transformer = $this->instantiateTestObject();
        $product = $transformer->transform(
            data: $product,
            arguments: $argumentIterator,
        );

        $this->assertSame(expected: 'this value', actual: $product->getData('attribute_code'));
    }
}
