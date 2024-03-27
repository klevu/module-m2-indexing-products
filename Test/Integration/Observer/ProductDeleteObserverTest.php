<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Test\Integration\Observer;

use Klevu\Indexing\Model\IndexingEntity;
use Klevu\Indexing\Model\ResourceModel\IndexingEntity\Collection;
use Klevu\Indexing\Test\Integration\Traits\IndexingEntitiesTrait;
use Klevu\IndexingApi\Api\Data\IndexingEntityInterface;
use Klevu\IndexingApi\Model\Source\Actions;
use Klevu\IndexingApi\Service\EntityUpdateResponderServiceInterface;
use Klevu\IndexingProducts\Observer\ProductDeleteObserver;
use Klevu\TestFixtures\Catalog\ProductTrait;
use Klevu\TestFixtures\Store\StoreFixturesPool;
use Klevu\TestFixtures\Store\StoreTrait;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\SetAuthKeysTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Magento\Catalog\Api\Data\CategoryInterface;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\ResourceModel\Product as ProductResourceModel;
use Magento\Framework\DataObject;
use Magento\Framework\Event\ConfigInterface as EventConfig;
use Magento\Framework\Event\ManagerInterface as EventManager;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use TddWizard\Fixtures\Catalog\ProductFixturePool;

/**
 * @covers \Klevu\IndexingProducts\Observer\ProductDeleteObserver
 * @method ObserverInterface instantiateTestObject(?array $arguments = null)
 * @method ObserverInterface instantiateTestObjectFromInterface(?array $arguments = null)
 */
class ProductDeleteObserverTest extends TestCase
{
    use IndexingEntitiesTrait;
    use ObjectInstantiationTrait;
    use ProductTrait;
    use SetAuthKeysTrait;
    use StoreTrait;
    use TestImplementsInterfaceTrait;

    private const OBSERVER_NAME = 'Klevu_IndexingProducts_ProductDelete';
    private const EVENT_NAME = 'catalog_product_delete_after_done';

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

        $this->implementationFqcn = ProductDeleteObserver::class;
        $this->interfaceFqcn = ObserverInterface::class;
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

    public function testInvalidateCustomerDataObserver_IsConfigured(): void
    {
        $observerConfig = $this->objectManager->create(type: EventConfig::class);
        $observers = $observerConfig->getObservers(eventName: self::EVENT_NAME);

        $this->assertArrayHasKey(key: self::OBSERVER_NAME, array: $observers);
        $this->assertSame(
            expected: ltrim(string: ProductDeleteObserver::class, characters: '\\'),
            actual: $observers[self::OBSERVER_NAME]['instance'],
        );
    }

    /**
     * @magentoAppArea adminhtml
     * @magentoDbIsolation disabled
     * @magentoConfigFixture klevu_test_store_1_store klevu_configuration/auth_keys/js_api_key klevu-js-api-key
     * @magentoConfigFixture klevu_test_store_1_store klevu_configuration/auth_keys/rest_auth_key klevu-rest-auth-key
     */
    public function testDeletedProduct_newIndexingEntityIsSetToNotIndexable(): void
    {
        $apiKey = 'klevu-js-api-key';
        $this->cleanIndexingEntities($apiKey);

        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');

        $this->createProduct(
            productData: [
                'key' => 'test_product',
            ],
            storeId: (int)$storeFixture->getId(),
        );
        $productFixture = $this->productFixturePool->get('test_product');

        /** @var ProductInterface&DataObject $product */
        $product = $productFixture->getProduct();
        $productResourceModel = $this->objectManager->get(ProductResourceModel::class);
        $productResourceModel->delete($product);

        $collection = $this->objectManager->create(Collection::class);
        $collection->addFieldToFilter(IndexingEntity::TARGET_ID, ['eq' => $product->getId()]);
        $collection->addFieldToFilter(IndexingEntity::API_KEY, ['eq' => $apiKey]);
        $collection->addFieldToFilter(IndexingEntity::TARGET_ENTITY_TYPE, ['eq' => 'KLEVU_PRODUCT']);
        $indexingEntities = $collection->getItems();

        /** @var IndexingEntityInterface $indexingEntity */
        $indexingEntity = array_shift($indexingEntities);
        $this->assertInstanceOf(expected: IndexingEntityInterface::class, actual: $indexingEntity);
        $this->assertSame(
            expected: Actions::NO_ACTION,
            actual: $indexingEntity->getNextAction(),
            message:'Next Action: No Action',
        );
        $this->assertFalse(condition: $indexingEntity->getIsIndexable());
        $this->cleanIndexingEntities($apiKey);
    }

