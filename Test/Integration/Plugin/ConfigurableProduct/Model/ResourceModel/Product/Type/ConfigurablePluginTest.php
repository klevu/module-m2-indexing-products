<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Test\Integration\Plugin\ConfigurableProduct\Model\ResourceModel\Product\Type;

use Klevu\Configuration\Service\Provider\ScopeProviderInterface;
use Klevu\Indexing\Model\IndexingEntity;
use Klevu\Indexing\Test\Integration\Traits\IndexingEntitiesTrait;
use Klevu\IndexingApi\Api\Data\IndexingEntityInterface;
use Klevu\IndexingApi\Model\Source\Actions;
use Klevu\IndexingProducts\Plugin\ConfigurableProduct\Model\ResourceModel\Product\Type\ConfigurablePlugin as ConfigurableProductTypePlugin; //phpcs:ignore Generic.Files.LineLength.TooLong
use Klevu\TestFixtures\Catalog\Attribute\AttributeFixturePool;
use Klevu\TestFixtures\Catalog\AttributeTrait;
use Klevu\TestFixtures\Catalog\ProductTrait;
use Klevu\TestFixtures\Store\StoreFixturesPool;
use Klevu\TestFixtures\Store\StoreTrait;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\SetAuthKeysTrait;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\ConfigurableProduct\Model\ResourceModel\Product\Type\Configurable as ConfigurableResourceModel;
use Magento\Framework\Interception\PluginList\PluginList;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\Indexer\TestCase;
use TddWizard\Fixtures\Catalog\ProductFixturePool;

/**
 * @covers ConfigurableProductTypePlugin
 * @method ConfigurableProductTypePlugin instantiateTestObject(?array $arguments = null)
 * @method ConfigurableProductTypePlugin instantiateTestObjectFromInterface(?array $arguments = null)
 */
