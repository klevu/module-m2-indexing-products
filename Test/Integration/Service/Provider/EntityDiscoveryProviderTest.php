<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Test\Integration\Service\Provider;

use Klevu\Configuration\Service\Provider\ScopeProviderInterface;
use Klevu\Indexing\Service\Provider\EntityDiscoveryProvider;
use Klevu\Indexing\Test\Integration\Traits\IndexingEntitiesTrait;
use Klevu\IndexingApi\Model\MagentoEntityInterface;
use Klevu\IndexingApi\Service\Provider\EntityDiscoveryProviderInterface;
use Klevu\IndexingProducts\Service\Provider\EntityDiscoveryProvider as EntityDiscoveryProviderVirtualType;
use Klevu\TestFixtures\Catalog\ProductTrait;
use Klevu\TestFixtures\Store\StoreFixturesPool;
use Klevu\TestFixtures\Store\StoreTrait;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\SetAuthKeysTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Klevu\TestFixtures\Website\WebsiteFixturesPool;
use Klevu\TestFixtures\Website\WebsiteTrait;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use TddWizard\Fixtures\Catalog\ProductFixturePool;
use TddWizard\Fixtures\Core\ConfigFixture;

/**
 * @covers \Klevu\Indexing\Service\Provider\EntityDiscoveryProvider::class
 * @method EntityDiscoveryProviderInterface instantiateTestObject(?array $arguments = null)
 * @method EntityDiscoveryProviderInterface instantiateTestObjectFromInterface(?array $arguments = null)
 */
class EntityDiscoveryProviderTest extends TestCase
{
    use IndexingEntitiesTrait;
    use ObjectInstantiationTrait;
    use SetAuthKeysTrait;
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

        $this->implementationFqcn = EntityDiscoveryProviderVirtualType::class; // @phpstan-ignore-line
        $this->interfaceFqcn = EntityDiscoveryProviderInterface::class;
        $this->implementationForVirtualType = EntityDiscoveryProvider::class;
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

    public function testGetEntityType_ReturnsCorrectString(): void
    {
        $provider = $this->instantiateTestObject();
        $this->assertSame(
            expected: 'KLEVU_PRODUCT',
            actual: $provider->getEntityType(),
            message: 'Get Entity Type',
        );
    }

    /**
     * @magentoDbIsolation disabled
     * @magentoConfigFixture klevu_test_store_1_store klevu_configuration/auth_keys/js_api_key klevu-js-api-key
     * @magentoConfigFixture klevu_test_store_1_store klevu_configuration/auth_keys/rest_auth_key klevu-rest-auth-key
     * @magentoConfigFixture default/klevu/indexing/exclude_disabled_products 0
     * @magentoConfigFixture default/klevu/indexing/exclude_oos_products 0
     */
    public function testGetData_IsIndexableChecksDisabled(): void
    {
        $apiKey = 'klevu-js-api-key';

        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');
        $scopeProvider = $this->objectManager->get(ScopeProviderInterface::class);
        $scopeProvider->setCurrentScope(scope: $storeFixture->get());
        $this->setAuthKeys(
            $scopeProvider,
            $apiKey,
            'rest-auth-key',
        );
        $this->cleanIndexingEntities($apiKey);
        $scopeProvider->unsetCurrentScope();

        $this->createProduct(storeId: $storeFixture->getId());
        $product = $this->productFixturePool->get('test_product');

        $provider = $this->instantiateTestObject();
        $result = $provider->getData(apiKeys: [$apiKey]);
        $productEntitiesByApiKey = iterator_to_array($result);

        $this->assertCount(expectedCount: 1, haystack: $productEntitiesByApiKey);
        $productEntities = iterator_to_array($productEntitiesByApiKey[$apiKey]);
        $productEntityArray = array_filter(
            array: $productEntities[0] ?? [],
            callback: static fn (MagentoEntityInterface $prodEntity): bool => (
                (int)$prodEntity->getEntityId() === (int)$product->getId()
            ),
        );
        $productEntity = array_shift($productEntityArray);

        $this->assertSame(expected: (int)$product->getId(), actual: $productEntity->getEntityId());
        $this->assertSame(expected: $apiKey, actual: $productEntity->getApiKey());
        $this->assertTrue(condition: $productEntity->isIndexable());
    }

