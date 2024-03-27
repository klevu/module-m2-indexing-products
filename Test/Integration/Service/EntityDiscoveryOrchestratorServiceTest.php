<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Test\Integration\Service;

use Klevu\Indexing\Model\IndexingEntity;
use Klevu\Indexing\Model\ResourceModel\IndexingEntity\Collection;
use Klevu\Indexing\Service\EntityDiscoveryOrchestratorService;
use Klevu\Indexing\Test\Integration\Traits\IndexingEntitiesTrait;
use Klevu\IndexingApi\Api\Data\IndexingEntityInterface;
use Klevu\IndexingApi\Model\Source\Actions;
use Klevu\IndexingApi\Service\EntityDiscoveryOrchestratorServiceInterface;
use Klevu\TestFixtures\Catalog\ProductTrait;
use Klevu\TestFixtures\Store\StoreFixturesPool;
use Klevu\TestFixtures\Store\StoreTrait;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Klevu\TestFixtures\Traits\TestInterfacePreferenceTrait;
use Klevu\TestFixtures\Website\WebsiteFixturesPool;
use Klevu\TestFixtures\Website\WebsiteTrait;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductWebsiteLinkRepositoryInterface;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Type;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Downloadable\Model\Product\Type as DownloadableType;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\ObjectManagerInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Api\WebsiteRepositoryInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use TddWizard\Fixtures\Catalog\ProductFixture;
use TddWizard\Fixtures\Catalog\ProductFixturePool;

/**
 * @covers \Klevu\Indexing\Service\EntityDiscoveryOrchestratorService::class
 * @method EntityDiscoveryOrchestratorServiceInterface instantiateTestObject(?array $arguments = null)
 * @method EntityDiscoveryOrchestratorServiceInterface instantiateTestObjectFromInterface(?array $arguments = null)
 */
class EntityDiscoveryOrchestratorServiceTest extends TestCase
{
    use IndexingEntitiesTrait;
    use ObjectInstantiationTrait;
    use ProductTrait;
    use StoreTrait;
    use TestImplementsInterfaceTrait;
    use TestInterfacePreferenceTrait;
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

        $this->implementationFqcn = EntityDiscoveryOrchestratorService::class;
        $this->interfaceFqcn = EntityDiscoveryOrchestratorServiceInterface::class;
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
     * @magentoAppIsolation enabled
     * @magentoConfigFixture klevu_test_store_1_store klevu_configuration/auth_keys/js_api_key klevu-js-api-key
     * @magentoConfigFixture klevu_test_store_1_store klevu_configuration/auth_keys/rest_auth_key klevu-rest-auth-key
     * @magentoConfigFixture default/klevu/indexing/exclude_disabled_products 0
     * @magentoConfigFixture default/klevu/indexing/exclude_oos_products 0
     */
    public function testExecute_AddsNewProducts_AsIndexable_WhenExcludeChecksDisabled(): void
    {
        $apiKey = 'klevu-js-api-key';
        $this->cleanIndexingEntities($apiKey);

        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');
        $store = $storeFixture->get();

        $productCollectionCount = count($this->getProducts($store));

        $this->createProduct(
            productData: [
                'in_stock' => true,
                'qty' => 123,
                'status' => Status::STATUS_ENABLED,
                'website_ids' => [(int)$store->getWebsiteId()],
                'key' => 'test_product_1',
            ],
            storeId: (int)$store->getId(),
        );
        $product1 = $this->productFixturePool->get('test_product_1');
        $this->createProduct(
            productData: [
                'in_stock' => true,
                'qty' => 123,
                'status' => Status::STATUS_DISABLED,
                'website_ids' => [(int)$store->getWebsiteId()],
                'type_id' => Type::TYPE_VIRTUAL,
                'key' => 'test_product_2',
            ],
            storeId: (int)$store->getId(),
        );
        $product2 = $this->productFixturePool->get('test_product_2');
        $this->createProduct(
            productData: [
                'in_stock' => false,
                'qty' => 0,
                'status' => Status::STATUS_ENABLED,
                'website_ids' => [(int)$store->getWebsiteId()],
                'type_id' => DownloadableType::TYPE_DOWNLOADABLE,
                'key' => 'test_product_3',
            ],
            storeId: (int)$store->getId(),
        );
        $product3 = $this->productFixturePool->get('test_product_3');
        $this->createProduct(
            productData: [
                'in_stock' => true,
                'qty' => 123,
                'status' => Status::STATUS_ENABLED,
                'website_ids' => [], // will still assign to default website
                'key' => 'test_product_4',
            ],
            storeId: (int)$store->getId(),
        );
        $product4 = $this->productFixturePool->get('test_product_4');
        $this->removeWebsitesFromProduct($product4);
        $this->cleanIndexingEntities($apiKey);

        $service = $this->instantiateTestObject();
        $result = $service->execute(entityType: 'KLEVU_PRODUCT', apiKeys: [$apiKey]);
        $this->assertTrue($result->isSuccess());

        $collection = $this->objectManager->create(Collection::class);
        $collection->addFieldToFilter(IndexingEntity::API_KEY, ['eq' => $apiKey]);
        $indexingEntities = $collection->getItems();
        $this->assertCount(
            expectedCount: 3 + $productCollectionCount,
            haystack: $indexingEntities,
            message: 'Final Items Count',
        );

        $this->assertAddIndexingEntity($indexingEntities, $product1, $apiKey, true);
        $this->assertAddIndexingEntity($indexingEntities, $product2, $apiKey, true);
        $this->assertAddIndexingEntity($indexingEntities, $product3, $apiKey, true);

        $this->cleanIndexingEntities($apiKey);
    }

