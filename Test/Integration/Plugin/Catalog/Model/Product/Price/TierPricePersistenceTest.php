<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Test\Integration\Plugin\Catalog\Model\Product\Price;

use Klevu\Configuration\Service\Provider\ScopeProviderInterface;
use Klevu\Indexing\Model\IndexingEntity;
use Klevu\Indexing\Test\Integration\Traits\IndexingEntitiesTrait;
use Klevu\IndexingApi\Model\Source\Actions;
use Klevu\IndexingApi\Service\EntityUpdateResponderServiceInterface;
use Klevu\IndexingProducts\Plugin\Catalog\Model\Product\Price\TierPricePersistencePlugin;
use Klevu\IndexingProducts\Service\EntityUpdateResponderService;
use Klevu\TestFixtures\Catalog\ProductTrait;
use Klevu\TestFixtures\Store\StoreFixturesPool;
use Klevu\TestFixtures\Store\StoreTrait;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\SetAuthKeysTrait;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\AbstractModel;
use Magento\Catalog\Model\Product\Price\TierPricePersistence;
use Magento\Catalog\Model\ResourceModel\Product\Attribute\Backend\Tierprice as TierpriceResourceModel;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\EntityManager\EntityMetadata;
use Magento\Framework\EntityManager\EntityMetadataInterface;
use Magento\Framework\EntityManager\MetadataPool as EntityMetadataPool;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Interception\PluginList\PluginList;
use Magento\Framework\ObjectManagerInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use TddWizard\Fixtures\Catalog\ProductFixturePool;

/**
 * @covers \Klevu\IndexingProducts\Plugin\Catalog\Model\Product\Price\TierPricePersistencePlugin::class
 * @method TierPricePersistencePlugin instantiateTestObject(?array $arguments = null)
 * @method TierPricePersistencePlugin instantiateTestObjectFromInterface(?array $arguments = null)
 */
class TierPricePersistenceTest extends TestCase
{
    use IndexingEntitiesTrait;
    use ObjectInstantiationTrait;
    use ProductTrait;
    use SetAuthKeysTrait;
    use StoreTrait;

    /**
     * @var ObjectManagerInterface|null
     */
    private ?ObjectManagerInterface $objectManager = null; // @phpstan-ignore-line
    /**
     * @var string|null
     */
    private ?string $pluginName = 'Klevu_IndexingProducts::TierPricePersistencePlugin';
    /**
     * @var EntityMetadata|EntityMetadataInterface|null
     */
    private EntityMetadata|EntityMetadataInterface|null $productMetadata = null;
    /**
     * @var ProductRepositoryInterface|null
     */
    private ?ProductRepositoryInterface $productRepository = null;

