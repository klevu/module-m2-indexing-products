<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Test\Integration\Service\Provider;

use Klevu\Configuration\Service\Provider\ScopeProviderInterface;
use Klevu\IndexingApi\Service\Provider\EntityProviderInterface;
use Klevu\IndexingProducts\Service\Provider\ProductEntityProvider;
use Klevu\IndexingProducts\Service\Provider\ProductEntityProvider\Grouped as GroupedEntityProviderVirtualType;
use Klevu\TestFixtures\Catalog\ProductTrait;
use Klevu\TestFixtures\Store\StoreFixturesPool;
use Klevu\TestFixtures\Store\StoreTrait;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Klevu\TestFixtures\Website\WebsiteFixturesPool;
use Klevu\TestFixtures\Website\WebsiteTrait;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Api\ProductWebsiteLinkRepositoryInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\GroupedProduct\Model\Product\Type\Grouped;
use Magento\Store\Api\WebsiteRepositoryInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use TddWizard\Fixtures\Catalog\ProductFixturePool;

/**
 * @covers \Klevu\IndexingProducts\Service\Provider\ProductEntityProvider\Grouped::class
 * @method EntityProviderInterface instantiateTestObject(?array $arguments = null)
 * @method EntityProviderInterface instantiateTestObjectFromInterface(?array $arguments = null)
 */
class GroupedProductEntityProviderTest extends TestCase
{
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

