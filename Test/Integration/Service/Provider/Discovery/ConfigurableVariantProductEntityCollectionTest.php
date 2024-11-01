<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Test\Integration\Service\Provider\Discovery;

use Klevu\IndexingApi\Service\Provider\Discovery\ProductEntityCollectionInterface;
use Klevu\IndexingProducts\Service\Provider\Discovery\ConfigurableVariantProductEntityCollection;
use Klevu\TestFixtures\Catalog\Attribute\AttributeFixturePool;
use Klevu\TestFixtures\Catalog\AttributeTrait;
use Klevu\TestFixtures\Catalog\ProductTrait;
use Klevu\TestFixtures\Store\StoreFixturesPool;
use Klevu\TestFixtures\Store\StoreTrait;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use TddWizard\Fixtures\Catalog\ProductFixturePool;

/**
 * @covers \Klevu\IndexingProducts\Service\Provider\Discovery\ConfigurableVariantProductEntityCollection::class
 * @method ProductEntityCollectionInterface instantiateTestObject(?array $arguments = null)
 * @method ProductEntityCollectionInterface instantiateTestObjectFromInterface(?array $arguments = null)
 */
class ConfigurableVariantProductEntityCollectionTest extends TestCase
{
    use AttributeTrait;
    use ObjectInstantiationTrait;
    use ProductTrait;
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