    /**
     * @magentoDbIsolation disabled
     * @magentoAppIsolation enabled
     * @magentoConfigFixture klevu_test_store_1_store klevu_configuration/auth_keys/js_api_key klevu-js-api-key
     * @magentoConfigFixture klevu_test_store_1_store klevu_configuration/auth_keys/rest_auth_key klevu-rest-auth-key
     * @magentoConfigFixture default/klevu/indexing/exclude_disabled_products 1
     * @magentoConfigFixture default/klevu/indexing/exclude_oos_products 0
     */
    public function testExecute_AddsNewDisabledProducts_AsNotIndexable_WhenExcludeEnabled(): void
    {
        $apiKey = 'klevu-js-api-key';
        $this->cleanIndexingEntities($apiKey);

        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');
        $store = $storeFixture->get();
        $productCollectionCount = count($this->getProducts($store));

        $this->createProduct(
            productData: [
                'in_stock' => true,
                'qty' => 123,
                'status' => Status::STATUS_ENABLED,
                'website_ids' => [(int)$store->getWebsiteId()],
                'type_id' => DownloadableType::TYPE_DOWNLOADABLE,
                'key' => 'test_product_1',
            ],
            storeId: (int)$store->getId(),
        );
        $product1 = $this->productFixturePool->get('test_product_1');
        $this->createProduct(
            productData: [
                'in_stock' => true,
                'qty' => 123,
                'status' => Status::STATUS_DISABLED,
                'website_ids' => [(int)$store->getWebsiteId()],
                'type_id' => Type::TYPE_VIRTUAL,
                'key' => 'test_product_2',
            ],
            storeId: (int)$store->getId(),
        );
        $product2 = $this->productFixturePool->get('test_product_2');
        $this->createProduct(
            productData: [
                'in_stock' => false,
                'qty' => 0,
                'status' => Status::STATUS_ENABLED,
                'website_ids' => [(int)$store->getWebsiteId()],
                'key' => 'test_product_3',
            ],
            storeId: (int)$store->getId(),
        );
        $product3 = $this->productFixturePool->get('test_product_3');
        $this->createProduct(
            productData: [
                'in_stock' => true,
                'qty' => 123,
                'status' => Status::STATUS_ENABLED,
                'website_ids' => [], // will still assign to default website
                'key' => 'test_product_4',
            ],
            storeId: (int)$store->getId(),
        );
        $product4 = $this->productFixturePool->get('test_product_4');
        $this->removeWebsitesFromProduct($product4);
        $this->cleanIndexingEntities($apiKey);

        $service = $this->instantiateTestObject();
        $result = $service->execute(entityType: 'KLEVU_PRODUCT', apiKeys: [$apiKey]);
        $this->assertTrue($result->isSuccess());

        $collection = $this->objectManager->create(Collection::class);
        $collection->addFieldToFilter(IndexingEntity::API_KEY, ['eq' => $apiKey]);
        $indexingEntities = $collection->getItems();
        $this->assertCount(
            expectedCount: 3 + $productCollectionCount,
            haystack: $indexingEntities,
            message: 'Final Items Count',
        );

        $this->assertAddIndexingEntity($indexingEntities, $product1, $apiKey, true);
        $this->assertAddIndexingEntity($indexingEntities, $product2, $apiKey, false);

        $this->cleanIndexingEntities($apiKey);

        $this->markTestIncomplete(
            'TODO fix OOS products test product stockItem object (it does not contain is_in_stock)',
        );
        $this->assertAddIndexingEntity($indexingEntities, $product3, $apiKey, false);
    }

