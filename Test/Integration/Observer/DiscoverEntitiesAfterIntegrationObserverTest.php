<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Test\Integration\Observer;

use Klevu\Indexing\Model\IndexingEntity;
use Klevu\Indexing\Model\ResourceModel\IndexingEntity\Collection as IndexingEntityCollection;
use Klevu\Indexing\Observer\DiscoverEntitiesAfterIntegrationObserver;
use Klevu\Indexing\Test\Integration\Traits\IndexingEntitiesTrait;
use Klevu\IndexingApi\Api\Data\IndexingEntityInterface;
use Klevu\IndexingApi\Model\Source\Actions;
use Klevu\IndexingApi\Service\EntityDiscoveryOrchestratorServiceInterface;
use Klevu\TestFixtures\Catalog\ProductTrait;
use Klevu\TestFixtures\Store\StoreFixturesPool;
use Klevu\TestFixtures\Store\StoreTrait;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Klevu\TestFixtures\Website\WebsiteFixturesPool;
use Klevu\TestFixtures\Website\WebsiteTrait;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Type;
use Magento\Downloadable\Model\Product\Type as DownloadableType;
use Magento\Framework\Event\ManagerInterface as EventManager;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use TddWizard\Fixtures\Catalog\ProductFixture;
use TddWizard\Fixtures\Catalog\ProductFixturePool;

/**
 * @covers \Klevu\Indexing\Observer\DiscoverEntitiesAfterIntegrationObserver::class
 */
class DiscoverEntitiesAfterIntegrationObserverTest extends TestCase
{
    use IndexingEntitiesTrait;
    use ObjectInstantiationTrait;
    use ProductTrait;
    use StoreTrait;
    use TestImplementsInterfaceTrait;
    use WebsiteTrait;

    private const EVENT_NAME = 'klevu_integrate_api_keys_after';
    private const ENTITY_TYPE = 'KLEVU_PRODUCT';

    /**
     * @var ObjectManagerInterface|null
     */
    private ?ObjectManagerInterface $objectManager = null;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->implementationFqcn = DiscoverEntitiesAfterIntegrationObserver::class;
        $this->interfaceFqcn = ObserverInterface::class;
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
    public function testEventObserver_DoesNotCallOrchestrator_WhenApiKeyMissing(): void
    {
        $mockDiscoveryOrchestrator = $this->getMockBuilder(EntityDiscoveryOrchestratorServiceInterface::class)
            ->getMock();
        $mockDiscoveryOrchestrator->expects($this->never())
            ->method('execute');

        $this->objectManager->addSharedInstance(
            instance: $mockDiscoveryOrchestrator,
            className: EntityDiscoveryOrchestratorServiceInterface::class,
            forPreference: true,
        );

        $this->dispatchEvent();
    }

