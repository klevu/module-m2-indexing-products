<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Test\Integration\Plugin\Catalog\Model\ResourceModel;

use Klevu\Indexing\Model\IndexingEntity;
use Klevu\Indexing\Test\Integration\Traits\IndexingEntitiesTrait;
use Klevu\IndexingApi\Model\Source\Actions;
use Klevu\IndexingProducts\Plugin\Catalog\Model\ResourceModel\CategoryResourceModelChangedProductIdsPlugin;
use Klevu\TestFixtures\Catalog\CategoryTrait;
use Klevu\TestFixtures\Catalog\ProductTrait;
use Klevu\TestFixtures\Store\StoreFixturesPool;
use Klevu\TestFixtures\Store\StoreTrait;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ResourceModel\Category as CategoryResourceModel;
use Magento\Framework\Interception\PluginList\PluginList;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use TddWizard\Fixtures\Catalog\CategoryFixturePool;
use TddWizard\Fixtures\Catalog\ProductFixturePool;

/**
 * @covers CategoryResourceModelChangedProductIdsPlugin
 * @method CategoryResourceModelChangedProductIdsPlugin instantiateTestObject(?array $arguments = null)
 * @method CategoryResourceModelChangedProductIdsPlugin instantiateTestObjectFromInterface(?array $arguments = null)
 */
class CategoryResourceModelChangedProductIdsPluginTest extends TestCase
{
    use CategoryTrait;
    use IndexingEntitiesTrait;
    use ObjectInstantiationTrait;
    use StoreTrait;
    use ProductTrait;

