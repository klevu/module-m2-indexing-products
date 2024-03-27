<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Test\Integration\Plugin\Catalog\Model\ResourceModel\Product\Price;

use Klevu\Indexing\Model\IndexingEntity;
use Klevu\Indexing\Test\Integration\Traits\IndexingEntitiesTrait;
use Klevu\IndexingApi\Model\Source\Actions;
use Klevu\IndexingProducts\Plugin\Catalog\Model\ResourceModel\Product\Price\SpecialPricePlugin;
use Klevu\TestFixtures\Catalog\ProductTrait;
use Klevu\TestFixtures\Store\StoreFixturesPool;
use Klevu\TestFixtures\Store\StoreTrait;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Magento\Catalog\Api\Data\SpecialPriceInterfaceFactory;
use Magento\Catalog\Model\ResourceModel\Product\Price\SpecialPrice;
use Magento\Framework\Interception\PluginList\PluginList;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use TddWizard\Fixtures\Catalog\ProductFixturePool;

/**
 * @covers \Klevu\IndexingProducts\Plugin\Catalog\Model\ResourceModel\Product\Price\SpecialPricePlugin::class
 * @method SpecialPricePlugin instantiateTestObject(?array $arguments = null)
 * @method SpecialPricePlugin instantiateTestObjectFromInterface(?array $arguments = null)
 */
class SpecialPricePluginTest extends TestCase
{
    use IndexingEntitiesTrait;
    use ObjectInstantiationTrait;
    use ProductTrait;
    use StoreTrait;

    /**
     * @var ObjectManagerInterface|null
     */
    private ?ObjectManagerInterface $objectManager = null; // @phpstan-ignore-line
    /**
     * @var string|null
     */
    private ?string $pluginName = 'Klevu_IndexingProducts::SpecialPricePlugin';

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->implementationFqcn = SpecialPricePlugin::class;
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
        $this->assertSame(SpecialPricePlugin::class, $pluginInfo[$this->pluginName]['instance']);
    }

    /**
     * @magentoDbIsolation disabled
     * @magentoConfigFixture klevu_test_store_1_store klevu_configuration/auth_keys/js_api_key klevu-js-api-key
     * @magentoConfigFixture klevu_test_store_1_store klevu_configuration/auth_keys/rest_auth_key klevu-rest-auth-key
     * @magentoConfigFixture default/klevu/indexing/exclude_disabled_products 1
     */
    public function testUpdateSpecialPrice_UpdatesInedxingEntities(): void
    {
        $apiKey = 'klevu-js-api-key';
        $this->cleanIndexingEntities($apiKey);

        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');
        $store = $storeFixture->get();

        $this->createProduct(storeId: (int)$store->getId());
        $productFixture = $this->productFixturePool->get('test_product');

        $this->cleanIndexingEntities($apiKey);

        $this->createIndexingEntity([
            IndexingEntity::TARGET_ID => $productFixture->getId(),
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::NEXT_ACTION => Actions::NO_ACTION,
            IndexingEntity::LAST_ACTION => Actions::ADD,
            IndexingEntity::IS_INDEXABLE => true,
        ]);

        $specialPriceFactory = $this->objectManager->get(SpecialPriceInterfaceFactory::class);

        $specialPrice = $specialPriceFactory->create();
        $specialPrice->setSku($productFixture->getSku());
        $specialPrice->setStoreId($store->getId());
        $specialPrice->setPrice(random_int(1,100));
        $specialPrice->setPriceFrom(date('Y-m-d H:i:s', time() - (3600 * 24)));
        $specialPrice->setPriceTo(date('Y-m-d H:i:s', time() + (3600 * 24)));

        $specialPriceResourceModel = $this->objectManager->get(SpecialPrice::class);
        $specialPriceResourceModel->update([$specialPrice]);

        $indexingEntity = $this->getIndexingEntityForEntity(
            apiKey: $apiKey,
            entity: $productFixture->getProduct(),
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
    public function testDeleteSpecialPrice_UpdatesInedxingEntities(): void
    {
        $apiKey = 'klevu-js-api-key';
        $this->cleanIndexingEntities($apiKey);

        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');
        $store = $storeFixture->get();

        $this->createProduct(storeId: (int)$store->getId());
        $productFixture = $this->productFixturePool->get('test_product');

        $this->cleanIndexingEntities($apiKey);

        $this->createIndexingEntity([
            IndexingEntity::TARGET_ID => $productFixture->getId(),
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::NEXT_ACTION => Actions::NO_ACTION,
            IndexingEntity::LAST_ACTION => Actions::ADD,
            IndexingEntity::IS_INDEXABLE => true,
        ]);

        $specialPriceFactory = $this->objectManager->get(SpecialPriceInterfaceFactory::class);

        $specialPrice = $specialPriceFactory->create();
        $specialPrice->setSku($productFixture->getSku());
        $specialPrice->setStoreId($store->getId());
        $specialPrice->setPrice(random_int(1,100));
        $specialPrice->setPriceFrom(date('Y-m-d H:i:s', time() - (3600 * 24)));
        $specialPrice->setPriceTo(date('Y-m-d H:i:s', time() + (3600 * 24)));

        $specialPriceResourceModel = $this->objectManager->get(SpecialPrice::class);
        $specialPriceResourceModel->delete([$specialPrice]);

        $indexingEntity = $this->getIndexingEntityForEntity(
            apiKey: $apiKey,
            entity: $productFixture->getProduct(),
            type: 'KLEVU_PRODUCT',
        );

        $this->assertNotNull($indexingEntity);
        $this->assertSame(expected: Actions::UPDATE, actual: $indexingEntity->getNextAction());
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

        return $pluginList->get(SpecialPrice::class, []);
    }
}