        $this->implementationFqcn = ConfigurableVariantProductEntityCollection::class;
        $this->interfaceFqcn = ProductEntityCollectionInterface::class;
        $this->objectManager = Bootstrap::getObjectManager();
        $this->storeFixturesPool = $this->objectManager->get(StoreFixturesPool::class);
        $this->attributeFixturePool = $this->objectManager->get(AttributeFixturePool::class);
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
        $this->attributeFixturePool->rollback();
        $this->storeFixturesPool->rollback();
    }

    /**
     * @magentoAppIsolation enabled
     * @magentoDbIsolation disabled
     */
    public function testGet_ReturnsCollection_FilteredByType(): void
    {
        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');
        $store = $storeFixture->get();

        $this->createAttribute([
            'key' => 'klevu_test_attribute',
            'attribute_type' => 'configurable',
            'options' => [
                '1' => 'Option 1',
                '2' => 'Option 2',
                '3' => 'Option 3',
            ],
        ]);
        $configurableAttribute = $this->attributeFixturePool->get('klevu_test_attribute');

        $this->createProduct([
            'key' => 'test_simple_product_1',
            'sku' => 'test_simple_product_1',
            'status' => Status::STATUS_ENABLED,
            'website_ids' => [
                $store->getWebsiteId(),
            ],
            'data' => [
                $configurableAttribute->getAttributeCode() => '1',
            ],
            'stores' => [
                $store->getId() => [
                    'name' => 'Simple Product 1 Store 1',
                    'status' => Status::STATUS_ENABLED,
                ],
            ],
        ]);
        $productSimple1 = $this->productFixturePool->get('test_simple_product_1');
        $this->createProduct([
            'key' => 'test_simple_product_2',
            'sku' => 'test_simple_product_2',
            'status' => Status::STATUS_ENABLED,
            'website_ids' => [
                $store->getWebsiteId(),
            ],
            'data' => [
                $configurableAttribute->getAttributeCode() => '2',
            ],
            'stores' => [
                $store->getId() => [
                    'name' => 'Simple Product 2 Store 1',
                    'status' => Status::STATUS_DISABLED,
                ],
            ],
        ]);
        $productSimple2 = $this->productFixturePool->get('test_simple_product_2');

        $this->createProduct([
            'key' => 'test_configurable_product',
            'sku' => 'test_configurable_product',
            'status' => Status::STATUS_ENABLED,
            'website_ids' => [
                $store->getWebsiteId(),
            ],
            'type_id' => Configurable::TYPE_CODE,
            'configurable_attributes' => [
                $configurableAttribute->getAttribute(),
            ],
            'variants' => [
                $productSimple1->getProduct(),
                $productSimple2->getProduct(),
            ],
            'stores' => [
                $store->getId() => [
                    'name' => 'Configurable Product 1 Store 1',
                    'status' => Status::STATUS_ENABLED,
                ],
            ],
        ]);
        $productConfigurable = $this->productFixturePool->get('test_configurable_product');

        $provider = $this->instantiateTestObject();
        $collection = $provider->get($store);
        /** @var ProductInterface[] $items */
        $items = $collection->getItems();

        $itemIdSimple1 = sprintf(
            '%d-%d',
            $productSimple1->getId(),
            $productConfigurable->getId(),
        );
        $foundItemsForSimple1 = array_filter(
            array: $items,
            callback: static fn (ProductInterface $collectionItem): bool => (
                (string)$collectionItem->getId() === $itemIdSimple1
            ),
        );
        $this->assertCount(
            expectedCount: 1,
            haystack: $foundItemsForSimple1,
            message: sprintf(
                'Collection contains itemId %s',
                $itemIdSimple1,
            ),
        );

        /** @var ProductInterface $item1 */
        $item1 = current($foundItemsForSimple1);
        $this->assertSame(expected: Status::STATUS_ENABLED, actual: (int)$item1->getStatus());

        $itemIdSimple2 = sprintf(
            '%d-%d',
            $productSimple2->getId(),
            $productConfigurable->getId(),
        );
        $foundItemsForSimple2 = array_filter(
            array: $items,
            callback: static fn (ProductInterface $collectionItem): bool => (
                (string)$collectionItem->getId() === $itemIdSimple2
            ),
        );
        $this->assertCount(
            expectedCount: 1,
            haystack: $foundItemsForSimple2,
            message: sprintf(
                'Collection contains itemId %s',
                $itemIdSimple2,
            ),
        );

        /** @var ProductInterface $item2 */
        $item2 = current($foundItemsForSimple2);
        $this->assertSame(expected: Status::STATUS_DISABLED, actual: (int)$item2->getStatus());

        $foundItemsForConfigurable = array_filter(
            array: $items,
            callback: static fn (ProductInterface $collectionItem): bool => (
                $collectionItem->getId() === $productConfigurable->getId()
            ),
        );
        $this->assertCount(
            expectedCount: 0,
            haystack: $foundItemsForConfigurable,
            message: sprintf(
                'Collection does not contain itemId %s',
                $productConfigurable->getId(),
            ),
        );
    }

    /**
     * @magentoAppIsolation enabled
     * @magentoDbIsolation disabled
     * @group wip
     */
    public function testGet_ReturnsCollection_FilteredByType_AndStore_AndEntityId(): void
    {
        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');
        $store = $storeFixture->get();

        $this->createAttribute([
            'key' => 'klevu_test_attribute',
            'attribute_type' => 'configurable',
            'options' => [
                '1' => 'Option 1',
                '2' => 'Option 2',
                '3' => 'Option 3',
            ],
        ]);
        $configurableAttribute = $this->attributeFixturePool->get('klevu_test_attribute');

        $this->createProduct([
            'key' => 'test_simple_product_1',
            'sku' => 'test_simple_product_1',
            'status' => Status::STATUS_ENABLED,
            'website_ids' => [
                $store->getWebsiteId(),
            ],
            'data' => [
                $configurableAttribute->getAttributeCode() => '1',
            ],
            'stores' => [
                $store->getId() => [
                    'name' => 'Simple Product 1 Store 1',
                    'status' => Status::STATUS_ENABLED,
                ],
            ],
        ]);
        $productSimple1 = $this->productFixturePool->get('test_simple_product_1');
        $this->createProduct([
            'key' => 'test_simple_product_2',
            'sku' => 'test_simple_product_2',
            'status' => Status::STATUS_ENABLED,
            'website_ids' => [
                $store->getWebsiteId(),
            ],
            'data' => [
                $configurableAttribute->getAttributeCode() => '2',
            ],
            'stores' => [
                $store->getId() => [
                    'name' => 'Simple Product 2 Store 1',
                    'status' => Status::STATUS_DISABLED,
                ],
            ],
        ]);
        $productSimple2 = $this->productFixturePool->get('test_simple_product_2');

        $this->createProduct([
            'key' => 'test_configurable_product',
            'sku' => 'test_configurable_product',
            'status' => Status::STATUS_ENABLED,
            'website_ids' => [
                $store->getWebsiteId(),
            ],
            'type_id' => Configurable::TYPE_CODE,
            'configurable_attributes' => [
                $configurableAttribute->getAttribute(),
            ],
            'variants' => [
                $productSimple1->getProduct(),
                $productSimple2->getProduct(),
            ],
            'stores' => [
                $store->getId() => [
                    'name' => 'Configurable Product 1 Store 1',
                    'status' => Status::STATUS_ENABLED,
                ],
            ],
        ]);
        $productConfigurable = $this->productFixturePool->get('test_configurable_product');

        $provider = $this->instantiateTestObject();
        $collection = $provider->get($store, [(int)$productSimple2->getId()]);
        /** @var ProductInterface[] $items */
        $items = $collection->getItems();

        $itemIdSimple1 = sprintf(
            '%d-%d',
            $productSimple1->getId(),
            $productConfigurable->getId(),
        );
        $foundItemsForSimple1 = array_filter(
            array: $items,
            callback: static fn (ProductInterface $collectionItem): bool => (
                (string)$collectionItem->getId() === $itemIdSimple1
            ),
        );
        $this->assertCount(
            expectedCount: 0,
            haystack: $foundItemsForSimple1,
            message: sprintf(
                'Collection does not contain itemId %s',
                $itemIdSimple1,
            ),
        );

        $itemIdSimple2 = sprintf(
            '%d-%d',
            $productSimple2->getId(),
            $productConfigurable->getId(),
        );
        $foundItemsForSimple2 = array_filter(
            array: $items,
            callback: static fn (ProductInterface $collectionItem): bool => (
                (string)$collectionItem->getId() === $itemIdSimple2
            ),
        );
        $this->assertCount(
            expectedCount: 1,
            haystack: $foundItemsForSimple2,
            message: sprintf(
                'Collection contains itemId %s',
                $itemIdSimple2,
            ),
        );

        /** @var ProductInterface $item2 */
        $item2 = current($foundItemsForSimple2);
        $this->assertSame(expected: Status::STATUS_DISABLED, actual: (int)$item2->getStatus());

        $foundItemsForConfigurable = array_filter(
            array: $items,
            callback: static fn (ProductInterface $collectionItem): bool => (
                $collectionItem->getId() === $productConfigurable->getId()
            ),
        );
        $this->assertCount(
            expectedCount: 0,
            haystack: $foundItemsForConfigurable,
            message: sprintf(
                'Collection does not contain itemId %s',
                $productConfigurable->getId(),
            ),
        );
    }

    /**
     * Ref: KS-22991
     * @magentoAppIsolation enabled
     * @magentoDbIsolation disabled
     */
    public function testGet_ReturnsCollection_WhereVariantsWithMultipleParentsExist(): void
    {
        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');
        $store = $storeFixture->get();

        $this->createAttribute([
            'key' => 'klevu_test_attribute',
            'attribute_type' => 'configurable',
            'options' => [
                '1' => 'Option 1',
                '2' => 'Option 2',
                '3' => 'Option 3',
            ],
        ]);
        $configurableAttribute = $this->attributeFixturePool->get('klevu_test_attribute');

        $this->createProduct([
            'key' => 'test_simple_product',
            'sku' => 'test_simple_product',
            'status' => Status::STATUS_ENABLED,
            'website_ids' => [
                $store->getWebsiteId(),
            ],
            'data' => [
                $configurableAttribute->getAttributeCode() => '1',
            ],
            'stores' => [
                $store->getId() => [
                    'name' => 'Simple Product 1 Store 1',
                    'status' => Status::STATUS_ENABLED,
                ],
            ],
        ]);
        $productSimple = $this->productFixturePool->get('test_simple_product');

        $this->createProduct([
            'key' => 'test_configurable_product_1',
            'sku' => 'test_configurable_product_1',
            'status' => Status::STATUS_ENABLED,
            'website_ids' => [
                $store->getWebsiteId(),
            ],
            'type_id' => Configurable::TYPE_CODE,
            'configurable_attributes' => [
                $configurableAttribute->getAttribute(),
            ],
            'variants' => [
                $productSimple->getProduct(),
            ],
            'stores' => [
                $store->getId() => [
                    'name' => 'Configurable Product 1 Store 1',
                    'status' => Status::STATUS_ENABLED,
                ],
            ],
        ]);
        $productConfigurable1 = $this->productFixturePool->get('test_configurable_product_1');

        $this->createProduct([
            'key' => 'test_configurable_product_2',
            'sku' => 'test_configurable_product_2',
            'status' => Status::STATUS_ENABLED,
            'website_ids' => [
                $store->getWebsiteId(),
            ],
            'type_id' => Configurable::TYPE_CODE,
            'configurable_attributes' => [
                $configurableAttribute->getAttribute(),
            ],
            'variants' => [
                $productSimple->getProduct(),
            ],
            'stores' => [
                $store->getId() => [
                    'name' => 'Configurable Product 2 Store 1',
                    'status' => Status::STATUS_ENABLED,
                ],
            ],
        ]);
        $productConfigurable2 = $this->productFixturePool->get('test_configurable_product_2');

        $provider = $this->instantiateTestObject();
        $collection = $provider->get($store);
        /** @var ProductInterface[] $items */
        $items = $collection->getItems();

        $itemIdSimpleConfig1 = sprintf(
            '%d-%d',
            $productSimple->getId(),
            $productConfigurable1->getId(),
        );
        $foundItemsForSimpleConfig1 = array_filter(
            array: $items,
            callback: static fn (ProductInterface $collectionItem): bool => (
                (string)$collectionItem->getId() === $itemIdSimpleConfig1
            ),
        );
        $this->assertCount(
            expectedCount: 1,
            haystack: $foundItemsForSimpleConfig1,
            message: sprintf(
                'Collection contains itemId %s',
                $itemIdSimpleConfig1,
            ),
        );

        $foundItemsForConfigurable1 = array_filter(
            array: $items,
            callback: static fn (ProductInterface $collectionItem): bool => (
                $collectionItem->getId() === $productConfigurable1->getId()
            ),
        );
        $this->assertCount(
            expectedCount: 0,
            haystack: $foundItemsForConfigurable1,
            message: sprintf(
                'Collection does not contain itemId %s',
                $productConfigurable1->getId(),
            ),
        );

        $itemIdSimpleConfig2 = sprintf(
            '%d-%d',
            $productSimple->getId(),
            $productConfigurable2->getId(),
        );
        $foundItemsForSimpleConfig2 = array_filter(
            array: $items,
            callback: static fn (ProductInterface $collectionItem): bool => (
                (string)$collectionItem->getId() === $itemIdSimpleConfig2
            ),
        );
        $this->assertCount(
            expectedCount: 1,
            haystack: $foundItemsForSimpleConfig2,
            message: sprintf(
                'Collection contains itemId %s',
                $itemIdSimpleConfig2,
            ),
        );

        $foundItemsForConfigurable2 = array_filter(
            array: $items,
            callback: static fn (ProductInterface $collectionItem): bool => (
                $collectionItem->getId() === $productConfigurable2->getId()
            ),
        );
        $this->assertCount(
            expectedCount: 0,
            haystack: $foundItemsForConfigurable2,
            message: sprintf(
                'Collection does not contain itemId %s',
                $productConfigurable2->getId(),
            ),
        );
    }
}
