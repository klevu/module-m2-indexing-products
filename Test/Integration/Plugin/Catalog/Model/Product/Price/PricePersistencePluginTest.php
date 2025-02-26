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
use Klevu\IndexingProducts\Plugin\Catalog\Model\Product\Price\PricePersistencePlugin;
use Klevu\TestFixtures\Catalog\ProductTrait;
use Klevu\TestFixtures\Store\StoreFixturesPool;
use Klevu\TestFixtures\Store\StoreTrait;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\SetAuthKeysTrait;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\AbstractModel;
use Magento\Catalog\Model\Product\Price\PricePersistence;
use Magento\Framework\EntityManager\MetadataPool as EntityMetadataPool;
use Magento\Framework\Interception\PluginList\PluginList;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use TddWizard\Fixtures\Catalog\ProductFixturePool;

/**
 * @covers \Klevu\IndexingProducts\Plugin\Catalog\Model\Product\Price\PricePersistencePlugin::class
 * @method PricePersistencePlugin instantiateTestObject(?array $arguments = null)
 * @method PricePersistencePlugin instantiateTestObjectFromInterface(?array $arguments = null)
 */
class PricePersistencePluginTest extends TestCase
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
    private ?string $pluginName = 'Klevu_IndexingProducts::PricePersistencePlugin';

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->implementationFqcn = PricePersistencePlugin::class;
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
     * @magentoAppArea global
     */
    public function testPlugin_InterceptsCallsToTheField_InGlobalScope(): void
    {
        $pluginInfo = $this->getSystemConfigPluginInfo();
        $this->assertArrayHasKey($this->pluginName, $pluginInfo);
        $this->assertSame(PricePersistencePlugin::class, $pluginInfo[$this->pluginName]['instance']);
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
                    'method' => 'Klevu\IndexingProducts\Plugin\Catalog\Model\Product\Price\PricePersistencePlugin::__construct',
                    'message' => $exceptionMessage,
                ],
            );

        $plugin = $this->instantiateTestObject([
            'metadataPool' => $mockMetadataPool,
            'logger' => $mockLogger,
        ]);

        $mockPricePersistence = $this->getMockBuilder(PricePersistence::class)
            ->disableOriginalConstructor()
            ->getMock();

        $plugin->afterUpdate(
            subject: $mockPricePersistence,
            result: null, // @phpstan-ignore-line
            prices: [],
        );
    }

    /**
     * @magentoDbIsolation disabled
     * @magentoConfigFixture default/klevu/indexing/exclude_disabled_products 1
     */
    public function testUpdateBasePrice_UpdatesIndexingEntity(): void
    {
        $apiKey = 'klevu-js-api-key';

        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');
        $scopeProvider = $this->objectManager->get(ScopeProviderInterface::class);
        $scopeProvider->setCurrentScope($storeFixture->get());
        $this->setAuthKeys(
            scopeProvider: $scopeProvider,
            jsApiKey: $apiKey,
            restAuthKey: 'klevu-rest-key',
        );

        $this->createProduct(productData: [
            'price' => 12.50,
        ]);
        $productFixture = $this->productFixturePool->get('test_product');
        /** @var ProductInterface&AbstractModel $product */
        $product = $productFixture->getProduct();

        $this->cleanIndexingEntities($apiKey);

        $this->createIndexingEntity([
            IndexingEntity::TARGET_ID => (int)$product->getId(),
            IndexingEntity::TARGET_ENTITY_SUBTYPE => 'simple',
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::NEXT_ACTION => Actions::NO_ACTION,
            IndexingEntity::LAST_ACTION => Actions::ADD,
            IndexingEntity::IS_INDEXABLE => true,
        ]);

        $metadataPool = $this->objectManager->get(EntityMetadataPool::class);
        $productMetadata = $metadataPool->getMetadata(ProductInterface::class);
        $linkField = $productMetadata->getLinkField();

        $prices = [
            [
                'store_id' => 0,
                $linkField => $product->getData($linkField),
                'value' => 15.00,
            ],
        ];

        $pricePersistence = $this->objectManager->create(PricePersistence::class, [
            'attributeCode' => 'price',
        ]);
        $pricePersistence->update($prices);

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
     * @magentoDbIsolation disabled
     * @magentoConfigFixture default/klevu/indexing/exclude_disabled_products 1
     */
    public function testNewStoreScopeBasePrice_UpdatesIndexingEntity(): void
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

        $this->createProduct(productData: [
            'price' => 12.50,
        ]);
        $productFixture = $this->productFixturePool->get('test_product');
        /** @var ProductInterface&AbstractModel $product */
        $product = $productFixture->getProduct();

        $this->cleanIndexingEntities($apiKey);

        $this->createIndexingEntity([
            IndexingEntity::TARGET_ID => (int)$product->getId(),
            IndexingEntity::TARGET_ENTITY_SUBTYPE => 'simple',
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::NEXT_ACTION => Actions::NO_ACTION,
            IndexingEntity::LAST_ACTION => Actions::ADD,
            IndexingEntity::IS_INDEXABLE => true,
        ]);

        $metadataPool = $this->objectManager->get(EntityMetadataPool::class);
        $productMetadata = $metadataPool->getMetadata(ProductInterface::class);
        $linkField = $productMetadata->getLinkField();

        $prices = [
            [
                'store_id' => $storeFixture->getId(),
                $linkField => $product->getData($linkField),
                'value' => 15.00,
            ],
        ];

        $pricePersistence = $this->objectManager->create(PricePersistence::class, [
            'attributeCode' => 'price',
        ]);
        $pricePersistence->update($prices);

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
     * @return mixed[]|null
     */
    private function getSystemConfigPluginInfo(): ?array
    {
        /** @var PluginList $pluginList */
        $pluginList = $this->objectManager->get(PluginList::class);

        return $pluginList->get(PricePersistence::class, []);
    }
}
