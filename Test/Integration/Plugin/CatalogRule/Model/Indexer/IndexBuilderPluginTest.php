<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Test\Integration\Plugin\CatalogRule\Model\Indexer;

use Klevu\Indexing\Model\IndexingEntity;
use Klevu\Indexing\Test\Integration\Traits\IndexingEntitiesTrait;
use Klevu\IndexingApi\Model\Source\Actions;
use Klevu\IndexingProducts\Plugin\CatalogRule\Model\Indexer\IndexBuilderPlugin;
use Klevu\TestFixtures\Catalog\ProductTrait;
use Klevu\TestFixtures\Catalog\Rule\RuleFixturePool;
use Klevu\TestFixtures\Catalog\RuleTrait;
use Klevu\TestFixtures\Store\StoreFixturesPool;
use Klevu\TestFixtures\Store\StoreTrait;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Magento\CatalogRule\Model\Indexer\IndexBuilder;
use Magento\Framework\Interception\PluginList\PluginList;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use TddWizard\Fixtures\Catalog\ProductFixturePool;

class IndexBuilderPluginTest extends TestCase
{
    use IndexingEntitiesTrait;
    use ObjectInstantiationTrait;
    use ProductTrait;
    use RuleTrait;
    use StoreTrait;

    /**
     * @var ObjectManagerInterface|null
     */
    private ?ObjectManagerInterface $objectManager = null; // @phpstan-ignore-line
    /**
     * @var string|null
     */
    private ?string $pluginName = 'Klevu_IndexingProducts::CatalogRuleIndexBuilderPlugin';

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->implementationFqcn = IndexBuilderPlugin::class;
        $this->objectManager = Bootstrap::getObjectManager();
        $this->storeFixturesPool = $this->objectManager->get(StoreFixturesPool::class);
        $this->ruleFixturePool = $this->objectManager->get(RuleFixturePool::class);
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
        $this->ruleFixturePool->rollback();
        $this->storeFixturesPool->rollback();
    }

    /**
     * @magentoAppArea global
     */
    public function testPlugin_InterceptsCallsToTheField_InGlobalScope(): void
    {
        $pluginInfo = $this->getSystemConfigPluginInfo();
        $this->assertArrayHasKey($this->pluginName, $pluginInfo);
        $this->assertSame(IndexBuilderPlugin::class, $pluginInfo[$this->pluginName]['instance']);
    }

    /**
     * @magentoDbIsolation disabled
     * @magentoConfigFixture klevu_test_store_1_store klevu_configuration/auth_keys/js_api_key klevu-js-api-key
     * @magentoConfigFixture klevu_test_store_1_store klevu_configuration/auth_keys/rest_auth_key klevu-rest-auth-key
     * @magentoConfigFixture default/klevu/indexing/exclude_disabled_products 1
     */
    public function testReindexFull_UpdatesIndexingEntities(): void
    {
        $apiKey = 'klevu-js-api-key';
        $this->cleanIndexingEntities($apiKey);

        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');
        $store = $storeFixture->get();

        $sku = 'KlevuTest' . random_int(0, 9999999);
        $this->createProduct(
            productData: [
                'sku' => $sku,
            ],
            storeId: (int)$store->getId(),
        );
        $productFixture = $this->productFixturePool->get('test_product');

        $this->createRule([
            'conditions' => [
                [
                    'attribute' => 'sku',
                    'value' => $sku,
                ],
            ],
        ]);

        $this->cleanIndexingEntities($apiKey);

        $this->createIndexingEntity([
            IndexingEntity::TARGET_ID => $productFixture->getId(),
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::NEXT_ACTION => Actions::NO_ACTION,
            IndexingEntity::IS_INDEXABLE => true,
        ]);

        $indexBuilder = $this->objectManager->get(IndexBuilder::class);
        $indexBuilder->reindexFull();

        $indexingEntity = $this->getIndexingEntityForEntity(
            apiKey: $apiKey,
            entity: $productFixture->getProduct(),
            type: 'KLEVU_PRODUCT',
        );
        $this->assertNotNull($indexingEntity);
        $this->assertSame(expected: Actions::UPDATE, actual: $indexingEntity->getNextAction());
        $this->assertTrue(condition: $indexingEntity->getIsIndexable());
    }

    /**
     * @magentoDbIsolation disabled
     * @magentoConfigFixture klevu_test_store_1_store klevu_configuration/auth_keys/js_api_key klevu-js-api-key
     * @magentoConfigFixture klevu_test_store_1_store klevu_configuration/auth_keys/rest_auth_key klevu-rest-auth-key
     * @magentoConfigFixture default/klevu/indexing/exclude_disabled_products 1
     */
    public function testReindexById_UpdatesIndexingEntities(): void
    {
        $apiKey = 'klevu-js-api-key';
        $this->cleanIndexingEntities($apiKey);

        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');
        $store = $storeFixture->get();

        $sku = 'KlevuTest' . random_int(0, 9999999);
        $this->createProduct(
            productData: [
                'sku' => $sku,
            ],
            storeId: (int)$store->getId(),
        );
        $productFixture = $this->productFixturePool->get('test_product');

        $this->createRule([
            'conditions' => [
                [
                    'attribute' => 'sku',
                    'value' => $sku,
                ],
            ],
        ]);

        $this->cleanIndexingEntities($apiKey);

        $this->createIndexingEntity([
            IndexingEntity::TARGET_ID => $productFixture->getId(),
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::NEXT_ACTION => Actions::NO_ACTION,
            IndexingEntity::IS_INDEXABLE => true,
        ]);

        $indexBuilder = $this->objectManager->get(IndexBuilder::class);
        $indexBuilder->reindexById((string)$productFixture->getId());

        $indexingEntity = $this->getIndexingEntityForEntity(
            apiKey: $apiKey,
            entity: $productFixture->getProduct(),
            type: 'KLEVU_PRODUCT',
        );
        $this->assertNotNull($indexingEntity);
        $this->assertSame(expected: Actions::UPDATE, actual: $indexingEntity->getNextAction());
        $this->assertTrue(condition: $indexingEntity->getIsIndexable());
    }

    /**
     * @magentoDbIsolation disabled
     * @magentoConfigFixture klevu_test_store_1_store klevu_configuration/auth_keys/js_api_key klevu-js-api-key
     * @magentoConfigFixture klevu_test_store_1_store klevu_configuration/auth_keys/rest_auth_key klevu-rest-auth-key
     * @magentoConfigFixture default/klevu/indexing/exclude_disabled_products 1
     */
    public function testReindexByIds_UpdatesIndexingEntities(): void
    {
        $apiKey = 'klevu-js-api-key';
        $this->cleanIndexingEntities($apiKey);

        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');
        $store = $storeFixture->get();

        $sku = 'KlevuTest' . random_int(0, 9999999);
        $this->createProduct(
            productData: [
                'sku' => $sku,
            ],
            storeId: (int)$store->getId(),
        );
        $productFixture = $this->productFixturePool->get('test_product');

        $this->createRule([
            'conditions' => [
                [
                    'attribute' => 'sku',
                    'value' => $sku,
                ],
            ],
        ]);

        $this->cleanIndexingEntities($apiKey);

        $this->createIndexingEntity([
            IndexingEntity::TARGET_ID => $productFixture->getId(),
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::NEXT_ACTION => Actions::NO_ACTION,
            IndexingEntity::IS_INDEXABLE => true,
        ]);

        $indexBuilder = $this->objectManager->get(IndexBuilder::class);
        $indexBuilder->reindexByIds([(string)$productFixture->getId()]);

        $indexingEntity = $this->getIndexingEntityForEntity(
            apiKey: $apiKey,
            entity: $productFixture->getProduct(),
            type: 'KLEVU_PRODUCT',
        );
        $this->assertNotNull($indexingEntity);
        $this->assertSame(expected: Actions::UPDATE, actual: $indexingEntity->getNextAction());
        $this->assertTrue(condition: $indexingEntity->getIsIndexable());
    }

    /**
     * @return mixed[]|null
     */
    private function getSystemConfigPluginInfo(): ?array
    {
        /** @var PluginList $pluginList */
        $pluginList = $this->objectManager->get(PluginList::class);

        return $pluginList->get(IndexBuilder::class, []);
    }
}