    /**
     * @magentoDbIsolation disabled
     * @magentoConfigFixture klevu_test_store_1_store klevu_configuration/auth_keys/js_api_key klevu-js-api-key-1
     * @magentoConfigFixture klevu_test_store_1_store klevu_configuration/auth_keys/rest_auth_key klevu-rest-auth-key-1
     * @magentoConfigFixture klevu_test_store_2_store klevu_configuration/auth_keys/js_api_key klevu-js-api-key-2
     * @magentoConfigFixture klevu_test_store_2_store klevu_configuration/auth_keys/rest_auth_key klevu-rest-auth-key-2
     * @magentoConfigFixture default/klevu/indexing/exclude_disabled_products 1
     * @magentoConfigFixture default/klevu/indexing/exclude_oos_products 0
     */
    public function testExecute_HandlesMultipleStores_DifferentKeys(): void
    {
        $apiKey1 = 'klevu-js-api-key-1';
        $this->cleanIndexingEntities($apiKey1);

        $this->createStore([
            'code' => 'klevu_test_store_1',
            'key' => 'test_store_1',
        ]);
        $storeFixture = $this->storeFixturesPool->get('test_store_1');
        $store1 = $storeFixture->get();

        $apiKey2 = 'klevu-js-api-key-2';
        $this->cleanIndexingEntities($apiKey2);

        $this->createStore([
            'code' => 'klevu_test_store_2',
            'key' => 'test_store_2',
        ]);
        $storeFixture = $this->storeFixturesPool->get('test_store_2');
        $store2 = $storeFixture->get();

        $this->createProduct(
            productData: [
                'in_stock' => true,
                'qty' => 123,
                'status' => Status::STATUS_ENABLED,
                'website_ids' => [(int)$store1->getWebsiteId()],
                'type_id' => Type::TYPE_VIRTUAL,
                'key' => 'test_product_1',
            ],
            storeId: (int)$store1->getId(),
        );
        $productStore1 = $this->productFixturePool->get('test_product_1');
        $this->createProduct(
            productData: [
                'in_stock' => true,
                'qty' => 123,
                'status' => Status::STATUS_DISABLED,
                'website_ids' => [(int)$store2->getWebsiteId()],
                'key' => 'test_product_2',
            ],
            storeId: (int)$store2->getId(),
        );
        $productStore2 = $this->productFixturePool->get('test_product_2');
        $this->cleanIndexingEntities($apiKey1);
        $this->cleanIndexingEntities($apiKey2);

        $service = $this->instantiateTestObject();
        $result = $service->execute(entityType: 'KLEVU_PRODUCT');
        $this->assertTrue($result->isSuccess());

        $collection = $this->objectManager->create(Collection::class);
        $collection->addFieldToFilter(IndexingEntity::API_KEY, ['in' => [$apiKey1, $apiKey2]]);
        $indexingEntities = $collection->getItems();

        $this->assertAddIndexingEntity($indexingEntities, $productStore1, $apiKey1, true);
        $this->assertAddIndexingEntity($indexingEntities, $productStore1, $apiKey2, true);
        $this->assertAddIndexingEntity($indexingEntities, $productStore2, $apiKey1, false);
        $this->assertAddIndexingEntity($indexingEntities, $productStore2, $apiKey2, false);

        $this->cleanIndexingEntities($apiKey1);
        $this->cleanIndexingEntities($apiKey2);
    }

