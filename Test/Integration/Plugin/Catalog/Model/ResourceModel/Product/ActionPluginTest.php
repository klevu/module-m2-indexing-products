<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Test\Integration\Plugin\Catalog\Model\ResourceModel\Product;

use Klevu\Indexing\Model\IndexingEntity;
use Klevu\Indexing\Test\Integration\Traits\IndexingEntitiesTrait;
use Klevu\IndexingApi\Model\Source\Actions;
use Klevu\IndexingApi\Model\Source\IndexType;
use Klevu\IndexingProducts\Plugin\Catalog\Model\ResourceModel\Product\ActionPlugin;
use Klevu\TestFixtures\Catalog\Attribute\AttributeFixturePool;
use Klevu\TestFixtures\Catalog\AttributeTrait;
use Klevu\TestFixtures\Catalog\ProductTrait;
use Klevu\TestFixtures\Store\StoreFixturesPool;
use Klevu\TestFixtures\Store\StoreTrait;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\ResourceModel\Product\Action as ProductAction;
use Magento\Framework\Interception\PluginList\PluginList;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use TddWizard\Fixtures\Catalog\ProductFixturePool;

/**
 * @covers \Klevu\IndexingProducts\Plugin\Catalog\Model\ResourceModel\Product\ActionPlugin::class
 * @method ActionPlugin instantiateTestObject(?array $arguments = null)
 * @method ActionPlugin instantiateTestObjectFromInterface(?array $arguments = null)
 */
