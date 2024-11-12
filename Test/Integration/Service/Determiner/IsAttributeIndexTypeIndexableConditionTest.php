<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Test\Integration\Service\Determiner;

use Klevu\IndexingApi\Model\Source\IndexType;
use Klevu\IndexingApi\Service\Determiner\IsAttributeIndexableConditionInterface;
use Klevu\IndexingProducts\Service\Determiner\IsAttributeIndexTypeIndexableCondition;
use Klevu\TestFixtures\Catalog\Attribute\AttributeFixturePool;
use Klevu\TestFixtures\Catalog\AttributeTrait;
use Klevu\TestFixtures\Store\StoreFixturesPool;
use Klevu\TestFixtures\Store\StoreTrait;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Magento\Catalog\Api\Data\CategoryAttributeInterface;
use Magento\Catalog\Api\Data\ProductAttributeInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Klevu\IndexingProducts\Service\Determiner\IsAttributeIndexTypeIndexableCondition::class
 * @method IsAttributeIndexableConditionInterface instantiateTestObject(?array $arguments = null)
 * @method IsAttributeIndexableConditionInterface instantiateTestObjectFromInterface(?array $arguments = null)
 */
class IsAttributeIndexTypeIndexableConditionTest extends TestCase
{
    use AttributeTrait;
    use ObjectInstantiationTrait;
    use StoreTrait;
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

        $this->implementationFqcn = IsAttributeIndexTypeIndexableCondition::class;
        $this->interfaceFqcn = IsAttributeIndexableConditionInterface::class;
        $this->objectManager = Bootstrap::getObjectManager();
        $this->storeFixturesPool = $this->objectManager->get(StoreFixturesPool::class);
        $this->attributeFixturePool = $this->objectManager->get(AttributeFixturePool::class);
    }

    /**
     * @return void
     * @throws \Exception
     */
    protected function tearDown(): void
    {
        parent::tearDown();

        $this->attributeFixturePool->rollback();
        $this->storeFixturesPool->rollback();
    }

    public function testExecute_ThrowsInvalidArgumentException(): void
    {
        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');

        $invalidEntity = $this->objectManager->create(CategoryAttributeInterface::class);
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            sprintf(
                'Invalid argument provided for "$attribute". Expected %s, received %s.',
                ProductAttributeInterface::class,
                get_debug_type($invalidEntity),
            ),
        );

        $service = $this->instantiateTestObject();
        $service->execute(
            attribute: $invalidEntity,
            store: $storeFixture->get(),
        );
    }

    /**
     * @magentoAppIsolation enabled
     * @dataProvider dataProvider_testExecute_ReturnsBoolean_BasedOnIndexType
     */
    public function testExecute_ReturnsBoolean_BasedOnIndexType(IndexType $indexAs, bool $expected): void
    {
        $this->createStore([
            'key' => 'test_store_1',
        ]);
        $storeFixture = $this->storeFixturesPool->get('test_store_1');

        $this->createAttribute([
            'attribute_type' => 'text',
            'index_as' => $indexAs,
        ]);
        $attribute = $this->attributeFixturePool->get('test_attribute');

        $determiner = $this->instantiateTestObject();
        $isIndexable = $determiner->execute(
            attribute: $attribute->getAttribute(),
            store: $storeFixture->get(),
        );

        $this->assertSame(expected: $expected, actual: $isIndexable);
    }

    /**
     * @return mixed[][]
     */
    public function dataProvider_testExecute_ReturnsBoolean_BasedOnIndexType(): array
    {
        return [
            [IndexType::NO_INDEX, false],
            [IndexType::INDEX, true],
        ];
    }
}
