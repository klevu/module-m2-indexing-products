<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Test\Integration\Plugin\Catalog\Model\ResourceModel\Product;

use Klevu\Indexing\Model\IndexingEntity;
use Klevu\Indexing\Test\Integration\Traits\IndexingEntitiesTrait;
use Klevu\IndexingApi\Model\Source\Actions;
use Klevu\IndexingProducts\Plugin\Catalog\Model\ResourceModel\Product\ProductLinkResourceModelPlugin;
use Klevu\TestFixtures\Catalog\ProductTrait;
use Klevu\TestFixtures\Store\StoreFixturesPool;
use Klevu\TestFixtures\Store\StoreTrait;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Magento\Catalog\Api\Data\ProductLinkInterface;
use Magento\Catalog\Api\ProductLinkRepositoryInterface;
use Magento\Catalog\Model\ResourceModel\Product\Link as ProductLinkResourceModel;
use Magento\Framework\Interception\PluginList\PluginList;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use TddWizard\Fixtures\Catalog\ProductFixturePool;

/**
 * @covers \Klevu\IndexingProducts\Plugin\Catalog\Model\ResourceModel\Product\ProductLinkResourceModelPlugin::class
 * @method ProductLinkResourceModelPlugin instantiateTestObject(?array $arguments = null)
 * @method ProductLinkResourceModelPlugin instantiateTestObjectFromInterface(?array $arguments = null)
 */
class ProductLinkResourceModelPluginTest extends TestCase
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
    private ?string $pluginName = 'Klevu_IndexingProducts::ProductLinkResourceModelPlugin';

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->implementationFqcn = ProductLinkResourceModelPlugin::class;
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
        $this->assertSame(ProductLinkResourceModelPlugin::class, $pluginInfo[$this->pluginName]['instance']);
    }

    /**
     * @magentoDbIsolation disabled
     * @magentoConfigFixture klevu_test_store_1_store klevu_configuration/auth_keys/js_api_key klevu-js-api-key
     * @magentoConfigFixture klevu_test_store_1_store klevu_configuration/auth_keys/rest_auth_key klevu-rest-auth-key
     */
    public function testAfterSaveProductLinks_UpdatesGroupedParents_NotChildren(): void
    {
        $apiKey = 'klevu-js-api-key';
        $this->cleanIndexingEntities($apiKey);

        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');
        $store = $storeFixture->get();

        $this->createProduct(
            productData: [
                'key' => 'test_simple_product_1',
                'type_id' => 'simple',
                'sku' => 'test_simple_product_1',
            ],
            storeId: (int)$store->getId(),
        );
        $simpleProduct1Fixture = $this->productFixturePool->get('test_simple_product_1');
        $this->createProduct(
            productData: [
                'key' => 'test_simple_product_2',
                'type_id' => 'simple',
                'sku' => 'test_simple_product_2',
            ],
            storeId: (int)$store->getId(),
        );
        $simpleProduct2Fixture = $this->productFixturePool->get('test_simple_product_2');
        $this->createProduct(
            productData: [
                'key' => 'test_grouped_product',
                'type_id' => 'grouped',
                'linked_products' => [
                    $simpleProduct1Fixture,
                    $simpleProduct2Fixture,
                ],
            ],
            storeId: (int)$store->getId(),
        );
        $groupedProductFixture = $this->productFixturePool->get('test_grouped_product');

        $this->cleanIndexingEntities($apiKey);

        $this->createIndexingEntity([
            IndexingEntity::TARGET_ID => $simpleProduct1Fixture->getId(),
            IndexingEntity::TARGET_ENTITY_SUBTYPE => 'simple',
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::NEXT_ACTION => Actions::NO_ACTION,
            IndexingEntity::LAST_ACTION => Actions::ADD,
            IndexingEntity::IS_INDEXABLE => true,
        ]);
        $this->createIndexingEntity([
            IndexingEntity::TARGET_ID => $simpleProduct2Fixture->getId(),
            IndexingEntity::TARGET_ENTITY_SUBTYPE => 'simple',
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::NEXT_ACTION => Actions::NO_ACTION,
            IndexingEntity::LAST_ACTION => Actions::ADD,
            IndexingEntity::IS_INDEXABLE => true,
        ]);
        $this->createIndexingEntity([
            IndexingEntity::TARGET_ID => $groupedProductFixture->getId(),
            IndexingEntity::TARGET_ENTITY_SUBTYPE => 'grouped',
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::NEXT_ACTION => Actions::NO_ACTION,
            IndexingEntity::LAST_ACTION => Actions::ADD,
            IndexingEntity::IS_INDEXABLE => true,
        ]);

        $groupedProduct = $groupedProductFixture->getProduct();
        $links = $groupedProduct->getProductLinks();
        /** @var ProductLinkInterface $link */
        $link = array_shift($links);
        $link->setPosition(3);

        $linkRepository = $this->objectManager->get(ProductLinkRepositoryInterface::class);
        $linkRepository->save($link);

        $indexingEntitySimple1 = $this->getIndexingEntityForEntity(
            apiKey: $apiKey,
            entity: $simpleProduct1Fixture->getProduct(),
            type: 'KLEVU_PRODUCT',
        );
        $this->assertSame(
            expected: Actions::NO_ACTION,
            actual: $indexingEntitySimple1->getNextAction(),
            message: 'No Action',
        );
        $this->assertTrue(condition: $indexingEntitySimple1->getIsIndexable());

        $indexingEntitySimple2 = $this->getIndexingEntityForEntity(
            apiKey: $apiKey,
            entity: $simpleProduct2Fixture->getProduct(),
            type: 'KLEVU_PRODUCT',
        );
        $this->assertSame(
            expected: Actions::NO_ACTION,
            actual: $indexingEntitySimple2->getNextAction(),
            message: 'No Action',
        );
        $this->assertTrue(condition: $indexingEntitySimple2->getIsIndexable());

        $indexingEntityGrouped = $this->getIndexingEntityForEntity(
            apiKey: $apiKey,
            entity: $groupedProduct,
            type: 'KLEVU_PRODUCT',
        );
        $this->assertSame(
            expected: Actions::UPDATE,
            actual: $indexingEntityGrouped->getNextAction(),
            message: 'Action Update',
        );
        $this->assertTrue(condition: $indexingEntityGrouped->getIsIndexable());
    }

    /**
     * @return mixed[]|null
     */
    private function getSystemConfigPluginInfo(): ?array
    {
        /** @var PluginList $pluginList */
        $pluginList = $this->objectManager->get(PluginList::class);

        return $pluginList->get(ProductLinkResourceModel::class, []);
    }
}
