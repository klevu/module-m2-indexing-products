<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Test\Integration\Service\Provider\Discovery;

use Klevu\IndexingApi\Service\Provider\Discovery\ProductEntityCollectionInterface;
use Klevu\IndexingProducts\Service\Provider\Discovery\ProductEntityCollection;
use Klevu\TestFixtures\Catalog\ProductTrait;
use Klevu\TestFixtures\Store\StoreFixturesPool;
use Klevu\TestFixtures\Store\StoreTrait;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Type;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use TddWizard\Fixtures\Catalog\ProductFixturePool;

/**
 * @covers \Klevu\IndexingProducts\Service\Provider\Discovery\ProductEntityCollection::class
 * @method ProductEntityCollectionInterface instantiateTestObject(?array $arguments = null)
 * @method ProductEntityCollectionInterface instantiateTestObjectFromInterface(?array $arguments = null)
 */
class ProductEntityCollectionTest extends TestCase
{
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

        $this->implementationFqcn = ProductEntityCollection::class;
        $this->interfaceFqcn = ProductEntityCollectionInterface::class;
        $this->objectManager = Bootstrap::getObjectManager();
        $this->storeFixturesPool = $this->objectManager->get(StoreFixturesPool::class);
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
        $this->storeFixturesPool->rollback();
    }

    /**
     * @magentoDbIsolation disabled
     */
    public function testGet_ReturnsCollection_FilteredByType_AndStore(): void
    {
        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');
        $store = $storeFixture->get();

        $this->createProduct([
            'key' => 'test_simple_product_1',
            'sku' => 'test_simple_product_1',
            'status' => Status::STATUS_ENABLED,
            'stores' => [
                $store->getId() => [
                    'name' => 'Simple Product 1',
                    'status' => Status::STATUS_ENABLED,
                ],
            ],
        ]);
        $productSimple1 = $this->productFixturePool->get('test_simple_product_1');
        $this->createProduct([
            'key' => 'test_simple_product_2',
            'sku' => 'test_simple_product_2',
            'status' => Status::STATUS_ENABLED,
            'stores' => [
                $store->getId() => [
                    'name' => 'Simple Product 2',
                    'status' => Status::STATUS_DISABLED,
                ],
            ],
        ]);
        $productSimple2 = $this->productFixturePool->get('test_simple_product_2');
        $this->createProduct([
            'key' => 'test_virtual_product_1',
            'sku' => 'test_virtual_product_1',
            'type_id' => Type::TYPE_VIRTUAL,
            'status' => Status::STATUS_DISABLED,
            'stores' => [
                $store->getId() => [
                    'name' => 'Virtual Product 1',
                    'status' => Status::STATUS_ENABLED,
                ],
            ],
        ]);
        $productVirtual1 = $this->productFixturePool->get('test_virtual_product_1');

        $provider = $this->instantiateTestObject([
            'productType' => Type::TYPE_SIMPLE,
        ]);
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

        $this->assertArrayNotHasKey(key: $productVirtual1->getId(), array: $items);
    }

    /**
     * @magentoDbIsolation disabled
     */
    public function testGet_ReturnsCollection_FilteredByType_AndStore_AndEntityId(): void
    {
        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');
        $store = $storeFixture->get();

        $this->createProduct([
            'key' => 'test_simple_product_1',
            'sku' => 'test_simple_product_1',
            'status' => Status::STATUS_ENABLED,
            'stores' => [
                $store->getId() => [
                    'name' => 'Simple Product 1',
                    'status' => Status::STATUS_ENABLED,
                ],
            ],
        ]);
        $productSimple1 = $this->productFixturePool->get('test_simple_product_1');
        $this->createProduct([
            'key' => 'test_simple_product_2',
            'sku' => 'test_simple_product_2',
            'status' => Status::STATUS_ENABLED,
            'stores' => [
                $store->getId() => [
                    'name' => 'Simple Product 2',
                    'status' => Status::STATUS_DISABLED,
                ],
            ],
        ]);
        $productSimple2 = $this->productFixturePool->get('test_simple_product_2');
        $this->createProduct([
            'key' => 'test_virtual_product_1',
            'sku' => 'test_virtual_product_1',
            'type_id' => Type::TYPE_VIRTUAL,
            'status' => Status::STATUS_DISABLED,
            'stores' => [
                $store->getId() => [
                    'name' => 'Virtual Product 1',
                    'status' => Status::STATUS_ENABLED,
                ],
            ],
        ]);
        $productVirtual1 = $this->productFixturePool->get('test_virtual_product_1');

        $provider = $this->instantiateTestObject([
            'productType' => Type::TYPE_SIMPLE,
        ]);
        $collection = $provider->get($store, [(int)$productSimple2->getId()]);
        $items = $collection->getItems();

        $this->assertArrayNotHasKey(key: $productSimple1->getId(), array: $items);

        $this->assertArrayHasKey(key: $productSimple2->getId(), array: $items);
        /** @var ProductInterface $item2 */
        $item2 = $items[$productSimple2->getId()];
        $this->assertSame(expected: Status::STATUS_DISABLED, actual: (int)$item2->getStatus());

        $this->assertArrayNotHasKey(key: $productVirtual1->getId(), array: $items);
    }
}