class ActionPluginTest extends TestCase
{
    use AttributeTrait;
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
    private ?string $pluginName = 'Klevu_IndexingProducts::ProductActionPlugin';

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->implementationFqcn = ActionPlugin::class;
        $this->objectManager = Bootstrap::getObjectManager();
        $this->storeFixturesPool = $this->objectManager->get(StoreFixturesPool::class);
        $this->attributeFixturePool = $this->objectManager->get(AttributeFixturePool::class);
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
        $this->attributeFixturePool->rollback();
        $this->storeFixturesPool->rollback();
    }

    /**
     * @magentoAppArea global
     */
    public function testPlugin_InterceptsCallsToTheField_InGlobalScope(): void
    {
        $pluginInfo = $this->getSystemConfigPluginInfo();
        $this->assertArrayHasKey($this->pluginName, $pluginInfo);
        $this->assertSame(ActionPlugin::class, $pluginInfo[$this->pluginName]['instance']);
    }

    /**
     * @magentoDbIsolation disabled
     * @magentoConfigFixture klevu_test_store_1_store klevu_configuration/auth_keys/js_api_key klevu-js-api-key
     * @magentoConfigFixture klevu_test_store_1_store klevu_configuration/auth_keys/rest_auth_key klevu-rest-auth-key
     * @magentoConfigFixture default/klevu/indexing/exclude_disabled_products 1
     */
    public function testUpdateAttributes_DoesNotTriggerIndexingChange_IfAttributeIsNotSetToIndex(): void
    {
        $apiKey = 'klevu-js-api-key';
        $this->cleanIndexingEntities($apiKey);

        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');
        $store = $storeFixture->get();

        $this->createAttribute([
            'code' => 'klevu_test_attribute',
            'attribute_type' => 'text',
            'index_as' => IndexType::NO_INDEX,
        ]);
        $attributeFixture = $this->attributeFixturePool->get('test_attribute');

        $this->createProduct(storeId: (int)$store->getId());
        $productFixture = $this->productFixturePool->get('test_product');

        $this->cleanIndexingEntities($apiKey);

        $this->createIndexingEntity([
            IndexingEntity::TARGET_ID => $productFixture->getId(),
            IndexingEntity::TARGET_ENTITY_SUBTYPE => 'simple',
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::NEXT_ACTION => Actions::NO_ACTION,
            IndexingEntity::LAST_ACTION => Actions::ADD,
            IndexingEntity::IS_INDEXABLE => true,
        ]);

        $action = $this->objectManager->get(ProductAction::class);
        $action->updateAttributes(
            entityIds: [$productFixture->getId()],
            attrData: [
                $attributeFixture->getAttributeCode() => 'Some Test String',
            ],
            storeId: $store->getId(),
        );

        $indexingEntity = $this->getIndexingEntityForEntity(
            apiKey: $apiKey,
            entity: $productFixture->getProduct(),
            type: 'KLEVU_PRODUCT',
        );
        $this->assertNotNull($indexingEntity);
        $this->assertSame(expected: Actions::NO_ACTION, actual: $indexingEntity->getNextAction());
        $this->assertTrue(condition: $indexingEntity->getIsIndexable());

        $this->cleanIndexingEntities($apiKey);
    }

    /**
     * @magentoDbIsolation disabled
     * @magentoConfigFixture klevu_test_store_1_store klevu_configuration/auth_keys/js_api_key klevu-js-api-key
     * @magentoConfigFixture klevu_test_store_1_store klevu_configuration/auth_keys/rest_auth_key klevu-rest-auth-key
     * @magentoConfigFixture default/klevu/indexing/exclude_disabled_products 1
     */
    public function testUpdateAttributes_DoesNotTriggerIndexingChange_IfEntityIsNotIndexable(): void
    {
        $apiKey = 'klevu-js-api-key';
        $this->cleanIndexingEntities($apiKey);

        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');
        $store = $storeFixture->get();

        $this->createProduct(
            productData: [
                'status' => Status::STATUS_DISABLED,
            ],
            storeId: (int)$store->getId(),
        );
        $productFixture = $this->productFixturePool->get('test_product');

        $this->cleanIndexingEntities($apiKey);

        $this->createIndexingEntity([
            IndexingEntity::TARGET_ID => $productFixture->getId(),
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::TARGET_ENTITY_SUBTYPE => 'simple',
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::NEXT_ACTION => Actions::NO_ACTION,
            IndexingEntity::LAST_ACTION => Actions::DELETE,
            IndexingEntity::IS_INDEXABLE => false,
        ]);

        $action = $this->objectManager->get(ProductAction::class);
        $action->updateAttributes(
            entityIds: [$productFixture->getId()],
            attrData: [
                ProductInterface::NAME => 'Some Test String',
            ],
            storeId: $store->getId(),
        );

        $indexingEntity = $this->getIndexingEntityForEntity(
            apiKey: $apiKey,
            entity: $productFixture->getProduct(),
            type: 'KLEVU_PRODUCT',
        );
        $this->assertNotNull($indexingEntity);
        $this->assertSame(expected: Actions::NO_ACTION, actual: $indexingEntity->getNextAction());
        $this->assertFalse(condition: $indexingEntity->getIsIndexable());

        $this->cleanIndexingEntities($apiKey);
    }

    /**
     * @magentoDbIsolation disabled
     * @magentoConfigFixture klevu_test_store_1_store klevu_configuration/auth_keys/js_api_key klevu-js-api-key
     * @magentoConfigFixture klevu_test_store_1_store klevu_configuration/auth_keys/rest_auth_key klevu-rest-auth-key
     * @magentoConfigFixture default/klevu/indexing/exclude_disabled_products 1
     */
    public function testUpdateAttributes_UpdatesIndexingEntity(): void
    {
        $apiKey = 'klevu-js-api-key';
        $this->cleanIndexingEntities($apiKey);

        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');
        $store = $storeFixture->get();

        $this->createAttribute([
            'code' => 'klevu_test_attribute',
            'attribute_type' => 'text',
            'index_as' => IndexType::INDEX,
        ]);
        $attributeFixture = $this->attributeFixturePool->get('test_attribute');

        $this->createProduct(storeId: (int)$store->getId());
        $productFixture = $this->productFixturePool->get('test_product');

        $this->cleanIndexingEntities($apiKey);

        $this->createIndexingEntity([
            IndexingEntity::TARGET_ID => $productFixture->getId(),
            IndexingEntity::TARGET_ENTITY_SUBTYPE => 'simple',
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::NEXT_ACTION => Actions::NO_ACTION,
            IndexingEntity::LAST_ACTION => Actions::ADD,
            IndexingEntity::IS_INDEXABLE => true,
        ]);

        $action = $this->objectManager->get(ProductAction::class);
        $action->updateAttributes(
            entityIds: [$productFixture->getId()],
            attrData: [
                $attributeFixture->getAttributeCode() => 'Some Test String',
            ],
            storeId: $store->getId(),
        );

        $indexingEntity = $this->getIndexingEntityForEntity(
            apiKey: $apiKey,
            entity: $productFixture->getProduct(),
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

        return $pluginList->get(ProductAction::class, []);
    }
}
