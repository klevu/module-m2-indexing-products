<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Test\Integration\Plugin\Catalog\Model\ResourceModel;

use Klevu\Indexing\Model\IndexingEntity;
use Klevu\Indexing\Model\ResourceModel\IndexingEntity\Collection;
use Klevu\Indexing\Test\Integration\Traits\IndexingEntitiesTrait;
use Klevu\IndexingApi\Api\Data\IndexingEntityInterface;
use Klevu\IndexingApi\Model\Source\Actions;
use Klevu\IndexingApi\Model\Source\IndexType;
use Klevu\IndexingProducts\Model\Source\Aspect;
use Klevu\IndexingProducts\Plugin\Catalog\Model\ResourceModel\ProductPlugin;
use Klevu\TestFixtures\Catalog\Attribute\AttributeFixturePool;
use Klevu\TestFixtures\Catalog\AttributeTrait;
use Klevu\TestFixtures\Catalog\ProductTrait;
use Klevu\TestFixtures\Store\StoreFixturesPool;
use Klevu\TestFixtures\Store\StoreTrait;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\ResourceModel\Product as ProductResourceModel;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Framework\Interception\PluginList\PluginList;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use TddWizard\Fixtures\Catalog\ProductFixturePool;

/**
 * @covers \Klevu\IndexingProducts\Plugin\Catalog\Model\ResourceModel\ProductPlugin
 * @method ProductPlugin instantiateTestObject(?array $arguments = null)
 * @method ProductPlugin instantiateTestObjectFromInterface(?array $arguments = null)
 */