class ConfigurablePluginTest extends TestCase
{
    use AttributeTrait;
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
    private ?string $pluginName = 'Klevu_IndexingProducts::ConfigurableProductTypePlugin';

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->implementationFqcn = ConfigurableProductTypePlugin::class;
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
        $this->assertSame(ConfigurableProductTypePlugin::class, $pluginInfo[$this->pluginName]['instance']);
    }

    /**
     * @return mixed[]|null
     */
    private function getSystemConfigPluginInfo(): ?array
    {
        /** @var PluginList $pluginList */
        $pluginList = $this->objectManager->get(PluginList::class);

        return $pluginList->get(ConfigurableResourceModel::class, []);
    }

    /**
     * @magentoDbIsolation disabled
     */
    public function testAfterAddRelations_AddsVariantsToKlevuIndexingEntity(): void
    {
        $apiKey = 'klevu-test-js-api-key';

        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');
        $store = $storeFixture->get();
        $scopeProvider = $this->objectManager->get(ScopeProviderInterface::class);
        $scopeProvider->setCurrentScope(scope: $storeFixture->get());
        $this->setAuthKeys(
            scopeProvider: $scopeProvider,
            jsApiKey: $apiKey,
            restAuthKey: 'klevu-test-rest-auth-key',
        );

        $this->createAttribute([
            'key' => 'klevu_test_attribute',
            'attribute_type' => 'configurable',
            'options' => [
                '1' => 'Option 1',
                '2' => 'Option 2',
                '3' => 'Option 3',
            ],
        ]);
        $configurableAttribute = $this->attributeFixturePool->get('klevu_test_attribute');

        $this->createProduct([
            'key' => 'test_simple_product_1',
            'sku' => 'test_simple_product_1',
            'status' => Status::STATUS_ENABLED,
            'website_ids' => [
                $store->getWebsiteId(),
            ],
            'data' => [
                $configurableAttribute->getAttributeCode() => '1',
            ],
        ]);
        $simple1ProductFixture = $this->productFixturePool->get('test_simple_product_1');

        $this->createProduct([
            'key' => 'test_simple_product_2',
            'sku' => 'test_simple_product_2',
            'status' => Status::STATUS_ENABLED,
            'website_ids' => [
                $store->getWebsiteId(),
            ],
            'data' => [
                $configurableAttribute->getAttributeCode() => '2',
            ],
        ]);
        $simple2ProductFixture = $this->productFixturePool->get('test_simple_product_2');

        $this->createProduct([
            'key' => 'test_configurable_product',
            'sku' => 'test_configurable_product',
            'status' => Status::STATUS_ENABLED,
            'website_ids' => [
                $store->getWebsiteId(),
            ],
            'type_id' => Configurable::TYPE_CODE,
            'configurable_attributes' => [
                $configurableAttribute->getAttribute(),
            ],
            'variants' => [
                $simple2ProductFixture->getProduct(),
            ],
        ]);
        $configurableProductFixture = $this->productFixturePool->get('test_configurable_product');
        /** @var Product $configurableProduct */
        $configurableProduct = $configurableProductFixture->getProduct();

        $this->cleanIndexingEntities(apiKey: $apiKey);
        $this->createIndexingEntity([
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::TARGET_ID => $configurableProduct->getId(),
            IndexingEntity::TARGET_PARENT_ID => null,
            IndexingEntity::NEXT_ACTION => Actions::NO_ACTION,
            IndexingEntity::LAST_ACTION => Actions::ADD,
            IndexingEntity::LAST_ACTION_TIMESTAMP => date('Y-m-d H:i:s'),
        ]);
        $this->createIndexingEntity([
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::TARGET_ID => $simple2ProductFixture->getId(),
            IndexingEntity::TARGET_PARENT_ID => $configurableProduct->getId(),
            IndexingEntity::NEXT_ACTION => Actions::NO_ACTION,
            IndexingEntity::LAST_ACTION => Actions::ADD,
            IndexingEntity::LAST_ACTION_TIMESTAMP => date('Y-m-d H:i:s'),
        ]);

        $configurableResourceModel = $this->objectManager->get(ConfigurableResourceModel::class);
        $configurableResourceModel->saveProducts(
            mainProduct: $configurableProduct,
            productIds: [
                $simple1ProductFixture->getId(),
                $simple2ProductFixture->getId(),
            ],
        );

        $indexingEntities = $this->getIndexingEntities(
            type: 'KLEVU_PRODUCT',
            apiKey: $apiKey,
        );

        $simple1Variants = array_filter(
            array: $indexingEntities,
            callback: static fn (IndexingEntityInterface $indexingEntity) => (
                (int)$indexingEntity->getTargetId() === (int)$simple1ProductFixture->getId()
                && (int)$indexingEntity->getTargetParentId() === (int)$configurableProductFixture->getId()
            ),
        );
        $this->assertCount(expectedCount: 1, haystack: $simple1Variants);
        $simple1Variant = array_shift($simple1Variants);

        $this->assertSame(
            expected: Actions::ADD,
            actual: $simple1Variant->getNextAction(),
        );
        $this->assertSame(
            expected: (int)$simple1ProductFixture->getId(),
            actual: (int)$simple1Variant->getTargetId(),
        );
        $this->assertSame(
            expected: (int)$configurableProductFixture->getId(),
            actual: (int)$simple1Variant->getTargetParentId(),
        );

        $simple2Variants = array_filter(
            array: $indexingEntities,
            callback: static fn (IndexingEntityInterface $indexingEntity) => (
                (int)$indexingEntity->getTargetId() === (int)$simple2ProductFixture->getId()
                && (int)$indexingEntity->getTargetParentId() === (int)$configurableProductFixture->getId()
            ),
        );
        $this->assertCount(expectedCount: 1, haystack: $simple2Variants);
        $simple2Variant = array_shift($simple2Variants);

        $this->assertSame(
            expected: Actions::NO_ACTION,
            actual: $simple2Variant->getNextAction(),
        );
        $this->assertSame(
            expected: (int)$simple2ProductFixture->getId(),
            actual: (int)$simple2Variant->getTargetId(),
        );
        $this->assertSame(
            expected: (int)$configurableProductFixture->getId(),
            actual: (int)$simple2Variant->getTargetParentId(),
        );
    }

    /**
     * @magentoDbIsolation disabled
     */
    public function testAfterRemoveRelations_SetsKlevuIndexingEntityToDelete(): void
    {
        $apiKey = 'klevu-test-js-api-key';

        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');
        $store = $storeFixture->get();
        $scopeProvider = $this->objectManager->get(ScopeProviderInterface::class);
        $scopeProvider->setCurrentScope(scope: $storeFixture->get());
        $this->setAuthKeys(
            scopeProvider: $scopeProvider,
            jsApiKey: $apiKey,
            restAuthKey: 'klevu-test-rest-auth-key',
        );

        $this->createAttribute([
            'key' => 'klevu_test_attribute',
            'attribute_type' => 'configurable',
            'options' => [
                '1' => 'Option 1',
                '2' => 'Option 2',
                '3' => 'Option 3',
            ],
        ]);
        $configurableAttribute = $this->attributeFixturePool->get('klevu_test_attribute');

        $this->createProduct([
            'key' => 'test_simple_product_1',
            'sku' => 'test_simple_product_1',
            'status' => Status::STATUS_ENABLED,
            'website_ids' => [
                $store->getWebsiteId(),
            ],
            'data' => [
                $configurableAttribute->getAttributeCode() => '1',
            ],
        ]);
        $simple1ProductFixture = $this->productFixturePool->get('test_simple_product_1');

        $this->createProduct([
            'key' => 'test_simple_product_2',
            'sku' => 'test_simple_product_2',
            'status' => Status::STATUS_ENABLED,
            'website_ids' => [
                $store->getWebsiteId(),
            ],
            'data' => [
                $configurableAttribute->getAttributeCode() => '2',
            ],
        ]);
        $simple2ProductFixture = $this->productFixturePool->get('test_simple_product_2');

        $this->createProduct([
            'key' => 'test_configurable_product',
            'sku' => 'test_configurable_product',
            'status' => Status::STATUS_ENABLED,
            'website_ids' => [
                $store->getWebsiteId(),
            ],
            'type_id' => Configurable::TYPE_CODE,
            'configurable_attributes' => [
                $configurableAttribute->getAttribute(),
            ],
            'variants' => [
                $simple1ProductFixture->getProduct(),
                $simple2ProductFixture->getProduct(),
            ],
        ]);
        $configurableProductFixture = $this->productFixturePool->get('test_configurable_product');
        /** @var Product $configurableProduct */
        $configurableProduct = $configurableProductFixture->getProduct();

        $this->cleanIndexingEntities(apiKey: $apiKey);
        $this->createIndexingEntity([
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::TARGET_ID => $configurableProduct->getId(),
            IndexingEntity::TARGET_PARENT_ID => null,
            IndexingEntity::NEXT_ACTION => Actions::NO_ACTION,
            IndexingEntity::LAST_ACTION => Actions::ADD,
            IndexingEntity::LAST_ACTION_TIMESTAMP => date('Y-m-d H:i:s'),
        ]);
        $this->createIndexingEntity([
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::TARGET_ID => $simple1ProductFixture->getId(),
            IndexingEntity::TARGET_PARENT_ID => $configurableProduct->getId(),
            IndexingEntity::NEXT_ACTION => Actions::NO_ACTION,
            IndexingEntity::LAST_ACTION => Actions::ADD,
            IndexingEntity::LAST_ACTION_TIMESTAMP => date('Y-m-d H:i:s'),
        ]);
        $this->createIndexingEntity([
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::TARGET_ID => $simple2ProductFixture->getId(),
            IndexingEntity::TARGET_PARENT_ID => $configurableProduct->getId(),
            IndexingEntity::NEXT_ACTION => Actions::NO_ACTION,
            IndexingEntity::LAST_ACTION => Actions::ADD,
            IndexingEntity::LAST_ACTION_TIMESTAMP => date('Y-m-d H:i:s'),
        ]);

        $configurableResourceModel = $this->objectManager->get(ConfigurableResourceModel::class);
        $configurableResourceModel->saveProducts(
            mainProduct: $configurableProduct,
            productIds: [
                $simple2ProductFixture->getId(),
            ],
        );

        $indexingEntities = $this->getIndexingEntities(
            type: 'KLEVU_PRODUCT',
            apiKey: $apiKey,
        );

        $simple1Variants = array_filter(
            array: $indexingEntities,
            callback: static fn (IndexingEntityInterface $indexingEntity) => (
                (int)$indexingEntity->getTargetId() === (int)$simple1ProductFixture->getId()
                && (int)$indexingEntity->getTargetParentId() === (int)$configurableProductFixture->getId()
            ),
        );
        $this->assertCount(expectedCount: 1, haystack: $simple1Variants);
        $simple1Variant = array_shift($simple1Variants);

        $this->assertSame(
            expected: Actions::DELETE,
            actual: $simple1Variant->getNextAction(),
        );
        $this->assertSame(
            expected: (int)$simple1ProductFixture->getId(),
            actual: (int)$simple1Variant->getTargetId(),
        );
        $this->assertSame(
            expected: (int)$configurableProductFixture->getId(),
            actual: (int)$simple1Variant->getTargetParentId(),
        );

        $simple2Variants = array_filter(
            array: $indexingEntities,
            callback: static fn (IndexingEntityInterface $indexingEntity) => (
                (int)$indexingEntity->getTargetId() === (int)$simple2ProductFixture->getId()
                && (int)$indexingEntity->getTargetParentId() === (int)$configurableProductFixture->getId()
            ),
        );
        $this->assertCount(expectedCount: 1, haystack: $simple2Variants);
        $simple2Variant = array_shift($simple2Variants);

        $this->assertSame(
            expected: Actions::NO_ACTION,
            actual: $simple2Variant->getNextAction(),
        );
        $this->assertSame(
            expected: (int)$simple2ProductFixture->getId(),
            actual: (int)$simple2Variant->getTargetId(),
        );
        $this->assertSame(
            expected: (int)$configurableProductFixture->getId(),
            actual: (int)$simple2Variant->getTargetParentId(),
        );

        $configurables = array_filter(
            array: $indexingEntities,
            callback: static fn (IndexingEntityInterface $indexingEntity) => (
                (int)$indexingEntity->getTargetId() === (int)$configurableProductFixture->getId()
                && $indexingEntity->getTargetParentId() === null
            ),
        );
        $this->assertCount(expectedCount: 1, haystack: $configurables);
        $configurable = array_shift($configurables);

        $this->assertSame(
            expected: Actions::NO_ACTION,
            actual: $configurable->getNextAction(),
        );
        $this->assertSame(
            expected: (int)$configurableProductFixture->getId(),
            actual: (int)$configurable->getTargetId(),
        );
        $this->assertNull(
            actual: $configurable->getTargetParentId(),
        );
    }

    /**
     * @magentoDbIsolation disabled
     */
    public function testAfterRemoveAllRelations_SetsKlevuIndexingEntityToDelete(): void
    {
        $apiKey = 'klevu-test-js-api-key';

        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');
        $store = $storeFixture->get();
        $scopeProvider = $this->objectManager->get(ScopeProviderInterface::class);
        $scopeProvider->setCurrentScope(scope: $storeFixture->get());
        $this->setAuthKeys(
            scopeProvider: $scopeProvider,
            jsApiKey: $apiKey,
            restAuthKey: 'klevu-test-rest-auth-key',
        );

        $this->createAttribute([
            'key' => 'klevu_test_attribute',
            'attribute_type' => 'configurable',
            'options' => [
                '1' => 'Option 1',
                '2' => 'Option 2',
                '3' => 'Option 3',
            ],
        ]);
        $configurableAttribute = $this->attributeFixturePool->get('klevu_test_attribute');

        $this->createProduct([
            'key' => 'test_simple_product_1',
            'sku' => 'test_simple_product_1',
            'status' => Status::STATUS_ENABLED,
            'website_ids' => [
                $store->getWebsiteId(),
            ],
            'data' => [
                $configurableAttribute->getAttributeCode() => '1',
            ],
        ]);
        $simple1ProductFixture = $this->productFixturePool->get('test_simple_product_1');

        $this->createProduct([
            'key' => 'test_simple_product_2',
            'sku' => 'test_simple_product_2',
            'status' => Status::STATUS_ENABLED,
            'website_ids' => [
                $store->getWebsiteId(),
            ],
            'data' => [
                $configurableAttribute->getAttributeCode() => '2',
            ],
        ]);
        $simple2ProductFixture = $this->productFixturePool->get('test_simple_product_2');

        $this->createProduct([
            'key' => 'test_configurable_product',
            'sku' => 'test_configurable_product',
            'status' => Status::STATUS_ENABLED,
            'website_ids' => [
                $store->getWebsiteId(),
            ],
            'type_id' => Configurable::TYPE_CODE,
            'configurable_attributes' => [
                $configurableAttribute->getAttribute(),
            ],
            'variants' => [
                $simple1ProductFixture->getProduct(),
                $simple2ProductFixture->getProduct(),
            ],
        ]);
        $configurableProductFixture = $this->productFixturePool->get('test_configurable_product');
        /** @var Product $configurableProduct */
        $configurableProduct = $configurableProductFixture->getProduct();

        $this->cleanIndexingEntities(apiKey: $apiKey);
        $this->createIndexingEntity([
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::TARGET_ID => $configurableProduct->getId(),
            IndexingEntity::TARGET_PARENT_ID => null,
            IndexingEntity::NEXT_ACTION => Actions::NO_ACTION,
            IndexingEntity::LAST_ACTION => Actions::ADD,
            IndexingEntity::LAST_ACTION_TIMESTAMP => date('Y-m-d H:i:s'),
        ]);
        $this->createIndexingEntity([
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::TARGET_ID => $simple1ProductFixture->getId(),
            IndexingEntity::TARGET_PARENT_ID => $configurableProduct->getId(),
            IndexingEntity::NEXT_ACTION => Actions::NO_ACTION,
            IndexingEntity::LAST_ACTION => Actions::ADD,
            IndexingEntity::LAST_ACTION_TIMESTAMP => date('Y-m-d H:i:s'),
        ]);
        $this->createIndexingEntity([
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::TARGET_ID => $simple2ProductFixture->getId(),
            IndexingEntity::TARGET_PARENT_ID => $configurableProduct->getId(),
            IndexingEntity::NEXT_ACTION => Actions::NO_ACTION,
            IndexingEntity::LAST_ACTION => Actions::ADD,
            IndexingEntity::LAST_ACTION_TIMESTAMP => date('Y-m-d H:i:s'),
        ]);

        $configurableResourceModel = $this->objectManager->get(ConfigurableResourceModel::class);
        $configurableResourceModel->saveProducts(
            mainProduct: $configurableProduct,
            productIds: [],
        );

        $indexingEntities = $this->getIndexingEntities(
            type: 'KLEVU_PRODUCT',
            apiKey: $apiKey,
        );

        $simple1Variants = array_filter(
            array: $indexingEntities,
            callback: static fn (IndexingEntityInterface $indexingEntity) => (
                (int)$indexingEntity->getTargetId() === (int)$simple1ProductFixture->getId()
                && (int)$indexingEntity->getTargetParentId() === (int)$configurableProductFixture->getId()
            ),
        );
        $this->assertCount(expectedCount: 1, haystack: $simple1Variants);
        $simple1Variant = array_shift($simple1Variants);

        $this->assertSame(
            expected: Actions::DELETE,
            actual: $simple1Variant->getNextAction(),
        );
        $this->assertSame(
            expected: (int)$simple1ProductFixture->getId(),
            actual: (int)$simple1Variant->getTargetId(),
        );
        $this->assertSame(
            expected: (int)$configurableProductFixture->getId(),
            actual: (int)$simple1Variant->getTargetParentId(),
        );

        $simple2Variants = array_filter(
            array: $indexingEntities,
            callback: static fn (IndexingEntityInterface $indexingEntity) => (
                (int)$indexingEntity->getTargetId() === (int)$simple2ProductFixture->getId()
                && (int)$indexingEntity->getTargetParentId() === (int)$configurableProductFixture->getId()
            ),
        );
        $this->assertCount(expectedCount: 1, haystack: $simple2Variants);
        $simple2Variant = array_shift($simple2Variants);

        $this->assertSame(
            expected: Actions::DELETE,
            actual: $simple2Variant->getNextAction(),
        );
        $this->assertSame(
            expected: (int)$simple2ProductFixture->getId(),
            actual: (int)$simple2Variant->getTargetId(),
        );
        $this->assertSame(
            expected: (int)$configurableProductFixture->getId(),
            actual: (int)$simple2Variant->getTargetParentId(),
        );

        $configurables = array_filter(
            array: $indexingEntities,
            callback: static fn (IndexingEntityInterface $indexingEntity) => (
                (int)$indexingEntity->getTargetId() === (int)$configurableProductFixture->getId()
                && $indexingEntity->getTargetParentId() === null
            ),
        );
        $this->assertCount(expectedCount: 1, haystack: $configurables);
        $configurable = array_shift($configurables);

        $this->assertSame(
            expected: Actions::NO_ACTION,
            actual: $configurable->getNextAction(),
        );
        $this->assertSame(
            expected: (int)$configurableProductFixture->getId(),
            actual: (int)$configurable->getTargetId(),
        );
        $this->assertNull(
            actual: $configurable->getTargetParentId(),
        );
    }
}
