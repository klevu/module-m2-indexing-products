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
        $items = $collection->getItems();

        $this->assertArrayHasKey(key: $productSimple1->getId(), array: $items);
        /** @var ProductInterface $item1 */
        $item1 = $items[$productSimple1->getId()];
        $this->assertSame(expected: Status::STATUS_ENABLED, actual: (int)$item1->getStatus());

        $this->assertArrayHasKey(key: $productSimple2->getId(), array: $items);
        /** @var ProductInterface $item2 */
        $item2 = $items[$productSimple2->getId()];
        $this->assertSame(expected: Status::STATUS_DISABLED, actual: (int)$item2->getStatus());

        $this->assertArrayNotHasKey(key: $productConfigurable->getId(), array: $items);
    }

    /**
     * @magentoDbIsolation disabled
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
        $items = $collection->getItems();

        $this->assertArrayNotHasKey(key: $productSimple1->getId(), array: $items);

        $this->assertArrayHasKey(key: $productSimple2->getId(), array: $items);
        /** @var ProductInterface $item2 */
        $item2 = $items[$productSimple2->getId()];
        $this->assertSame(expected: Status::STATUS_DISABLED, actual: (int)$item2->getStatus());

        $this->assertArrayNotHasKey(key: $productConfigurable->getId(), array: $items);
    }
}