class ProductSavePluginTest extends TestCase
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
    private ?string $pluginName = 'Klevu_IndexingProducts::ProductResourceModelPlugin';

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->implementationFqcn = ProductPlugin::class;
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
        $this->assertSame(ProductPlugin::class, $pluginInfo[$this->pluginName]['instance']);
    }

    /**
     * @magentoDbIsolation disabled
     * @magentoConfigFixture klevu_test_store_1_store klevu_configuration/auth_keys/js_api_key klevu-js-api-key
     * @magentoConfigFixture klevu_test_store_1_store klevu_configuration/auth_keys/rest_auth_key klevu-rest-auth-key
     */
    public function testAroundSave_ForNewProducts_setsNextActonAdd(): void
    {
        $apiKey = 'klevu-js-api-key';
        $this->cleanIndexingEntities($apiKey);

        $this->createStore();

        $this->createProduct();
        $productFixture = $this->productFixturePool->get('test_product');

        $indexingEntity = $this->getIndexingEntityForProduct(
            apiKey: $apiKey,
            product: $productFixture->getProduct(),
        );
        $this->assertNotNull($indexingEntity);
        $this->assertSame(
            expected: Actions::ADD,
            actual: $indexingEntity->getNextAction(),
            message: 'expected: ' . Actions::ADD->value . ', actual: ' . $indexingEntity->getNextAction()->value,
        );
        $this->assertTrue(condition: $indexingEntity->getIsIndexable());
    }

    /**
     * @magentoDbIsolation disabled
     * @magentoConfigFixture klevu_test_store_1_store klevu_configuration/auth_keys/js_api_key klevu-js-api-key
     * @magentoConfigFixture klevu_test_store_1_store klevu_configuration/auth_keys/rest_auth_key klevu-rest-auth-key
     */
    public function testAroundSave_ForExistingProducts_WhichHasNotYetBeenSynced_DoesNotChangeNextAction(): void
    {
        $apiKey = 'klevu-js-api-key';
        $this->cleanIndexingEntities($apiKey);

        $this->createStore();

        $this->createProduct();
        $productFixture = $this->productFixturePool->get('test_product');
        $product = $productFixture->getProduct();

        $indexingEntity = $this->getIndexingEntityForProduct(apiKey: $apiKey, product: $product);
        $this->assertNotNull($indexingEntity);
        $this->assertSame(expected: Actions::ADD, actual: $indexingEntity->getNextAction());
        $this->assertTrue(condition: $indexingEntity->getIsIndexable());

        $productResourceModel = $this->objectManager->get(ProductResourceModel::class);
        $productResourceModel->save($product);

        $indexingEntity = $this->getIndexingEntityForProduct(apiKey: $apiKey, product: $product);
        $this->assertNotNull($indexingEntity);
        $this->assertSame(
            expected: Actions::ADD,
            actual: $indexingEntity->getNextAction(),
            message: 'expected: ' . Actions::ADD->value . ', actual: ' . $indexingEntity->getNextAction()->value,
        );
        $this->assertTrue(condition: $indexingEntity->getIsIndexable());
    }

    /**
     * @magentoDbIsolation disabled
     * @magentoConfigFixture klevu_test_store_1_store klevu_configuration/auth_keys/js_api_key klevu-js-api-key
     * @magentoConfigFixture klevu_test_store_1_store klevu_configuration/auth_keys/rest_auth_key klevu-rest-auth-key
     */
    public function testAroundSave_ForExistingProduct_UpdateNextAction(): void
    {
        $apiKey = 'klevu-js-api-key';
        $this->cleanIndexingEntities($apiKey);

        $this->createStore();

        $this->createProduct();
        $productFixture = $this->productFixturePool->get('test_product');
        $product = $productFixture->getProduct();

        $this->cleanIndexingEntities($apiKey);

        $this->createIndexingEntity([
            IndexingEntity::TARGET_ID => $product->getId(),
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::NEXT_ACTION => Actions::NO_ACTION,
            IndexingEntity::LAST_ACTION => Actions::ADD,
            IndexingEntity::LAST_ACTION_TIMESTAMP => date('Y-m-d H:i:s'),
            IndexingEntity::IS_INDEXABLE => true,
            IndexingEntity::TARGET_ENTITY_SUBTYPE => 'simple',
        ]);

        $product->setName('Test Product UPDATE TITLE ' . random_int(0, 99999999));
        $productResourceModel = $this->objectManager->get(ProductResourceModel::class);
        $productResourceModel->save($product);

        $indexingEntity = $this->getIndexingEntityForProduct(apiKey: $apiKey, product: $product);
        $this->assertNotNull($indexingEntity);
        $this->assertSame(
            expected: Actions::UPDATE,
            actual: $indexingEntity->getNextAction(),
            message: 'expected: ' . Actions::UPDATE->value . ', actual: ' . $indexingEntity->getNextAction()->value,
        );
        $this->assertTrue(condition: $indexingEntity->getIsIndexable());
    }

    /**
     * @magentoDbIsolation disabled
     * @magentoConfigFixture klevu_test_store_1_store klevu_configuration/auth_keys/js_api_key klevu-js-api-key
     * @magentoConfigFixture klevu_test_store_1_store klevu_configuration/auth_keys/rest_auth_key klevu-rest-auth-key
     * @magentoConfigFixture default/klevu/indexing/exclude_disabled_products 1
     */
    public function testAroundSave_ForExistingProduct_NotIndexable_ChangedToIndexable(): void
    {
        $apiKey = 'klevu-js-api-key';
        $this->cleanIndexingEntities($apiKey);

        $this->createStore();

        $this->createProduct([
            'status' => Status::STATUS_DISABLED,
        ]);
        $productFixture = $this->productFixturePool->get('test_product');
        $product = $productFixture->getProduct();

        $indexingEntity = $this->getIndexingEntityForProduct(apiKey: $apiKey, product: $product);
        $this->assertNotNull($indexingEntity);
        $this->assertSame(expected: Actions::NO_ACTION, actual: $indexingEntity->getNextAction());
        $this->assertFalse(condition: $indexingEntity->getIsIndexable());

        $product->setName('Test Product UPDATE TITLE ' . random_int(0, 99999999));
        $productResourceModel = $this->objectManager->get(ProductResourceModel::class);
        $productResourceModel->save($product);

        $indexingEntity = $this->getIndexingEntityForProduct(apiKey: $apiKey, product: $product);
        $this->assertNotNull($indexingEntity);
        $this->assertSame(
            expected: Actions::NO_ACTION,
            actual: $indexingEntity->getNextAction(),
            message: 'expected: ' . Actions::NO_ACTION->value . ', actual: ' . $indexingEntity->getNextAction()->value,
        );
        $this->assertFalse(condition: $indexingEntity->getIsIndexable(),);

        $product->setStatus(Status::STATUS_ENABLED);
        $productResourceModel->save($product);

        $indexingEntity = $this->getIndexingEntityForProduct(apiKey: $apiKey, product: $product);
        $this->assertNotNull($indexingEntity);
        $this->assertSame(
            expected: Actions::ADD,
            actual: $indexingEntity->getNextAction(),
            message: 'expected: ' . Actions::ADD->value . ', actual: ' . $indexingEntity->getNextAction()->value,
        );
        $this->assertTrue(condition: $indexingEntity->getIsIndexable());
    }

    /**
     * @magentoDbIsolation disabled
     * @magentoConfigFixture klevu_test_store_1_store klevu_configuration/auth_keys/js_api_key klevu-js-api-key
     * @magentoConfigFixture klevu_test_store_1_store klevu_configuration/auth_keys/rest_auth_key klevu-rest-auth-key
     */
    public function testAroundSave_ForExistingCategory_NextActionDeleteChangedToUpdate(): void
    {
        $apiKey = 'klevu-js-api-key';
        $this->cleanIndexingEntities($apiKey);

        $this->createStore();

        $this->createProduct();
        $productFixture = $this->productFixturePool->get('test_product');
        $product = $productFixture->getProduct();

        $this->cleanIndexingEntities($apiKey);

        $this->createIndexingEntity([
            IndexingEntity::TARGET_ID => $product->getId(),
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::NEXT_ACTION => Actions::DELETE,
            IndexingEntity::LAST_ACTION => Actions::UPDATE,
            IndexingEntity::IS_INDEXABLE => true,
            IndexingEntity::TARGET_ENTITY_SUBTYPE => 'simple',
        ]);

        $product->setName('Test Product UPDATE TITLE ' . random_int(0, 99999999));
        $productResourceModel = $this->objectManager->get(ProductResourceModel::class);
        $productResourceModel->save($product);

        $indexingEntity = $this->getIndexingEntityForProduct(apiKey: $apiKey, product: $product);
        $this->assertNotNull($indexingEntity);
        $this->assertSame(
            expected: Actions::UPDATE,
            actual: $indexingEntity->getNextAction(),
            message: 'expected: ' . Actions::UPDATE->value . ', actual: ' . $indexingEntity->getNextAction()->value,
        );
        $this->assertTrue(condition: $indexingEntity->getIsIndexable());
    }

    /**
     * @magentoDbIsolation disabled
     * @magentoConfigFixture klevu_test_store_1_store klevu_configuration/auth_keys/js_api_key klevu-js-api-key
     * @magentoConfigFixture klevu_test_store_1_store klevu_configuration/auth_keys/rest_auth_key klevu-rest-auth-key
     * @magentoConfigFixture default/klevu/indexing/exclude_disabled_products 1
     */
    public function testAroundSave_MakesIndexableEntityIndexableAgain(): void
    {
        $apiKey = 'klevu-js-api-key';
        $this->cleanIndexingEntities($apiKey);

        $this->createStore();

        $this->createProduct([
            'status' => Status::STATUS_DISABLED,
        ]);
        $productFixture = $this->productFixturePool->get('test_product');
        $product = $productFixture->getProduct();

        $indexingEntity = $this->getIndexingEntityForProduct(apiKey: $apiKey, product: $product);
        $this->assertNotNull($indexingEntity);
        $this->assertSame(
            expected: Actions::NO_ACTION,
            actual: $indexingEntity->getNextAction(),
            message: 'expected: ' . Actions::NO_ACTION->value . ', actual: ' . $indexingEntity->getNextAction()->value,
        );
        $this->assertFalse(condition: $indexingEntity->getIsIndexable());

        $product->setStatus(Status::STATUS_ENABLED);
        $productResourceModel = $this->objectManager->get(ProductResourceModel::class);
        $productResourceModel->save($product);

        $indexingEntity = $this->getIndexingEntityForProduct(apiKey: $apiKey, product: $product);
        $this->assertNotNull($indexingEntity);
        $this->assertSame(
            expected: Actions::ADD,
            actual: $indexingEntity->getNextAction(),
            message: 'expected: ' . Actions::ADD->value . ', actual: ' . $indexingEntity->getNextAction()->value,
        );
        $this->assertTrue(condition: $indexingEntity->getIsIndexable());
    }

    /**
     * @magentoAppIsolation enabled
     * @magentoDbIsolation disabled
     * @magentoConfigFixture klevu_test_store_1_store klevu_configuration/auth_keys/js_api_key klevu-js-api-key
     * @magentoConfigFixture klevu_test_store_1_store klevu_configuration/auth_keys/rest_auth_key klevu-rest-auth-key
     */
    public function testAroundSave_NoChangeForNoneIndexableAttributeChange(): void
    {
        $apiKey = 'klevu-js-api-key';
        $this->createStore();

        $this->createAttribute([
            'code' => 'klevu_test_select_attribute',
            'key' => 'klevu_test_nonindexable_attribute',
            'index_as' => IndexType::NO_INDEX,
            'aspect' => Aspect::NONE,
        ]);
        $nonIndexableAttributeFixture = $this->attributeFixturePool->get('klevu_test_nonindexable_attribute');

        $this->createProduct([
            'data' => [
                $nonIndexableAttributeFixture->getAttributeCode() => 'Original Value',
            ],
        ]);
        $productFixture = $this->productFixturePool->get('test_product');
        /** @var Product&ProductInterface $product */
        $product = $productFixture->getProduct();

        $this->cleanIndexingEntities($apiKey);

        $this->createIndexingEntity([
            IndexingEntity::TARGET_ID => $product->getId(),
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::NEXT_ACTION => Actions::NO_ACTION,
            IndexingEntity::LAST_ACTION => Actions::UPDATE,
            IndexingEntity::IS_INDEXABLE => true,
            IndexingEntity::TARGET_ENTITY_SUBTYPE => 'simple',
        ]);

        $product->setData(key: $nonIndexableAttributeFixture->getAttributeCode(), value: 'Updated Value');
        $productResourceModel = $this->objectManager->get(ProductResourceModel::class);
        $productResourceModel->save($product);

        $indexingEntity = $this->getIndexingEntityForProduct(apiKey: $apiKey, product: $product);
        $this->assertNotNull($indexingEntity);
        $this->assertSame(
            expected: Actions::NO_ACTION,
            actual: $indexingEntity->getNextAction(),
            message: 'expected: ' . Actions::NO_ACTION->value . ', actual: ' . $indexingEntity->getNextAction()->value,
        );
        $this->assertTrue(condition: $indexingEntity->getIsIndexable());
    }

    /**
     * @magentoDbIsolation disabled
     * @magentoConfigFixture klevu_test_store_1_store klevu_configuration/auth_keys/js_api_key klevu-js-api-key
     * @magentoConfigFixture klevu_test_store_1_store klevu_configuration/auth_keys/rest_auth_key klevu-rest-auth-key
     * @magentoConfigFixture default/klevu/indexing/exclude_disabled_products 1
     */
    public function testSavingConfigurable_DoesNotSetAllVariantsToDelete(): void
    {
        $apiKey = 'klevu-js-api-key';

        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');
        $store = $storeFixture->get();

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
            IndexingEntity::TARGET_ENTITY_SUBTYPE => 'configurable',
        ]);
        $this->createIndexingEntity([
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::TARGET_ID => $simple1ProductFixture->getId(),
            IndexingEntity::TARGET_PARENT_ID => $configurableProduct->getId(),
            IndexingEntity::NEXT_ACTION => Actions::NO_ACTION,
            IndexingEntity::LAST_ACTION => Actions::ADD,
            IndexingEntity::LAST_ACTION_TIMESTAMP => date('Y-m-d H:i:s'),
            IndexingEntity::TARGET_ENTITY_SUBTYPE => 'configurable_variants',
        ]);
        $this->createIndexingEntity([
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::TARGET_ID => $simple2ProductFixture->getId(),
            IndexingEntity::TARGET_PARENT_ID => $configurableProduct->getId(),
            IndexingEntity::NEXT_ACTION => Actions::NO_ACTION,
            IndexingEntity::LAST_ACTION => Actions::ADD,
            IndexingEntity::LAST_ACTION_TIMESTAMP => date('Y-m-d H:i:s'),
            IndexingEntity::TARGET_ENTITY_SUBTYPE => 'configurable_variants',
        ]);

        $configurableProduct->setName($configurableProduct->getName() . ' TEST');
        $productResourceModel = $this->objectManager->get(ProductResourceModel::class);
        $productResourceModel->save($configurableProduct);

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
            expected: Actions::NO_ACTION,
            actual: $simple1Variant->getNextAction(),
            message: sprintf(
                'Expected %s, Received %s',
                Actions::NO_ACTION->value,
                $simple1Variant->getNextAction()->value,
            ),
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
            message: sprintf(
                'Expected %s, Received %s',
                Actions::NO_ACTION->value,
                $simple1Variant->getNextAction()->value,
            ),
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
            expected: Actions::UPDATE,
            actual: $configurable->getNextAction(),
            message: sprintf(
                'Expected %s, Received %s',
                Actions::UPDATE->value,
                $simple1Variant->getNextAction()->value,
            ),
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
     * @magentoConfigFixture klevu_test_store_1_store klevu_configuration/auth_keys/js_api_key klevu-js-api-key
     * @magentoConfigFixture klevu_test_store_1_store klevu_configuration/auth_keys/rest_auth_key klevu-rest-auth-key
     * @magentoConfigFixture default/klevu/indexing/exclude_disabled_products 1
     */
    public function testSavingSimple_AlsoUpdatesVariants(): void
    {
        $apiKey = 'klevu-js-api-key';

        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');
        $store = $storeFixture->get();

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
        /** @var Product $simple1Product */
        $simple1Product = $simple1ProductFixture->getProduct();

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
            IndexingEntity::TARGET_ID => $simple1ProductFixture->getId(),
            IndexingEntity::TARGET_PARENT_ID => null,
            IndexingEntity::NEXT_ACTION => Actions::NO_ACTION,
            IndexingEntity::LAST_ACTION => Actions::ADD,
            IndexingEntity::LAST_ACTION_TIMESTAMP => date('Y-m-d H:i:s'),
            IndexingEntity::TARGET_ENTITY_SUBTYPE => 'simple',
        ]);
        $this->createIndexingEntity([
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::TARGET_ID => $simple2ProductFixture->getId(),
            IndexingEntity::TARGET_PARENT_ID => null,
            IndexingEntity::NEXT_ACTION => Actions::NO_ACTION,
            IndexingEntity::LAST_ACTION => Actions::ADD,
            IndexingEntity::LAST_ACTION_TIMESTAMP => date('Y-m-d H:i:s'),
            IndexingEntity::TARGET_ENTITY_SUBTYPE => 'simple',
        ]);
        $this->createIndexingEntity([
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::TARGET_ID => $configurableProduct->getId(),
            IndexingEntity::TARGET_PARENT_ID => null,
            IndexingEntity::NEXT_ACTION => Actions::NO_ACTION,
            IndexingEntity::LAST_ACTION => Actions::ADD,
            IndexingEntity::LAST_ACTION_TIMESTAMP => date('Y-m-d H:i:s'),
            IndexingEntity::TARGET_ENTITY_SUBTYPE => 'configurable',
        ]);
        $this->createIndexingEntity([
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::TARGET_ID => $simple1ProductFixture->getId(),
            IndexingEntity::TARGET_PARENT_ID => $configurableProduct->getId(),
            IndexingEntity::NEXT_ACTION => Actions::NO_ACTION,
            IndexingEntity::LAST_ACTION => Actions::ADD,
            IndexingEntity::LAST_ACTION_TIMESTAMP => date('Y-m-d H:i:s'),
            IndexingEntity::TARGET_ENTITY_SUBTYPE => 'configurable_variants',
        ]);
        $this->createIndexingEntity([
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::TARGET_ID => $simple2ProductFixture->getId(),
            IndexingEntity::TARGET_PARENT_ID => $configurableProduct->getId(),
            IndexingEntity::NEXT_ACTION => Actions::NO_ACTION,
            IndexingEntity::LAST_ACTION => Actions::ADD,
            IndexingEntity::LAST_ACTION_TIMESTAMP => date('Y-m-d H:i:s'),
            IndexingEntity::TARGET_ENTITY_SUBTYPE => 'configurable_variants',
        ]);

        $simple1Product->setName($simple1Product->getName() . ' TEST');
        $productResourceModel = $this->objectManager->get(ProductResourceModel::class);
        $productResourceModel->save($simple1Product);

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
            expected: Actions::UPDATE,
            actual: $simple1Variant->getNextAction(),
            message: sprintf(
                'Expected %s, Received %s',
                Actions::UPDATE->value,
                $simple1Variant->getNextAction()->value,
            ),
        );

        $simple1Products = array_filter(
            array: $indexingEntities,
            callback: static fn (IndexingEntityInterface $indexingEntity) => (
                (int)$indexingEntity->getTargetId() === (int)$simple1ProductFixture->getId()
                && $indexingEntity->getTargetParentId() === null
            ),
        );
        $this->assertCount(expectedCount: 1, haystack: $simple1Products);
        $simple1Product = array_shift($simple1Products);

        $this->assertSame(
            expected: Actions::UPDATE,
            actual: $simple1Product->getNextAction(),
            message: sprintf(
                'Expected %s, Received %s',
                Actions::UPDATE->value,
                $simple1Product->getNextAction()->value,
            ),
        );
    }

    /**
     * @return mixed[]|null
     */
    private function getSystemConfigPluginInfo(): ?array
    {
        /** @var PluginList $pluginList */
        $pluginList = $this->objectManager->get(PluginList::class);

        return $pluginList->get(ProductResourceModel::class, []);
    }

    /**
     * @param string $apiKey
     * @param ProductInterface $product
     *
     * @return IndexingEntityInterface|null
     * @throws \Exception
     */
    private function getIndexingEntityForProduct(
        string $apiKey,
        ProductInterface $product,
    ): ?IndexingEntityInterface {
        $productIndexingEntities = $this->getProductIndexingEntities($apiKey);
        $productIndexingEntityArray = array_filter(
            array: $productIndexingEntities,
            callback: static fn (IndexingEntityInterface $indexingEntity) => (
                (int)$indexingEntity->getTargetId() === (int)$product->getId()
            ),
        );

        return array_shift($productIndexingEntityArray);
    }

    /**
     * @return IndexingEntityInterface[]
     * @throws \Exception
     */
    private function getProductIndexingEntities(?string $apiKey = null): array
    {
        $collection = $this->objectManager->create(Collection::class);
        $collection->addFieldToFilter(
            field: IndexingEntity::TARGET_ENTITY_TYPE,
            condition: ['eq' => 'KLEVU_PRODUCT'],
        );
        if ($apiKey) {
            $collection->addFieldToFilter(
                field: IndexingEntity::API_KEY,
                condition: ['eq' => $apiKey],
            );
        }

        return $collection->getItems();
    }
}