    /**
     * @magentoDbIsolation disabled
     * @magentoAppIsolation enabled
     * @magentoConfigFixture klevu_test_store_1_store klevu_configuration/auth_keys/js_api_key klevu-js-api-key
     * @magentoConfigFixture klevu_test_store_1_store klevu_configuration/auth_keys/rest_auth_key klevu-rest-auth-key
     * @magentoConfigFixture default/klevu/indexing/exclude_disabled_products 1
     * @magentoConfigFixture default/klevu/indexing/exclude_oos_products 0
     */
    public function testGetData_ForDisabledProduct_IsIndexableChecksEnabled(): void
    {
        $apiKey = 'klevu-js-api-key';

        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');
        $store = $storeFixture->get();
        $scopeProvider = $this->objectManager->get(ScopeProviderInterface::class);
        $scopeProvider->setCurrentScope(scope: $storeFixture->get());
        $this->cleanIndexingEntities($apiKey);
        $scopeProvider->unsetCurrentScope();

        $this->createProduct(
            productData: [
                'in_stock' => true,
                'qty' => 123,
                'status' => Status::STATUS_ENABLED,
                'website_ids' => [(int)$store->getWebsiteId()],
                'stores' => [
                    $store->getId() => [
                        'status' => Status::STATUS_DISABLED,
                    ],
                ],
            ],
        );

        $product = $this->productFixturePool->get('test_product');

        $provider = $this->instantiateTestObject();
        $result = $provider->getData(apiKeys: [$apiKey]);
        $productEntitiesByApiKey = iterator_to_array($result);

        $this->assertCount(expectedCount: 1, haystack: $productEntitiesByApiKey);
        $productEntities = iterator_to_array($productEntitiesByApiKey[$apiKey]);
        $productEntityArray = array_filter(
            array: $productEntities[0] ?? [],
            callback: static fn (MagentoEntityInterface $prodEntity): bool => (
                (int)$prodEntity->getEntityId() === (int)$product->getId()
            ),
        );
        $productEntity = array_shift($productEntityArray);

        $this->assertSame(expected: (int)$product->getId(), actual: $productEntity->getEntityId());
        $this->assertSame(expected: $apiKey, actual: $productEntity->getApiKey());
        $this->assertFalse(condition: $productEntity->isIndexable());
    }

    /**
     * @magentoDbIsolation disabled
     * @magentoConfigFixture klevu_test_store_1_store klevu_configuration/auth_keys/js_api_key klevu-js-api-key
     * @magentoConfigFixture klevu_test_store_1_store klevu_configuration/auth_keys/rest_auth_key klevu-rest-auth-key
     * @magentoConfigFixture default/klevu/indexing/exclude_disabled_products 0
     * @magentoConfigFixture default/klevu/indexing/exclude_oos_products 1
     */
    public function testGetData_ForOutOfStockProduct_IsIndexableChecksEnabled(): void
    {
        $apiKey = 'klevu-js-api-key';

        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');
        $store = $storeFixture->get();
        $scopeProvider = $this->objectManager->get(ScopeProviderInterface::class);
        $scopeProvider->setCurrentScope(scope: $storeFixture->get());
        $this->cleanIndexingEntities($apiKey);
        $scopeProvider->unsetCurrentScope();

        $this->createProduct(
            productData: [
                'in_stock' => false,
                'qty' => 0,
                'status' => Status::STATUS_ENABLED,
                'website_ids' => [(int)$store->getWebsiteId()],
            ],
            storeId: (int)$store->getId(),
        );
        $product = $this->productFixturePool->get('test_product');

        $provider = $this->instantiateTestObject();
        $result = $provider->getData(apiKeys: [$apiKey]);
        $productEntitiesByApiKey = iterator_to_array($result);

        $this->assertCount(expectedCount: 1, haystack: $productEntitiesByApiKey);
        $productEntities = iterator_to_array($productEntitiesByApiKey[$apiKey]);
        $productEntityArray = array_filter(
            array: $productEntities[0] ?? [],
            callback: static fn (MagentoEntityInterface $prodEntity): bool => (
                (int)$prodEntity->getEntityId() === (int)$product->getId()
            ),
        );
        $productEntity = array_shift($productEntityArray);

        $this->assertSame(expected: (int)$product->getId(), actual: $productEntity->getEntityId());
        $this->assertSame(expected: $apiKey, actual: $productEntity->getApiKey());
        $this->assertFalse(condition: $productEntity->isIndexable());
    }