        $this->implementationFqcn = GroupedEntityProviderVirtualType::class; // @phpstan-ignore-line
        $this->interfaceFqcn = EntityProviderInterface::class;
        $this->implementationForVirtualType = ProductEntityProvider::class;
        $this->objectManager = Bootstrap::getObjectManager();
        $this->websiteFixturesPool = $this->objectManager->get(WebsiteFixturesPool::class);
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
        $this->websiteFixturesPool->rollback();
    }

    /**
     * @magentoDbIsolation disabled
     */
    public function testGet_ReturnsProductData_AtStoreScope(): void
    {
        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');
        $scopeProvider = $this->objectManager->get(ScopeProviderInterface::class);
        $scopeProvider->setCurrentScope(scope: $storeFixture->get());

        $this->createProduct([
            'key' => 'test_product_1',
            'sku' => 'test_product_1',
            'name' => 'Product Name Store 1',
            'type_id' => Grouped::TYPE_CODE,
        ], $storeFixture->getId());
        $productFixture1 = $this->productFixturePool->get('test_product_1');
        $this->createProduct([
            'key' => 'test_product_2',
            'sku' => 'test_product_2',
            'type_id' => Grouped::TYPE_CODE,
        ], $storeFixture->getId());
        $productFixture2 = $this->productFixturePool->get('test_product_2');

        $provider = $this->instantiateTestObject();
        $searchResults = $provider->get(store: $storeFixture->get());

        $items = [];
        foreach ($searchResults as $searchResult) {
            foreach ($searchResult as $key => $resultItems) {
                $items[$key] = $resultItems;
            }
        }

        $productIds = array_map(
            callback: static function (ProductInterface $item): int {
                return (int)$item->getId();
            },
            array: $items,
        );
        $this->assertContains(needle: (int)$productFixture1->getId(), haystack: $productIds);
        $this->assertContains(needle: (int)$productFixture2->getId(), haystack: $productIds);

        $product1Array = array_filter(
            array: $items,
            callback: static function (ProductInterface $product) use ($productFixture1): bool {
                return (int)$product->getId() === (int)$productFixture1->getId();
            },
        );
        $product1 = array_shift($product1Array);
        $this->assertSame(
            expected: 'Product Name Store 1',
            actual: $product1->getName(),
        );

        $productRepository = $this->objectManager->get(ProductRepositoryInterface::class);
        $globalProduct = $productRepository->get(sku: $product1->getSku());
        $this->assertSame(
            expected: 'Grouped product',
            actual: $globalProduct->getName(),
        );
    }

    /**
     * @magentoDbIsolation disabled
     */
    public function testGet_ReturnsProductData_AtGlobalScope(): void
    {
        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');
        $scopeProvider = $this->objectManager->get(ScopeProviderInterface::class);
        $scopeProvider->setCurrentScope(scope: $storeFixture->get());

        $this->createProduct([
            'key' => 'test_product_1',
            'sku' => 'test_product_1',
            'name' => 'Product Name Store 1',
            'type_id' => Grouped::TYPE_CODE,
        ], $storeFixture->getId());
        $productFixture1 = $this->productFixturePool->get('test_product_1');
        $this->createProduct([
            'key' => 'test_product_2',
            'sku' => 'test_product_2',
            'type_id' => Grouped::TYPE_CODE,
        ], $storeFixture->getId());
        $productFixture2 = $this->productFixturePool->get('test_product_2');

        $provider = $this->instantiateTestObject();
        $searchResults = $provider->get();

        $items = [];
        foreach ($searchResults as $searchResult) {
            foreach ($searchResult as $key => $resultItems) {
                $items[$key] = $resultItems;
            }
        }

        $productIds = array_map(
            callback: static function (ProductInterface $item): int {
                return (int)$item->getId();
            },
            array: $items,
        );
        $this->assertContains(needle: (int)$productFixture1->getId(), haystack: $productIds);
        $this->assertContains(needle: (int)$productFixture2->getId(), haystack: $productIds);

        $product1Array = array_filter(
            array: $items,
            callback: static function (ProductInterface $product) use ($productFixture1): bool {
                return (int)$product->getId() === (int)$productFixture1->getId();
            },
        );
        $product1 = array_shift($product1Array);
        $this->assertSame(
            expected: 'Grouped product',
            actual: $product1->getName(),
        );

        $productRepository = $this->objectManager->get(ProductRepositoryInterface::class);
        $globalProduct = $productRepository->get(sku: $product1->getSku());
        $this->assertSame(
            expected: 'Grouped product',
            actual: $globalProduct->getName(),
        );
    }

    /**
     * @magentoDbIsolation disabled
     */
    public function testGet_ForProductNotAssignedToWebsite_AtStoreScope(): void
    {
        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');
        $store = $storeFixture->get();
        $scopeProvider = $this->objectManager->get(ScopeProviderInterface::class);
        $scopeProvider->setCurrentScope(scope: $storeFixture->get());

        $this->createProduct([
            'key' => 'test_product_1',
            'sku' => 'test_product_1',
            'website_ids' => [$store->getWebsiteId()],
            'type_id' => Grouped::TYPE_CODE,
        ], $storeFixture->getId());
        $productFixture1 = $this->productFixturePool->get('test_product_1');

        $this->createProduct([
            'key' => 'test_product_2',
            'sku' => 'test_product_2',
            'website_ids' => [],
            'type_id' => Grouped::TYPE_CODE,
        ], $storeFixture->getId());
        $productFixture2 = $this->productFixturePool->get('test_product_2');

        $websiteRepository = $this->objectManager->get(WebsiteRepositoryInterface::class);
        $defaultWebsite = $websiteRepository->get('base');
        $websiteLinkRepository = $this->objectManager->get(ProductWebsiteLinkRepositoryInterface::class);
        $websiteLinkRepository->deleteById(sku: $productFixture2->getSku(), websiteId: (int)$defaultWebsite->getId());

        $provider = $this->instantiateTestObject();
        $searchResults = $provider->get(store: $store);

        $items = [];
        foreach ($searchResults as $searchResult) {
            foreach ($searchResult as $key => $resultItems) {
                $items[$key] = $resultItems;
            }
        }
        $product1Array = array_filter(
            array: $items,
            callback: static function (ProductInterface $product) use ($productFixture1): bool {
                return (int)$product->getId() === (int)$productFixture1->getId();
            },
        );
        $this->assertCount(expectedCount: 1, haystack: $product1Array);

        $product2Array = array_filter(
            array: $items,
            callback: static function (ProductInterface $product) use ($productFixture2): bool {
                return (int)$product->getId() === (int)$productFixture2->getId();
            },
        );
        $this->assertCount(expectedCount: 0, haystack: $product2Array);
    }
}