    /**
     * @return void
     * @throws \Exception
     */
    protected function setUp(): void
    {
        $this->implementationFqcn = TierPricePersistencePlugin::class;
        $this->objectManager = Bootstrap::getObjectManager();
        $this->storeFixturesPool = $this->objectManager->get(StoreFixturesPool::class);
        $this->productFixturePool = $this->objectManager->get(ProductFixturePool::class);
        $this->productRepository = $this->objectManager->get(ProductRepositoryInterface::class);

        $metadataPool = $this->objectManager->get(EntityMetadataPool::class);
        $this->productMetadata = $metadataPool->getMetadata(ProductInterface::class);
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
     * @magentoAppArea global
     */
    public function testPlugin_InterceptsCallsToTheField_InGlobalScope(): void
    {
        $pluginInfo = $this->getSystemConfigPluginInfo();
        $this->assertArrayHasKey($this->pluginName, $pluginInfo);
        $this->assertSame(TierPricePersistencePlugin::class, $pluginInfo[$this->pluginName]['instance']);
    }

    public function testConstruct_LogsError_WhenMetadataExceptionIsThrown(): void
    {
        $exceptionMessage = 'MetadataPool Exception';

        $mockMetadataPool = $this->getMockBuilder(EntityMetadataPool::class)
            ->disableOriginalConstructor()
            ->getMock();

        $mockMetadataPool->expects($this->once())
            ->method('getMetadata')
            ->willThrowException(new \Exception($exceptionMessage));

        $mockLogger = $this->getMockBuilder(LoggerInterface::class)
            ->getMock();
        $mockLogger->expects($this->once())
            ->method('error')
            ->with(
                'Method: {method}, Error: {message}',
                [
                    // phpcs:ignore Generic.Files.LineLength.TooLong
                    'method' => 'Klevu\IndexingProducts\Plugin\Catalog\Model\Product\Price\TierPricePersistencePlugin::__construct',
                    'message' => $exceptionMessage,
                ],
            );

        $plugin = $this->instantiateTestObject([
            'metadataPool' => $mockMetadataPool,
            'logger' => $mockLogger,
        ]);

        $mockTierPricePersistence = $this->getMockBuilder(TierPricePersistence::class)
            ->disableOriginalConstructor()
            ->getMock();

        $plugin->afterUpdate(
            subject: $mockTierPricePersistence,
            result: null, // @phpstan-ignore-line
            prices: [],
        );
    }

    /**
     * @magentoDbIsolation disabled
     * @magentoConfigFixture default/klevu/indexing/exclude_disabled_products 1
     */
    public function testDelete_UpdatesIndexingEntity(): void
    {
        $apiKey = 'klevu-js-api-key';
        $this->cleanIndexingEntities($apiKey);

        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');
        $scopeProvider = $this->objectManager->get(ScopeProviderInterface::class);
        $scopeProvider->setCurrentScope($storeFixture->get());
        $this->setAuthKeys(
            scopeProvider: $scopeProvider,
            jsApiKey: $apiKey,
            restAuthKey: 'klevu-rest-key',
        );

        $this->createProduct();
        $productFixture = $this->productFixturePool->get('test_product');
        /** @var ProductInterface&AbstractModel $product */
        $product = $productFixture->getProduct();
        $product->setTierPrice([
            [
                'price' => 20.00,
                'price_qty' => 1,
                'website_id' => $storeFixture->getWebsiteId(),
                'cust_group' => 0,
            ],
        ]);
        $this->productRepository->save($product);

        $this->cleanIndexingEntities($apiKey);

        $this->createIndexingEntity([
            IndexingEntity::TARGET_ID => (int)$product->getId(),
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::NEXT_ACTION => Actions::NO_ACTION,
            IndexingEntity::LAST_ACTION => Actions::ADD,
            IndexingEntity::IS_INDEXABLE => true,
        ]);

        $tierPricePersistence = $this->objectManager->get(TierPricePersistence::class);
        $tierPricePersistence->delete(
            ids: $this->getTierPriceIds($product, $storeFixture->get()),
        );

        $indexingEntity = $this->getIndexingEntityForEntity(
            apiKey: $apiKey,
            entity: $product,
            type: 'KLEVU_PRODUCT',
        );

        $this->assertNotNull($indexingEntity);
        $this->assertSame(expected: Actions::UPDATE, actual: $indexingEntity->getNextAction());
        $this->assertTrue(condition: $indexingEntity->getIsIndexable());

        $this->cleanIndexingEntities($apiKey);
    }

    /**
     * @magentoDbIsolation disabled
     * @magentoConfigFixture klevu_test_store_1_store klevu_configuration/auth_keys/js_api_key klevu-js-api-key
     * @magentoConfigFixture klevu_test_store_1_store klevu_configuration/auth_keys/rest_auth_key klevu-rest-auth-key
     * @magentoConfigFixture default/klevu/indexing/exclude_disabled_products 1
     */
    public function testAfterReplace_UpdatesIndexingEntity(): void
    {
        $apiKey = 'klevu-js-api-key';
        $this->cleanIndexingEntities($apiKey);

        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');
        $scopeProvider = $this->objectManager->get(ScopeProviderInterface::class);
        $scopeProvider->setCurrentScope($storeFixture->get());
        $this->setAuthKeys(
            scopeProvider: $scopeProvider,
            jsApiKey: $apiKey,
            restAuthKey: 'klevu-rest-key',
        );

        $this->createProduct();
        $productFixture = $this->productFixturePool->get('test_product');
        /** @var ProductInterface&AbstractModel $product */
        $product = $productFixture->getProduct();
        $product->setTierPrice([
            [
                'price' => 20.00,
                'price_qty' => 1,
                'website_id' => $storeFixture->getWebsiteId(),
                'cust_group' => 0,
            ],
        ]);
        $this->productRepository->save($product);

        $this->cleanIndexingEntities($apiKey);

        $this->createIndexingEntity([
            IndexingEntity::TARGET_ID => (int)$product->getId(),
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::NEXT_ACTION => Actions::NO_ACTION,
            IndexingEntity::LAST_ACTION => Actions::ADD,
            IndexingEntity::IS_INDEXABLE => true,
        ]);

        $linkField = $this->productMetadata->getLinkField();
        $tierPrices = [
            [
                $linkField => $product->getData($linkField),
                'all_groups' => 1,
                'customer_group_id' => 0,
                'qty' => 1,
                'value' => '22.00',
                'percentage_value' => null, // fixed price
                'website_id' => $storeFixture->getWebsiteId(),
            ],
        ];

        $tierPricePersistence = $this->objectManager->get(TierPricePersistence::class);
        $tierPricePersistence->replace(
            prices: $tierPrices,
            ids: [$product->getData($linkField)],
        );

        $indexingEntity = $this->getIndexingEntityForEntity(
            apiKey: $apiKey,
            entity: $product,
            type: 'KLEVU_PRODUCT',
        );

        $this->assertNotNull($indexingEntity);
        $this->assertSame(
            expected: Actions::UPDATE,
            actual: $indexingEntity->getNextAction(),
            message: 'Expected ' . Actions::UPDATE->value . ', received ' . $indexingEntity->getNextAction()->value,
        );
        $this->assertTrue(condition: $indexingEntity->getIsIndexable());

        $this->cleanIndexingEntities($apiKey);
    }

    /**
     * @magentoDbIsolation disabled
     * @magentoConfigFixture default/klevu/indexing/exclude_disabled_products 1
     */
    public function testAfterUpdate_UpdatesIndexingEntity(): void
    {
        $apiKey = 'klevu-js-api-key';
        $this->cleanIndexingEntities($apiKey);

        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');
        $scopeProvider = $this->objectManager->get(ScopeProviderInterface::class);
        $scopeProvider->setCurrentScope($storeFixture->get());
        $this->setAuthKeys(
            scopeProvider: $scopeProvider,
            jsApiKey: $apiKey,
            restAuthKey: 'klevu-rest-key',
        );

        $this->createProduct();
        $productFixture = $this->productFixturePool->get('test_product');
        /** @var ProductInterface&AbstractModel $product */
        $product = $productFixture->getProduct();
        $product->setTierPrice([
            [
                'price' => 20.00,
                'price_qty' => 1,
                'website_id' => $storeFixture->getWebsiteId(),
                'cust_group' => 0,
            ],
        ]);
        $this->productRepository->save($product);

        $this->cleanIndexingEntities($apiKey);

        $this->createIndexingEntity([
            IndexingEntity::TARGET_ID => (int)$product->getId(),
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::NEXT_ACTION => Actions::NO_ACTION,
            IndexingEntity::LAST_ACTION => Actions::ADD,
            IndexingEntity::IS_INDEXABLE => true,
        ]);

        $linkField = $this->productMetadata->getLinkField();
        /**
         * @see \Magento\Catalog\Model\Product\Price\TierPriceFactory::createSkeleton
         * @var mixed[] $tierPrices
         */
        $tierPrices = [
            [
                $linkField => $product->getData($linkField),
                'all_groups' => 1,
                'customer_group_id' => 0,
                'qty' => 1,
                'value' => '20.00',
                'percentage_value' => null, // fixed price
                'website_id' => $storeFixture->getWebsiteId(),
            ],
        ];

        $tierPricePersistence = $this->objectManager->get(TierPricePersistence::class);
        $tierPricePersistence->update($tierPrices);

        $indexingEntity = $this->getIndexingEntityForEntity(
            apiKey: $apiKey,
            entity: $product,
            type: 'KLEVU_PRODUCT',
        );

        $this->assertNotNull($indexingEntity);
        $this->assertSame(
            expected: Actions::UPDATE,
            actual: $indexingEntity->getNextAction(),
            message: 'Expected ' . Actions::UPDATE->value . ', received ' . $indexingEntity->getNextAction()->value,
        );
        $this->assertTrue(condition: $indexingEntity->getIsIndexable());

        $this->cleanIndexingEntities($apiKey);
    }

    /**
     * @magentoAppIsolation enabled
     */
    public function testErrorIsLogged_WhenExceptionIsThrown_ByResourceModelGetMainTable(): void
    {
        $exceptionMessage = 'Get Main Table Exception';

        $mockSelect = $this->getMockBuilder(Select::class)
            ->disableOriginalConstructor()
            ->getMock();

        $mockConnection = $this->getMockBuilder(AdapterInterface::class)
            ->getMock();
        $mockConnection->expects($this->once())
            ->method('select')
            ->willReturn($mockSelect);

        $mockTierPriceResourceModel = $this->getMockBuilder(TierpriceResourceModel::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockTierPriceResourceModel->method('getConnection')
            ->willReturn($mockConnection);
        $mockTierPriceResourceModel->expects($this->once())
            ->method('getMainTable')
            ->willThrowException(new LocalizedException(__($exceptionMessage)));

        $mockResponderService = $this->getMockBuilder(EntityUpdateResponderServiceInterface::class)
            ->getMock();
        $mockResponderService->expects($this->never())
            ->method('execute');

        $mockLogger = $this->getMockBuilder(LoggerInterface::class)
            ->getMock();
        $mockLogger->expects($this->once())
            ->method('error')
            ->with(
                'Method: {method}, Error: {message}',
                [
                    // phpcs:ignore Generic.Files.LineLength.TooLong
                    'method' => 'Klevu\IndexingProducts\Plugin\Catalog\Model\Product\Price\TierPricePersistencePlugin::getEntityIdsFromValueId',
                    'message' => $exceptionMessage,
                ],
            );

        $this->objectManager->addSharedInstance(
            instance: $mockTierPriceResourceModel,
            className: TierpriceResourceModel::class,
            forPreference: true,
        );
        $this->objectManager->addSharedInstance(
            instance: $mockResponderService,
            className: EntityUpdateResponderService::class,
            forPreference: true,
        );
        $this->objectManager->addSharedInstance(
            instance: $mockLogger,
            className: LoggerInterface::class,
            forPreference: true,
        );

        $tierPricePersistence = $this->objectManager->get(TierPricePersistence::class);
        $tierPricePersistence->delete(
            ids: [],
        );
    }

    /**
     * @return mixed[]|null
     */
    private function getSystemConfigPluginInfo(): ?array
    {
        /** @var PluginList $pluginList */
        $pluginList = $this->objectManager->get(PluginList::class);

        return $pluginList->get(TierPricePersistence::class, []);
    }

    /**
     * @param AbstractModel&ProductInterface $product
     * @param StoreInterface $store
     *
     * @return int[]
     */
    private function getTierPriceIds(
        AbstractModel&ProductInterface $product,
        StoreInterface $store,
    ): array {
        $tierPriceResourceModel = $this->objectManager->get(TierpriceResourceModel::class);
        $priceDataArray = $tierPriceResourceModel->loadPriceData(
            productId: $product->getData($this->productMetadata->getLinkField()),
            websiteId: $store->getWebsiteId(),
        );

        return array_merge(
            array_map(
                callback: static fn (array $priceData) => ((int)$priceData['price_id']),
                array: $priceDataArray,
            ),
        );
    }
}