    /**
     * @magentoDbIsolation disabled
     * @magentoConfigFixture klevu_test_store_1_store klevu_configuration/auth_keys/js_api_key klevu-js-api-key-1
     * @magentoConfigFixture klevu_test_store_1_store klevu_configuration/auth_keys/rest_auth_key klevu-rest-auth-key-1
     * @magentoConfigFixture klevu_test_store_2_store klevu_configuration/auth_keys/js_api_key klevu-js-api-key-1
     * @magentoConfigFixture klevu_test_store_2_store klevu_configuration/auth_keys/rest_auth_key klevu-rest-auth-key-1
     * @magentoConfigFixture default/klevu/indexing/exclude_disabled_products 1
     * @magentoConfigFixture default/klevu/indexing/exclude_oos_products 0
     */
    public function testExecute_HandlesMultipleStores_SameKeys(): void
    {
        $apiKey = 'klevu-js-api-key-1';
        $this->cleanIndexingEntities($apiKey);

        $this->createWebsite();
        $websiteFixture = $this->websiteFixturesPool->get('test_website');

        $this->createStore([
            'code' => 'klevu_test_store_1',
            'key' => 'test_store_1',
        ]);
        $storeFixture = $this->storeFixturesPool->get('test_store_1');
        $store1 = $storeFixture->get();
        $productCollectionCount1 = count($this->getProducts($store1));

        $this->createStore([
            'code' => 'klevu_test_store_2',
            'key' => 'test_store_2',
            'website_id' => $websiteFixture->getId(),
        ]);
        $storeFixture = $this->storeFixturesPool->get('test_store_2');
        $store2 = $storeFixture->get();
        $productCollectionCount2 = count($this->getProducts($store2));
        $productCollectionCount = max($productCollectionCount1, $productCollectionCount2);

        $this->createProduct(
            productData: [
                'in_stock' => true,
                'qty' => 123,
                'status' => Status::STATUS_ENABLED,
                'website_ids' => [(int)$store1->getWebsiteId()],
                'key' => 'test_product_1',
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
        $productStore1 = $this->productFixturePool->get('test_product_1');
        $this->createProduct(
            productData: [
                'in_stock' => true,
                'qty' => 123,
                'status' => Status::STATUS_DISABLED,
                'website_ids' => [(int)$store2->getWebsiteId()],
                'type_id' => DownloadableType::TYPE_DOWNLOADABLE,
                'key' => 'test_product_2',
                'stores' => [
                    $store1->getId() => [
                        'status' => Status::STATUS_DISABLED,
                    ],
                    $store2->getId() => [
                        'status' => Status::STATUS_DISABLED,
                    ],
                ],
            ],
        );
        $productStore2 = $this->productFixturePool->get('test_product_2');
        $this->cleanIndexingEntities($apiKey);

        $service = $this->instantiateTestObject();
        $result = $service->execute(entityType: 'KLEVU_PRODUCT');
        $this->assertTrue($result->isSuccess());

        $collection = $this->objectManager->create(Collection::class);
        $collection->addFieldToFilter(IndexingEntity::API_KEY, ['eq' => $apiKey]);
        $indexingEntities = $collection->getItems();
        $this->assertCount(
            expectedCount: 2 + $productCollectionCount,
            haystack: $indexingEntities,
            message: 'Final Items Count',
        );

        $this->assertAddIndexingEntity($indexingEntities, $productStore1, $apiKey, true);
        $this->assertAddIndexingEntity($indexingEntities, $productStore2, $apiKey, false);

        $this->cleanIndexingEntities($apiKey);
    }

    /**
     * @magentoDbIsolation disabled
     * @magentoAppIsolation enabled
     * @magentoConfigFixture klevu_test_store_1_store klevu_configuration/auth_keys/js_api_key klevu-js-api-key
     * @magentoConfigFixture klevu_test_store_1_store klevu_configuration/auth_keys/rest_auth_key klevu-rest-auth-key
     * @magentoConfigFixture default/klevu/indexing/exclude_disabled_products 1
     */
    public function testExecute_SetsExistingIndexableProductForDeletion(): void
    {
        $apiKey = 'klevu-js-api-key';
        $this->cleanIndexingEntities($apiKey);

        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');
        $store = $storeFixture->get();
        $productCollectionCount = count($this->getProducts($store));

        $this->createProduct(
            productData: [
                'in_stock' => true,
                'qty' => 123,
                'status' => Status::STATUS_DISABLED,
                'website_ids' => [(int)$store->getWebsiteId()],
                'key' => 'test_product_1',
            ],
            storeId: (int)$store->getId(),
        );
        $product = $this->productFixturePool->get('test_product_1');
        $this->cleanIndexingEntities($apiKey);

        $this->createIndexingEntity(data: [
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::TARGET_ID => (int)$product->getId(),
            IndexingEntity::IS_INDEXABLE => true,
            IndexingEntity::NEXT_ACTION => Actions::NO_ACTION,
            IndexingEntity::LAST_ACTION => Actions::ADD,
            IndexingEntity::LAST_ACTION_TIMESTAMP => date('Y-m-d H:i:s'),
        ]);

        $service = $this->instantiateTestObject();
        $result = $service->execute(entityType: 'KLEVU_PRODUCT');
        $this->assertTrue($result->isSuccess());

        $collection = $this->objectManager->create(Collection::class);
        $collection->addFieldToFilter(IndexingEntity::API_KEY, ['eq' => $apiKey]);
        $indexingEntities = $collection->getItems();
        $this->assertCount(
            expectedCount: 1 + $productCollectionCount,
            haystack: $indexingEntities,
            message: 'Final Items Count',
        );
        $this->assertDeleteIndexingEntity($indexingEntities, $product, $apiKey, Actions::DELETE, true);

        $this->cleanIndexingEntities($apiKey);
    }

    /**
     * @magentoDbIsolation disabled
     * @magentoAppIsolation enabled
     * @magentoConfigFixture klevu_test_store_1_store klevu_configuration/auth_keys/js_api_key klevu-js-api-key
     * @magentoConfigFixture klevu_test_store_1_store klevu_configuration/auth_keys/rest_auth_key klevu-rest-auth-key
     * @magentoConfigFixture klevu_test_store_2_store klevu_configuration/auth_keys/js_api_key klevu-js-api-key
     * @magentoConfigFixture klevu_test_store_2_store klevu_configuration/auth_keys/rest_auth_key klevu-rest-auth-key
     * @magentoConfigFixture default/klevu/indexing/exclude_disabled_products 1
     */
    public function testExecute_SetsExistingIndexableProductForDeletion_MultiStore(): void
    {
        $apiKey = 'klevu-js-api-key';
        $this->cleanIndexingEntities($apiKey);

        $this->createWebsite();
        $websiteFixture = $this->websiteFixturesPool->get('test_website');

        $this->createStore([
            'code' => 'klevu_test_store_1',
            'key' => 'test_store_1',
        ]);
        $storeFixture = $this->storeFixturesPool->get('test_store_1');
        $store1 = $storeFixture->get();
        $productCollectionCount1 = count($this->getProducts($store1));

        $this->createStore([
            'code' => 'klevu_test_store_2',
            'key' => 'test_store_2',
            'website_id' => $websiteFixture->getId(),
        ]);
        $storeFixture = $this->storeFixturesPool->get('test_store_2');
        $store2 = $storeFixture->get();
        $productCollectionCount2 = count($this->getProducts($store2));
        $productCollectionCount = max($productCollectionCount1, $productCollectionCount2);

        $this->createProduct(
            productData: [
                'key' => 'test_product_1',
                'website_ids' => [
                    $store1->getWebsiteId(),
                    $store2->getWebsiteId(),
                ],
                'type_id' => DownloadableType::TYPE_DOWNLOADABLE,
                'status' => Status::STATUS_DISABLED,
                'stores' => [
                    $store1->getId() => [
                        'status' => Status::STATUS_DISABLED,
                    ],
                    $store2->getId() => [
                        'status' => Status::STATUS_DISABLED,
                    ],
                ],
            ],
        );
        $product1 = $this->productFixturePool->get('test_product_1');
        $this->createProduct(
            productData: [
                'key' => 'test_product_2',
                'website_ids' => [
                    $store1->getWebsiteId(),
                    $store2->getWebsiteId(),
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
        $product2 = $this->productFixturePool->get('test_product_2');
        $this->cleanIndexingEntities($apiKey);

        $this->createIndexingEntity(data: [
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::TARGET_ID => (int)$product1->getId(),
            IndexingEntity::IS_INDEXABLE => true,
            IndexingEntity::NEXT_ACTION => Actions::NO_ACTION,
            IndexingEntity::LAST_ACTION => Actions::ADD,
            IndexingEntity::LAST_ACTION_TIMESTAMP => date('Y-m-d H:i:s'),
        ]);
        $this->createIndexingEntity(data: [
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::TARGET_ID => (int)$product2->getId(),
            IndexingEntity::IS_INDEXABLE => true,
            IndexingEntity::NEXT_ACTION => Actions::NO_ACTION,
            IndexingEntity::LAST_ACTION => Actions::ADD,
            IndexingEntity::LAST_ACTION_TIMESTAMP => date('Y-m-d H:i:s'),
        ]);

        $service = $this->instantiateTestObject();
        $result = $service->execute(entityType: 'KLEVU_PRODUCT');
        $this->assertTrue($result->isSuccess());

        $collection = $this->objectManager->create(Collection::class);
        $collection->addFieldToFilter(IndexingEntity::API_KEY, ['eq' => $apiKey]);
        $indexingEntities = $collection->getItems();
        $this->assertCount(
            expectedCount: 2 + $productCollectionCount,
            haystack: $indexingEntities,
            message: 'Final Items Count',
        );
        $this->assertDeleteIndexingEntity($indexingEntities, $product1, $apiKey, Actions::DELETE, true);
        $this->assertDeleteIndexingEntity($indexingEntities, $product2, $apiKey, Actions::NO_ACTION, true);

        $this->cleanIndexingEntities($apiKey);
    }

    /**
     * @magentoDbIsolation disabled
     * @magentoAppIsolation enabled
     * @magentoConfigFixture klevu_test_store_1_store klevu_configuration/auth_keys/js_api_key klevu-js-api-key
     * @magentoConfigFixture klevu_test_store_1_store klevu_configuration/auth_keys/rest_auth_key klevu-rest-auth-key
     * @magentoConfigFixture default/klevu/indexing/exclude_disabled_products 1
     */
    public function testExecute_SkipsExistingNonIndexableProduct_WhenSetToNotIndexable(): void
    {
        $apiKey = 'klevu-js-api-key';
        $this->cleanIndexingEntities($apiKey);

        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');
        $store = $storeFixture->get();
        $productCollectionCount = count($this->getProducts($store));

        $this->createProduct(
            productData: [
                'in_stock' => true,
                'qty' => 123,
                'status' => Status::STATUS_DISABLED,
                'website_ids' => [(int)$store->getWebsiteId()],
                'type_id' => Type::TYPE_VIRTUAL,
                'key' => 'test_product_1',
            ],
            storeId: (int)$store->getId(),
        );
        $product = $this->productFixturePool->get('test_product_1');
        $this->cleanIndexingEntities($apiKey);

        $this->createIndexingEntity(data: [
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::TARGET_ID => (int)$product->getId(),
            IndexingEntity::IS_INDEXABLE => false,
            IndexingEntity::NEXT_ACTION => Actions::NO_ACTION,
            IndexingEntity::LAST_ACTION => Actions::ADD,
            IndexingEntity::LAST_ACTION_TIMESTAMP => date('Y-m-d H:i:s'),
        ]);

        $service = $this->instantiateTestObject();
        $result = $service->execute(entityType: 'KLEVU_PRODUCT');
        $this->assertTrue($result->isSuccess());

        $collection = $this->objectManager->create(Collection::class);
        $collection->addFieldToFilter(IndexingEntity::API_KEY, ['eq' => $apiKey]);
        $indexingEntities = $collection->getItems();
        $this->assertCount(
            expectedCount: 1 + $productCollectionCount,
            haystack: $indexingEntities,
            message: 'Final Items Count',
        );
        $this->assertDeleteIndexingEntity($indexingEntities, $product, $apiKey, Actions::NO_ACTION, false);

        $this->cleanIndexingEntities($apiKey);
    }

    /**
     * @param ProductFixture $product4
     *
     * @return void
     * @throws CouldNotSaveException
     * @throws NoSuchEntityException
     */
    private function removeWebsitesFromProduct(ProductFixture $product4): void
    {
        $websiteRepository = $this->objectManager->get(WebsiteRepositoryInterface::class);
        $defaultWebsite = $websiteRepository->get('base');
        $websiteLinkRepository = $this->objectManager->get(ProductWebsiteLinkRepositoryInterface::class);
        $websiteLinkRepository->deleteById(sku: $product4->getSku(), websiteId: (int)$defaultWebsite->getId());
    }

    /**
     * @param IndexingEntityInterface[] $indexingEntities
     * @param ProductFixture $product
     * @param string $apiKey
     * @param bool $isIndexable
     *
     * @return void
     * @throws LocalizedException
     */
    private function assertAddIndexingEntity(
        array $indexingEntities,
        ProductFixture $product,
        string $apiKey,
        bool $isIndexable,
    ): void {
        $indexingEntityArray = $this->filterIndexEntities($indexingEntities, $product->getId(), $apiKey);
        $indexingEntity = array_shift($indexingEntityArray);
        $this->assertSame(
            expected: (int)$product->getId(),
            actual: $indexingEntity->getTargetId(),
            message: 'Target Id',
        );
        $this->assertSame(
            expected: 'KLEVU_PRODUCT',
            actual: $indexingEntity->getTargetEntityType(),
            message: 'Target Entity Type',
        );
        $this->assertSame(
            expected: $apiKey,
            actual: $indexingEntity->getApiKey(),
            message: 'Target Entity Type',
        );
        $this->assertSame(
            expected: $isIndexable
                ? Actions::ADD
                : Actions::NO_ACTION,
            actual: $indexingEntity->getNextAction(),
            message: 'Next Action',
        );
        $this->assertSame(
            expected: Actions::NO_ACTION,
            actual: $indexingEntity->getLastAction(),
            message: 'Last Action',
        );
        $this->assertNull(
            actual: $indexingEntity->getLastActionTimestamp(),
            message: 'Last Action Timestamp',
        );
        $this->assertNull(
            actual: $indexingEntity->getLockTimestamp(),
            message: 'Lock Timestamp',
        );
        $this->assertSame(
            expected: $isIndexable,
            actual: $indexingEntity->getIsIndexable(),
            message: 'Is Indexable',
        );
    }

    /**
     * @param IndexingEntityInterface[] $indexingEntities
     * @param ProductFixture $product
     * @param string $apiKey
     * @param Actions $nextAction
     * @param bool $isIndexable
     *
     * @return void
     */
    private function assertDeleteIndexingEntity(
        array $indexingEntities,
        ProductFixture $product,
        string $apiKey,
        Actions $nextAction = Actions::NO_ACTION,
        bool $isIndexable = true,
    ): void {
        $indexingEntityArray = $this->filterIndexEntities($indexingEntities, $product->getId(), $apiKey);
        $indexingEntity = array_shift($indexingEntityArray);
        $this->assertSame(
            expected: (int)$product->getId(),
            actual: $indexingEntity->getTargetId(),
            message: 'Target Id',
        );
        $this->assertSame(
            expected: 'KLEVU_PRODUCT',
            actual: $indexingEntity->getTargetEntityType(),
            message: 'Target Entity Type',
        );
        $this->assertSame(
            expected: $apiKey,
            actual: $indexingEntity->getApiKey(),
            message: 'Target Entity Type',
        );
        $this->assertSame(
            expected: $nextAction,
            actual: $indexingEntity->getNextAction(),
            message: 'Next Action',
        );
        $this->assertNotNull(
            actual: $indexingEntity->getLastActionTimestamp(),
            message: 'Last Action Timestamp',
        );
        $this->assertNull(
            actual: $indexingEntity->getLockTimestamp(),
            message: 'Lock Timestamp',
        );
        $this->assertSame(
            expected: $isIndexable,
            actual: $indexingEntity->getIsIndexable(),
            message: 'Is Indexable',
        );
    }

    /**
     * @param IndexingEntityInterface[] $indexingEntities
     * @param int $entityId
     * @param string $apiKey
     *
     * @return IndexingEntityInterface[]
     */
    private function filterIndexEntities(array $indexingEntities, int $entityId, string $apiKey): array
    {
        return array_filter(
            array: $indexingEntities,
            callback: static function (IndexingEntityInterface $indexingEntity) use ($entityId, $apiKey) {
                return (int)$entityId === (int)$indexingEntity->getTargetId()
                    && $apiKey === $indexingEntity->getApiKey();
            },
        );
    }

    /**
     * @param StoreInterface|null $store
     *
     * @return ProductInterface[]
     */
    private function getProducts(?StoreInterface $store = null): array
    {
        $productCollectionFactory = $this->objectManager->get(ProductCollectionFactory::class);
        $productCollection = $productCollectionFactory->create();
        $productCollection->addAttributeToSelect('*');
        if ($store) {
            $productCollection->setStore((int)$store->getId());
        }

        return $productCollection->getItems();
    }
}