    /**
     * @magentoDbIsolation disabled
     * @magentoAppIsolation enabled
     * @magentoConfigFixture default/klevu/indexing/exclude_disabled_products 1
     */
    public function testGetData_IsIndexable_ForProductDisabledInOneStore_IsIndexableChecksEnabled(): void
    {
        $apiKey = 'klevu-js-api-key';

        $this->createWebsite();
        $websiteFixture = $this->websiteFixturesPool->get('test_website');

        $this->createStore([
            'code' => 'klevu_test_store_1',
            'key' => 'test_store_1',
        ]);
        $storeFixture1 = $this->storeFixturesPool->get('test_store_1');
        $store1 = $storeFixture1->get();

        $this->createStore([
            'website_id' => $websiteFixture->getId(),
            'code' => 'klevu_test_store_2',
            'key' => 'test_store_2',
        ]);
        $storeFixture2 = $this->storeFixturesPool->get('test_store_2');
        $store2 = $storeFixture2->get();

        $scopeProvider = $this->objectManager->get(ScopeProviderInterface::class);
        $scopeProvider->setCurrentScope(scope: $storeFixture2->get());
        $this->cleanIndexingEntities($apiKey);
        $scopeProvider->unsetCurrentScope();

        ConfigFixture::setForStore(
            path: 'klevu_configuration/auth_keys/js_api_key',
            value: 'klevu-js-api-key',
            storeCode: $store1->getCode(),
        );
        ConfigFixture::setForStore(
            path: 'klevu_configuration/auth_keys/rest_auth_key',
            value: 'klevu-rest-auth-key',
            storeCode: $store1->getCode(),
        );
        ConfigFixture::setForStore(
            path: 'klevu_configuration/auth_keys/js_api_key',
            value: 'klevu-js-api-key',
            storeCode: $store2->getCode(),
        );
        ConfigFixture::setForStore(
            path: 'klevu_configuration/auth_keys/rest_auth_key',
            value: 'klevu-rest-auth-key',
            storeCode: $store2->getCode(),
        );

        $this->createProduct(
            productData: [
                'in_stock' => true,
                'qty' => 123,
                'status' => Status::STATUS_ENABLED,
                'website_ids' => [
                    (int)$store2->getWebsiteId(),
                ],
                'stores' => [
                    $store1->getId() => [
                        'status' => Status::STATUS_ENABLED,
                    ],
                    $store2->getId() => [
                        'status' => Status::STATUS_DISABLED,
                    ],
                ],
            ],
        );
        $product = $this->productFixturePool->get('test_product');

        $provider = $this->instantiateTestObject([
            'isCheckIsIndexableAtStoreScope' => true,
        ]);
        $result = $provider->getData(apiKeys: [$apiKey]);
        $productEntitiesByApiKey = iterator_to_array($result);

        $this->assertCount(expectedCount: 1, haystack: $productEntitiesByApiKey);
        $productEntities = iterator_to_array($productEntitiesByApiKey[$apiKey]);
        $productEntityArray = array_filter(
            array: $productEntities[0] ?? [],
            callback: static fn (MagentoEntityInterface $prodEntity): bool => (
                (int)$prodEntity->getEntityId() === (int)$product->getId()
            ),
        );
        $productEntity = array_shift($productEntityArray);

        $this->assertSame(expected: (int)$product->getId(), actual: $productEntity->getEntityId());
        $this->assertSame(expected: $apiKey, actual: $productEntity->getApiKey());
        $this->assertFalse(condition: $productEntity->isIndexable(), message: 'In Indexable');
    }
}