    /**
     * @magentoAppArea adminhtml
     * @magentoDbIsolation disabled
     * @magentoConfigFixture klevu_test_store_1_store klevu_configuration/auth_keys/js_api_key klevu-js-api-key
     * @magentoConfigFixture klevu_test_store_1_store klevu_configuration/auth_keys/rest_auth_key klevu-rest-auth-key
     */
    public function testDeletedProduct_IndexingEntityIsSetToDelete(): void
    {
        $apiKey = 'klevu-js-api-key';
        $this->cleanIndexingEntities($apiKey);

        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');

        $this->createProduct(
            productData: [
                'key' => 'test_product',
            ],
            storeId: (int)$storeFixture->getId(),
        );
        $productFixture = $this->productFixturePool->get('test_product');

        $this->cleanIndexingEntities($apiKey);

        $this->createIndexingEntity([
            IndexingEntity::TARGET_ID => $productFixture->getId(),
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::NEXT_ACTION => Actions::NO_ACTION,
            IndexingEntity::LAST_ACTION => Actions::ADD,
            IndexingEntity::LAST_ACTION_TIMESTAMP => date('Y-m-d H:i:s'),
            IndexingEntity::IS_INDEXABLE => true,
        ]);

        /** @var ProductInterface&DataObject $product */
        $product = $productFixture->getProduct();
        $productResourceModel = $this->objectManager->get(ProductResourceModel::class);
        $productResourceModel->delete($product);

        $collection = $this->objectManager->create(Collection::class);
        $collection->addFieldToFilter(IndexingEntity::TARGET_ID, ['eq' => $product->getId()]);
        $collection->addFieldToFilter(IndexingEntity::API_KEY, ['eq' => $apiKey]);
        $collection->addFieldToFilter(IndexingEntity::TARGET_ENTITY_TYPE, ['eq' => 'KLEVU_PRODUCT']);
        $collection->addFieldToFilter(IndexingEntity::TARGET_PARENT_ID, ['null' => null]);
        $indexingEntities = $collection->getItems();

        /** @var IndexingEntityInterface $indexingEntity */
        $indexingEntity = array_shift($indexingEntities);
        $this->assertInstanceOf(expected: IndexingEntityInterface::class, actual: $indexingEntity);
        $this->assertSame(
            expected: Actions::DELETE,
            actual: $indexingEntity->getNextAction(),
            message:'Next Action: Delete',
        );
        $this->assertTrue(condition: $indexingEntity->getIsIndexable());
        $this->cleanIndexingEntities($apiKey);
    }

    public function testResponderServiceNotCalled_ForNonCategoryInterface(): void
    {
        $mockResponderService = $this->getMockBuilder(EntityUpdateResponderServiceInterface::class)
            ->getMock();
        $mockResponderService->expects($this->never())
            ->method('execute');

        $cmsPageDeleteObserver = $this->objectManager->create(ProductDeleteObserver::class, [
            'responderService' => $mockResponderService,
        ]);
        $this->objectManager->addSharedInstance(
            instance: $cmsPageDeleteObserver,
            className: ProductDeleteObserver::class,
        );

        $category = $this->objectManager->get(CategoryInterface::class);
        $this->dispatchEvent(
            event: self::EVENT_NAME,
            entity: $category,
        );
    }

    /**
     * @param string $event
     * @param mixed $entity
     *
     * @return void
     */
    private function dispatchEvent(
        string $event,
        mixed $entity,
    ): void {
        /** @var EventManager $eventManager */
        $eventManager = $this->objectManager->get(type: EventManager::class);
        $eventManager->dispatch(
            $event,
            [
                'entity' => $entity,
            ],
        );
    }
}
