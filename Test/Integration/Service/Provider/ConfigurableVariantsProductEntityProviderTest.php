<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Test\Integration\Service\Provider;

use Klevu\Configuration\Service\Provider\ScopeProviderInterface;
use Klevu\IndexingApi\Service\Provider\EntityProviderInterface;
use Klevu\IndexingProducts\Service\Provider\ProductEntityProvider;
use Klevu\IndexingProducts\Service\Provider\ProductEntityProvider\ConfigurableVariants as ConfigurableVariantsEntityProviderVirtualType; //phpcs:ignore Generic.Files.LineLength.TooLong
use Klevu\TestFixtures\Catalog\Attribute\AttributeFixturePool;
use Klevu\TestFixtures\Catalog\AttributeTrait;
use Klevu\TestFixtures\Catalog\ProductTrait;
use Klevu\TestFixtures\Store\StoreFixturesPool;
use Klevu\TestFixtures\Store\StoreTrait;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Klevu\TestFixtures\Website\WebsiteFixturesPool;
use Klevu\TestFixtures\Website\WebsiteTrait;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use TddWizard\Fixtures\Catalog\ProductFixturePool;

/**
 * @covers \Klevu\IndexingProducts\Service\Provider\ProductEntityProvider\ConfigurableVariants::class
 * @method EntityProviderInterface instantiateTestObject(?array $arguments = null)
 * @method EntityProviderInterface instantiateTestObjectFromInterface(?array $arguments = null)
 */
class ConfigurableVariantsProductEntityProviderTest extends TestCase
{
    use AttributeTrait;
    use ObjectInstantiationTrait;
    use ProductTrait;
    use StoreTrait;
    use TestImplementsInterfaceTrait;
    use WebsiteTrait;

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