    /**
     * @magentoDbIsolation disabled
     * @magentoAppIsolation enabled
     * @magentoConfigFixture klevu_test_store_1_store klevu_configuration/auth_keys/js_api_key klevu-js-api-key-1
     * @magentoConfigFixture klevu_test_store_1_store klevu_configuration/auth_keys/rest_auth_key klevu-rest-auth-key-1
     * @magentoConfigFixture klevu_test_store_2_store klevu_configuration/auth_keys/js_api_key klevu-js-api-key-2
     * @magentoConfigFixture klevu_test_store_2_store klevu_configuration/auth_keys/rest_auth_key klevu-rest-auth-key-2
     * @magentoConfigFixture default/klevu/indexing/exclude_disabled_products 1
     * @magentoConfigFixture default/klevu/indexing/exclude_oos_products 0
     */
    public function testEventObserver_CreatesIndexingEntities(): void
    {
        $apiKey1 = 'klevu-js-api-key-1';
        $apiKey2 = 'klevu-js-api-key-2';

        $indexingEntityCollection = $this->objectManager->create(IndexingEntityCollection::class);
        $indexingEntityCollection->addFieldToFilter(IndexingEntity::API_KEY, ['in' => [$apiKey1, $apiKey2]]);
        $collectionSize = count($indexingEntityCollection->getItems());

        $this->createWebsite();
        $websiteFixture = $this->websiteFixturesPool->get('test_website');

        $this->createStore([
            'code' => 'klevu_test_store_1',
            'key' => 'test_store_1',
        ]);
        $storeFixture = $this->storeFixturesPool->get('test_store_1');
        $store1 = $storeFixture->get();

        $this->createStore([
            'code' => 'klevu_test_store_2',
            'key' => 'test_store_2',
            'website_id' => $websiteFixture->getId(),
        ]);
        $storeFixture = $this->storeFixturesPool->get('test_store_2');
        $store2 = $storeFixture->get();

        $this->createProduct(
            productData: [
                'in_stock' => true,
                'qty' => 123,
                'status' => Status::STATUS_ENABLED,
                'website_ids' => [
                    (int)$store1->getWebsiteId(),
                    (int)$store2->getWebsiteId(),
                ],
                'type_id' => DownloadableType::TYPE_DOWNLOADABLE,
                'key' => 'test_product_1',
                'stores' => [
                    (int)$store1->getId() => [
                        'status' => Status::STATUS_ENABLED,
                    ],
                    (int)$store2->getId() => [
                        'status' => Status::STATUS_DISABLED,
                    ],
                ],
            ],
        );
        $this->createProduct(
            productData: [
                'in_stock' => true,
                'qty' => 123,
                'status' => Status::STATUS_ENABLED,
                'website_ids' => [
                    (int)$store1->getWebsiteId(),
                    (int)$store2->getWebsiteId(),
                ],
                'type_id' => Type::TYPE_VIRTUAL,
                'key' => 'test_product_2',
                'stores' => [
                    (int)$store1->getId() => [
                        'status' => Status::STATUS_DISABLED,
                    ],
                    (int)$store2->getId() => [
                        'status' => Status::STATUS_DISABLED,
                    ],
                ],
            ],
        );
        $this->createProduct(
            productData: [
                'in_stock' => true,
                'qty' => 123,
                'status' => Status::STATUS_ENABLED,
                'website_ids' => [
                    (int)$store1->getWebsiteId(),
                    (int)$store2->getWebsiteId(),
                ],
                'type_id' => Type::DEFAULT_TYPE,
                'key' => 'test_product_3',
                'stores' => [
                    (int)$store1->getId() => [
                        'status' => Status::STATUS_ENABLED,
                    ],
                    (int)$store2->getId() => [
                        'status' => Status::STATUS_DISABLED,
                    ],
                ],
            ],
        );

        // remove entities created by product creation, let discovery observer find them
        $this->cleanIndexingEntities($apiKey1);
        $this->cleanIndexingEntities($apiKey2);

        $this->dispatchEvent(
            apiKey: $apiKey1,
        );

        $indexingEntityCollection = $this->objectManager->create(IndexingEntityCollection::class);
        $indexingEntityCollection->addFieldToFilter(IndexingEntity::API_KEY, ['in' => [$apiKey1, $apiKey2]]);
        $indexingEntities = $indexingEntityCollection->getItems();
        $this->assertGreaterThanOrEqual(
            expected: 3 + $collectionSize,
            actual: count($indexingEntities),
            message: 'Final Items Count',
        );
        $product1 = $this->productFixturePool->get('test_product_1');
        $this->assertAddIndexingEntity($indexingEntities, $product1, $apiKey1, true);
        $product2 = $this->productFixturePool->get('test_product_2');
        $this->assertAddIndexingEntity($indexingEntities, $product2, $apiKey1, false);
        $product3 = $this->productFixturePool->get('test_product_3');
        $this->assertAddIndexingEntity($indexingEntities, $product3, $apiKey1, true);

        $this->cleanIndexingEntities($apiKey1);
        $this->cleanIndexingEntities($apiKey2);
    }

    /**
     * @param string|null $apiKey
     *
     * @return void
     */
    private function dispatchEvent(
        ?string $apiKey = null,
    ): void {
        /** @var EventManager $eventManager */
        $eventManager = $this->objectManager->get(type: EventManager::class);
        $eventManager->dispatch(
            self::EVENT_NAME,
            [
                'apiKey' => $apiKey,
            ],
        );
    }

    /**
     * @param IndexingEntityInterface[] $indexingEntities
     * @param ProductFixture $product
     * @param string $apiKey
     * @param bool $isIndexable
     *
     * @return void
     */
    private function assertAddIndexingEntity(
        array $indexingEntities,
        ProductFixture $product,
        string $apiKey,
        bool $isIndexable,
    ): void {
        $indexingEntityArray = $this->filterIndexEntities($indexingEntities, $product->getId(), $apiKey);
        $indexingEntity = array_shift($indexingEntityArray);
        $this->assertInstanceOf(
            expected: IndexingEntityInterface::class,
            actual: $indexingEntity,
            message: 'Indexing Entity not Found',
        );
        $this->assertSame(
            expected: (int)$product->getId(),
            actual: $indexingEntity->getTargetId(),
            message: 'Target Id',
        );
        $this->assertSame(
            expected: self::ENTITY_TYPE,
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
                    && $apiKey === $indexingEntity->getApiKey()
                    && self::ENTITY_TYPE === $indexingEntity->getTargetEntityType();
            },
        );
    }
}
