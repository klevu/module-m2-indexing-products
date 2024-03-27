<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Test\Integration\Observer;

use Klevu\Indexing\Model\IndexingEntity;
use Klevu\Indexing\Test\Integration\Traits\IndexingEntitiesTrait;
use Klevu\IndexingApi\Api\Data\IndexingEntityInterface;
use Klevu\IndexingApi\Model\Source\Actions;
use Klevu\IndexingProducts\Observer\ProductImportObserver;
use Klevu\TestFixtures\Catalog\ProductTrait;
use Klevu\TestFixtures\Store\StoreFixturesPool;
use Klevu\TestFixtures\Store\StoreTrait;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\SetAuthKeysTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\AbstractModel;
use Magento\CatalogImportExport\Model\Import\Product as ProductImport;
use Magento\Framework\Event\ConfigInterface as EventConfig;
use Magento\Framework\Event\ManagerInterface as EventManager;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use TddWizard\Fixtures\Catalog\ProductFixturePool;

/**
 * @covers \Klevu\IndexingProducts\Observer\ProductImportObserver
 * @method ObserverInterface instantiateTestObject(?array $arguments = null)
 * @method ObserverInterface instantiateTestObjectFromInterface(?array $arguments = null)
 */
class ProductImportObserverTest extends TestCase
{
    use IndexingEntitiesTrait;
    use ObjectInstantiationTrait;
    use ProductTrait;
    use SetAuthKeysTrait;
    use StoreTrait;
    use TestImplementsInterfaceTrait;

    private const OBSERVER_NAME = 'Klevu_IndexingProducts_ProductImport';
    private const EVENT_NAME = 'catalog_product_import_bunch_save_after';

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

        $this->implementationFqcn = ProductImportObserver::class;
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
            expected: ltrim(string: ProductImportObserver::class, characters: '\\'),
            actual: $observers[self::OBSERVER_NAME]['instance'],
        );
    }

    /**
     * @magentoAppArea adminhtml
     * @magentoDbIsolation disabled
     * @magentoConfigFixture klevu_test_store_1_store klevu_configuration/auth_keys/js_api_key klevu-js-api-key
     * @magentoConfigFixture klevu_test_store_1_store klevu_configuration/auth_keys/rest_auth_key klevu-rest-auth-key
     */
    public function testProductImport_ForNewProducts_CreatesIndexingEntities(): void
    {
        $apiKey = 'klevu-js-api-key';
        $this->cleanIndexingEntities($apiKey);

        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');
        $this->createProduct(
            productData: [
                'sku' => 'KLEVU_TEST_' . random_int(1, 99999999),
                'name' => 'Klevu Import Test New Product',
                'visibility' => 4,
                'type_id' => 'simple',
                'url_key' => 'klevu-import-test-product-' . random_int(1, 99999999),
            ],
            storeId: (int)$storeFixture->getId(),
        );
        $productFixture = $this->productFixturePool->get('test_product');
        /** @var ProductInterface&AbstractModel $product */
        $product = $productFixture->getProduct();

        $bunch = [
            [
                'sku' => $product->getSku(),
                'name' => $product->getName(),
                'visibility' => $product->getVisibility(),
                'product_type' => $product->getTypeId(),
                '_store' => $storeFixture->getId(),
                'url_key' => $product->getData('url_key'),
                'url_path' => $product->getData('url_path'),
                '_attribute_set' => $product->getAttributeSetId(),
                'save_rewrites_history' => false,
            ],
        ];

        /**
         * Fake that this is a new product by removing the indexing entity.
         * In reality the product would be created by the import before the event is dispatched.
         */
        $this->cleanIndexingEntities($apiKey);

        $this->dispatchEvent(
            event: self::EVENT_NAME,
            bunch: $bunch,
        );

       $indexingEntities = $this->getIndexingEntities(type: 'KLEVU_PRODUCT', apiKey: $apiKey);
       $indexingEntity = array_shift($indexingEntities);

        $this->assertInstanceOf(expected: IndexingEntityInterface::class, actual: $indexingEntity);
        $this->assertSame(
            expected: Actions::ADD,
            actual: $indexingEntity->getNextAction(),
            message:'Next Action: Add',
        );
        $this->assertTrue(condition: $indexingEntity->getIsIndexable());
        $this->cleanIndexingEntities($apiKey);
    }

    /**
     * @magentoAppArea adminhtml
     * @magentoDbIsolation disabled
     * @magentoConfigFixture klevu_test_store_1_store klevu_configuration/auth_keys/js_api_key klevu-js-api-key
     * @magentoConfigFixture klevu_test_store_1_store klevu_configuration/auth_keys/rest_auth_key klevu-rest-auth-key
     */
    public function testProductImport_ForExistingProducts_SetsIndexingEntityToUpdate(): void
    {
        $apiKey = 'klevu-js-api-key';
        $this->cleanIndexingEntities($apiKey);

        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');
        $this->createProduct(
            productData: [
                'sku' => 'KLEVU_TEST_' . random_int(1, 99999999),
                'name' => 'Klevu Import Test New Product',
                'visibility' => 4,
                'type_id' => 'simple',
                'url_key' => 'klevu-import-test-product-' . random_int(1, 99999999),
            ],
            storeId: (int)$storeFixture->getId(),
        );
        $productFixture = $this->productFixturePool->get('test_product');
        /** @var ProductInterface&AbstractModel $product */
        $product = $productFixture->getProduct();

        $bunch = [
            [
                'sku' => $product->getSku(),
                'name' => $product->getName(),
                'visibility' => $product->getVisibility(),
                'product_type' => $product->getTypeId(),
                '_store' => $storeFixture->getId(),
                'url_key' => $product->getData('url_key'),
                'url_path' => $product->getData('url_path'),
                '_attribute_set' => $product->getAttributeSetId(),
                'save_rewrites_history' => false,
            ],
        ];

        /**
         * Fake that this product has previously been synced to Klevu.
         */
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

        $this->dispatchEvent(
            event: self::EVENT_NAME,
            bunch: $bunch,
        );

        $indexingEntities = $this->getIndexingEntities(type: 'KLEVU_PRODUCT', apiKey: $apiKey);
        $indexingEntity = array_shift($indexingEntities);

        $this->assertInstanceOf(expected: IndexingEntityInterface::class, actual: $indexingEntity);
        $this->assertSame(
            expected: Actions::UPDATE,
            actual: $indexingEntity->getNextAction(),
            message:'Next Action: Update',
        );
        $this->assertTrue(condition: $indexingEntity->getIsIndexable());
        $this->cleanIndexingEntities($apiKey);
    }

    /**
     * @param string $event
     * @param mixed[] $bunch
     *
     * @return void
     */
    private function dispatchEvent(
        string $event,
        array $bunch,
    ): void {
        /** @var EventManager $eventManager */
        $eventManager = $this->objectManager->get(type: EventManager::class);
        $eventManager->dispatch(
            $event,
            [
                'adapter' => $this->objectManager->get(ProductImport::class),
                'bunch' => $bunch,
            ],
        );
    }
}