        $this->implementationFqcn = ConfigurableVariantsEntityProviderVirtualType::class; // @phpstan-ignore-line
        $this->interfaceFqcn = EntityProviderInterface::class;
        $this->implementationForVirtualType = ProductEntityProvider::class;
        $this->objectManager = Bootstrap::getObjectManager();
        $this->websiteFixturesPool = $this->objectManager->get(WebsiteFixturesPool::class);
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
        $this->websiteFixturesPool->rollback();
    }

    /**
     * @magentoDbIsolation disabled
     */
    public function testGet_ReturnsProductData_AtStoreScope(): void
    {
        $this->createWebsite([
            'code' => 'klevu_test_website_1',
            'key' => 'test_website_1',
        ]);
        $websiteFixture1 = $this->websiteFixturesPool->get('test_website_1');
        $this->createWebsite([
            'code' => 'klevu_test_website_2',
            'key' => 'test_website_2',
        ]);
        $websiteFixture2 = $this->websiteFixturesPool->get('test_website_2');

        $this->createStore([
            'code' => 'klevu_test_store_1',
            'key' => 'test_store_1',
        ]);
        $storeFixture1 = $this->storeFixturesPool->get('test_store_1');
        $store1 = $storeFixture1->get();

        $this->createStore([
            'code' => 'klevu_test_store_2',
            'key' => 'test_store_2',
            'website_id' => $websiteFixture1->getId(),
        ]);
        $storeFixture2 = $this->storeFixturesPool->get('test_store_2');
        $store2 = $storeFixture2->get();

        $this->createStore([
            'code' => 'klevu_test_store_3',
            'key' => 'test_store_3',
            'website_id' => $websiteFixture2->getId(),
        ]);
        $storeFixture3 = $this->storeFixturesPool->get('test_store_3');
        $store3 = $storeFixture3->get();

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
                $store1->getWebsiteId(),
                $store2->getWebsiteId(),
                $store3->getWebsiteId(),
            ],
            'data' => [
                $configurableAttribute->getAttributeCode() => '1',
            ],
            'stores' => [
                $store1->getId() => [
                    'name' => 'Simple Product 1 Store 1',
                    'status' => Status::STATUS_ENABLED,
                ],
                $store2->getId() => [
                    'name' => 'Simple Product 1 Store 2',
                    'status' => Status::STATUS_DISABLED,
                ],
                $store3->getId() => [
                    'name' => 'Simple Product 1 Store 3',
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
                $store1->getWebsiteId(),
                $store2->getWebsiteId(),
                $store3->getWebsiteId(),
            ],
            'data' => [
                $configurableAttribute->getAttributeCode() => '2',
            ],
            'stores' => [
                $store1->getId() => [
                    'name' => 'Simple Product 2 Store 1',
                    'status' => Status::STATUS_DISABLED,
                ],
                $store2->getId() => [
                    'name' => 'Simple Product 2 Store 2',
                    'status' => Status::STATUS_DISABLED,
                ],
                $store3->getId() => [
                    'name' => 'Simple Product 2 Store 3',
                    'status' => Status::STATUS_ENABLED,
                ],
            ],
        ]);
        $productSimple2 = $this->productFixturePool->get('test_simple_product_2');

        $this->createProduct([
            'key' => 'test_configurable_product',
            'sku' => 'test_configurable_product',
            'status' => Status::STATUS_ENABLED,
            'website_ids' => [
                $store1->getWebsiteId(),
                $store2->getWebsiteId(),
                $store3->getWebsiteId(),
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
                $store1->getId() => [
                    'name' => 'Configurable Product 1 Store 1',
                    'status' => Status::STATUS_ENABLED,
                ],
                $store2->getId() => [
                    'name' => 'Configurable Product 1 Store 2',
                    'status' => Status::STATUS_ENABLED,
                ],
                $store3->getId() => [
                    'name' => 'Configurable Product 1 Store 3',
                    'status' => Status::STATUS_DISABLED,
                ],
            ],
        ]);

        $scopeProvider = $this->objectManager->get(ScopeProviderInterface::class);
        $scopeProvider->setCurrentScope(scope: $storeFixture1->get());

        $provider = $this->instantiateTestObject();
        // STORE 1
        $searchResults1 = $provider->get(store: $store1);
        $items1 = [];
        foreach ($searchResults1 as $searchResult) {
            $items1[] = $searchResult;
        }
        $this->assertCount(expectedCount: 2, haystack: $items1);
        $product1Array = array_filter(
            array: $items1,
            callback: static function (ProductInterface $product) use ($productSimple1): bool {
                return (int)$product->getId() === (int)$productSimple1->getId();
            },
        );
        $productSimple1Store1 = array_shift($product1Array);
        $this->assertSame(
            expected: 'Simple Product 1 Store 1',
            actual: $productSimple1Store1->getName(),
        );
        $this->assertSame(
            expected: Status::STATUS_ENABLED,
            actual: (int)$productSimple1Store1->getStatus(),
            message: 'Product Status',
        );
        $product2Array = array_filter(
            array: $items1,
            callback: static function (ProductInterface $product) use ($productSimple2): bool {
                return (int)$product->getId() === (int)$productSimple2->getId();
            },
        );
        $productSimple2Store1 = array_shift($product2Array);
        $this->assertSame(
            expected: 'Simple Product 2 Store 1',
            actual: $productSimple2Store1->getName(),
        );
        $this->assertSame(
            expected: Status::STATUS_DISABLED,
            actual: (int)$productSimple2Store1->getStatus(),
            message: 'Product Status',
        );

        // STORE 2
        $searchResults2 = $provider->get(store: $store2);
        $items2 = [];
        foreach ($searchResults2 as $searchResult) {
            $items2[] = $searchResult;
        }
        $this->assertCount(expectedCount: 2, haystack: $items2);
        $product1Array = array_filter(
            array: $items2,
            callback: static function (ProductInterface $product) use ($productSimple1): bool {
                return (int)$product->getId() === (int)$productSimple1->getId();
            },
        );
        $productSimple1Store2 = array_shift($product1Array);
        $this->assertSame(
            expected: 'Simple Product 1 Store 2',
            actual: $productSimple1Store2->getName(),
        );
        $this->assertSame(
            expected: Status::STATUS_DISABLED,
            actual: (int)$productSimple1Store2->getStatus(),
            message: 'Product Status',
        );
        $product2Array = array_filter(
            array: $items2,
            callback: static function (ProductInterface $product) use ($productSimple2): bool {
                return (int)$product->getId() === (int)$productSimple2->getId();
            },
        );
        $productSimple2Store2 = array_shift($product2Array);
        $this->assertSame(
            expected: 'Simple Product 2 Store 2',
            actual: $productSimple2Store2->getName(),
        );
        $this->assertSame(
            expected: Status::STATUS_DISABLED,
            actual: (int)$productSimple2Store2->getStatus(),
            message: 'Product Status',
        );

        // STORE 3
        $searchResults3 = $provider->get(store: $store3);
        $items3 = [];
        foreach ($searchResults3 as $searchResult) {
            $items3[] = $searchResult;
        }
        $this->assertCount(expectedCount: 2, haystack: $items3);
        $product1Array = array_filter(
            array: $items3,
            callback: static function (ProductInterface $product) use ($productSimple1): bool {
                return (int)$product->getId() === (int)$productSimple1->getId();
            },
        );
        $productSimple1Store3 = array_shift($product1Array);
        $this->assertSame(
            expected: 'Simple Product 1 Store 3',
            actual: $productSimple1Store3->getName(),
        );
        $this->assertSame(
            expected: Status::STATUS_DISABLED,
            actual: (int)$productSimple1Store3->getStatus(),
            message: 'Product Status',
        );
        $product2Array = array_filter(
            array: $items3,
            callback: static function (ProductInterface $product) use ($productSimple2): bool {
                return (int)$product->getId() === (int)$productSimple2->getId();
            },
        );
        $productSimple2Store3 = array_shift($product2Array);
        $this->assertSame(
            expected: 'Simple Product 2 Store 3',
            actual: $productSimple2Store3->getName(),
        );
        $this->assertSame(
            expected: Status::STATUS_DISABLED,
            actual: (int)$productSimple2Store3->getStatus(),
            message: 'Product Status',
        );
    }
}