    /**
     * @var ObjectManagerInterface|null
     */
    private ?ObjectManagerInterface $objectManager = null; // @phpstan-ignore-line
    /**
     * @var string|null
     */
    private ?string $pluginName = 'Klevu_IndexingProducts::CategoryResourceModelChangedProductIdsPlugin';

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        // @phpstan-ignore-next-line
        $this->implementationFqcn = CategoryResourceModelChangedProductIdsPlugin::class;
        $this->objectManager = Bootstrap::getObjectManager();
        $this->storeFixturesPool = $this->objectManager->get(StoreFixturesPool::class);
        $this->categoryFixturePool = $this->objectManager->get(CategoryFixturePool::class);
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
        $this->categoryFixturePool->rollback();
        $this->storeFixturesPool->rollback();
    }

    /**
     * @magentoAppArea global
     */
    public function testPlugin_InterceptsCallsToTheField_InGlobalScope(): void
    {
        $pluginInfo = $this->getSystemConfigPluginInfo();
        $this->assertArrayHasKey($this->pluginName, $pluginInfo);
        $this->assertSame(
            CategoryResourceModelChangedProductIdsPlugin::class,
            $pluginInfo[$this->pluginName]['instance'],
        );
    }

    /**
     * @magentoAppIsolation enabled
     * @magentoDbIsolation disabled
     * @magentoConfigFixture default_store klevu_configuration/auth_keys/js_api_key klevu-js-api-key
     * @magentoConfigFixture default_store klevu_configuration/auth_keys/rest_auth_key klevu-rest-auth-key
     * @magentoConfigFixture default/klevu/indexing/exclude_disabled_products 1
     */
    public function testAfterSave_ForNewCategories_setsNextActionUpdate(): void
    {
        $apiKey = 'klevu-js-api-key';

        $this->createCategory();
        $categoryFixture = $this->categoryFixturePool->get('test_category');
        $category = $categoryFixture->getCategory();

        $this->createProduct();
        $productFixture = $this->productFixturePool->get('test_product');
        /** @var Product $product */
        $product = $productFixture->getProduct();

        $this->createProduct(
            [
                'key' => 'test_product_2',
                'category_ids' => [$category->getId()],
            ],
        );
        $productFixture2 = $this->productFixturePool->get('test_product_2');
        /** @var Product $product2 */
        $product2 = $productFixture2->getProduct();

        $this->cleanIndexingEntities(apiKey: $apiKey);

        $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ID => $product->getId(),
            IndexingEntity::NEXT_ACTION => Actions::NO_ACTION,
            IndexingEntity::LAST_ACTION => Actions::ADD,
            IndexingEntity::LAST_ACTION_TIMESTAMP => date('Y-m-d H:i:s'),
        ]);
        $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ID => $product2->getId(),
            IndexingEntity::NEXT_ACTION => Actions::NO_ACTION,
            IndexingEntity::LAST_ACTION => Actions::UPDATE,
            IndexingEntity::LAST_ACTION_TIMESTAMP => date('Y-m-d H:i:s'),
        ]);

        // @phpstan-ignore-next-line
        $category->setPostedProducts([$product->getEntityId() => 0]);
        $categoryResourceModel = $this->objectManager->get(CategoryResourceModel::class);
        $categoryResourceModel->save($category);

        $indexingEntity = $this->getIndexingEntityForEntity(
            apiKey: $apiKey,
            entity: $productFixture->getProduct(),
            type: 'KLEVU_PRODUCT',
        );
        $this->assertNotNull($indexingEntity);
        $this->assertEquals(
            expected: Actions::UPDATE,
            actual: $indexingEntity->getNextAction(),
        );
        $this->assertTrue(condition: $indexingEntity->getIsIndexable());

        $indexingEntity2 = $this->getIndexingEntityForEntity(
            apiKey: $apiKey,
            entity: $productFixture2->getProduct(),
            type: 'KLEVU_PRODUCT',
        );
        $this->assertNotNull($indexingEntity2);
        $this->assertEquals(
            expected: Actions::UPDATE,
            actual: $indexingEntity2->getNextAction(),
        );
        $this->assertTrue(condition: $indexingEntity2->getIsIndexable());

        $this->cleanIndexingEntities($apiKey);
    }

    /**
     * @magentoAppIsolation enabled
     * @magentoDbIsolation disabled
     * @magentoConfigFixture default_store klevu_configuration/auth_keys/js_api_key klevu-js-api-key
     * @magentoConfigFixture default_store klevu_configuration/auth_keys/rest_auth_key klevu-rest-auth-key
     * @magentoConfigFixture default/klevu/indexing/exclude_disabled_products 1
     *
     */
    public function testAfterSave_ForNewCategories_setsNextActionUpdate_ForMultiCount(): void
    {
        $apiKey = 'klevu-js-api-key';

        $this->createCategory();
        $categoryFixture = $this->categoryFixturePool->get('test_category');
        $category = $categoryFixture->getCategory();

        $this->createProduct();
        $productFixture = $this->productFixturePool->get('test_product');
        /** @var Product $product */
        $product = $productFixture->getProduct();

        $this->createProduct(
            [
                'key' => 'test_product_2',
                'category_ids' => [$category->getId()],
            ],
        );
        $productFixture2 = $this->productFixturePool->get('test_product_2');
        /** @var Product $product2 */
        $product2 = $productFixture2->getProduct();

        $this->createProduct(
            [
                'key' => 'test_product_3',
            ],
        );
        $productFixture3 = $this->productFixturePool->get('test_product_3');
        /** @var Product $product3 */
        $product3 = $productFixture3->getProduct();

        $this->cleanIndexingEntities(apiKey: $apiKey);

        $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ID => $product->getId(),
            IndexingEntity::NEXT_ACTION => Actions::NO_ACTION,
            IndexingEntity::LAST_ACTION => Actions::ADD,
            IndexingEntity::LAST_ACTION_TIMESTAMP => date('Y-m-d H:i:s'),
        ]);
        $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ID => $product2->getId(),
            IndexingEntity::NEXT_ACTION => Actions::NO_ACTION,
            IndexingEntity::LAST_ACTION => Actions::UPDATE,
            IndexingEntity::LAST_ACTION_TIMESTAMP => date('Y-m-d H:i:s'),
        ]);
        $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ID => $product3->getId(),
            IndexingEntity::NEXT_ACTION => Actions::NO_ACTION,
            IndexingEntity::LAST_ACTION => Actions::UPDATE,
            IndexingEntity::LAST_ACTION_TIMESTAMP => date('Y-m-d H:i:s'),
        ]);

        // @phpstan-ignore-next-line
        $category->setPostedProducts([
            $product->getEntityId() => 0,
            $product3->getEntityId() => 1,
        ]);
        $categoryResourceModel = $this->objectManager->get(CategoryResourceModel::class);
        $categoryResourceModel->save($category);

        $indexingEntity = $this->getIndexingEntityForEntity(
            apiKey: $apiKey,
            entity: $productFixture->getProduct(),
            type: 'KLEVU_PRODUCT',
        );
        $this->assertNotNull($indexingEntity);
        $this->assertEquals(
            expected: Actions::UPDATE,
            actual: $indexingEntity->getNextAction(),
        );
        $this->assertTrue(condition: $indexingEntity->getIsIndexable());

        $indexingEntity2 = $this->getIndexingEntityForEntity(
            apiKey: $apiKey,
            entity: $productFixture2->getProduct(),
            type: 'KLEVU_PRODUCT',
        );
        $this->assertNotNull($indexingEntity2);
        $this->assertEquals(
            expected: Actions::UPDATE,
            actual: $indexingEntity2->getNextAction(),
        );
        $this->assertTrue(condition: $indexingEntity2->getIsIndexable());

        $indexingEntity3 = $this->getIndexingEntityForEntity(
            apiKey: $apiKey,
            entity: $productFixture3->getProduct(),
            type: 'KLEVU_PRODUCT',
        );
        $this->assertNotNull($indexingEntity3);
        $this->assertEquals(
            expected: Actions::UPDATE,
            actual: $indexingEntity3->getNextAction(),
        );
        $this->assertTrue(condition: $indexingEntity3->getIsIndexable());

        $this->cleanIndexingEntities($apiKey);
    }

    /**
     * @magentoAppIsolation enabled
     * @magentoDbIsolation disabled
     * @magentoConfigFixture default_store klevu_configuration/auth_keys/js_api_key klevu-js-api-key
     * @magentoConfigFixture default_store klevu_configuration/auth_keys/rest_auth_key klevu-rest-auth-key
     * @magentoConfigFixture default/klevu/indexing/exclude_disabled_products 1
     *
     */
    public function testAfterSave_WhenNoProductChanged_setsNextActionNone(): void
    {
        $apiKey = 'klevu-js-api-key';

        $this->createCategory();
        $categoryFixture = $this->categoryFixturePool->get('test_category');
        $category = $categoryFixture->getCategory();

        $this->createProduct();
        $productFixture = $this->productFixturePool->get('test_product');
        /** @var Product $product */
        $product = $productFixture->getProduct();

        $this->createProduct(
            [
                'key' => 'test_product_2',
                'category_ids' => [$category->getId()],
            ],
        );
        $productFixture2 = $this->productFixturePool->get('test_product_2');
        /** @var Product $product2 */
        $product2 = $productFixture2->getProduct();

        $this->cleanIndexingEntities(apiKey: $apiKey);

        $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ID => $product->getId(),
            IndexingEntity::NEXT_ACTION => Actions::NO_ACTION,
            IndexingEntity::LAST_ACTION => Actions::ADD,
            IndexingEntity::LAST_ACTION_TIMESTAMP => date('Y-m-d H:i:s'),
        ]);
        $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ID => $product2->getId(),
            IndexingEntity::NEXT_ACTION => Actions::NO_ACTION,
            IndexingEntity::LAST_ACTION => Actions::UPDATE,
            IndexingEntity::LAST_ACTION_TIMESTAMP => date('Y-m-d H:i:s'),
        ]);

        // @phpstan-ignore-next-line
        $category->setPostedProducts([$product2->getEntityId() => 0]);
        $categoryResourceModel = $this->objectManager->get(CategoryResourceModel::class);
        $categoryResourceModel->save($category);

        $indexingEntity = $this->getIndexingEntityForEntity(
            apiKey: $apiKey,
            entity: $productFixture->getProduct(),
            type: 'KLEVU_PRODUCT',
        );
        $this->assertNotNull($indexingEntity);
        $this->assertEquals(
            expected: Actions::NO_ACTION,
            actual: $indexingEntity->getNextAction(),
        );
        $this->assertTrue(condition: $indexingEntity->getIsIndexable());

        $indexingEntity2 = $this->getIndexingEntityForEntity(
            apiKey: $apiKey,
            entity: $productFixture2->getProduct(),
            type: 'KLEVU_PRODUCT',
        );
        $this->assertNotNull($indexingEntity2);
        $this->assertEquals(
            expected: Actions::NO_ACTION,
            actual: $indexingEntity2->getNextAction(),
        );
        $this->assertTrue(condition: $indexingEntity2->getIsIndexable());

        $this->cleanIndexingEntities($apiKey);
    }

    /**
     * @return mixed[]|null
     */
    private function getSystemConfigPluginInfo(): ?array
    {
        /** @var PluginList $pluginList */
        $pluginList = $this->objectManager->get(PluginList::class);

        return $pluginList->get(CategoryResourceModel::class, []);
    }
}
