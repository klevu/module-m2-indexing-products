<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Test\Integration\Plugin\Catalog\Model\ResourceModel;

use Klevu\Configuration\Service\Provider\ScopeProviderInterface;
use Klevu\Indexing\Model\IndexingEntity;
use Klevu\Indexing\Test\Integration\Traits\IndexingEntitiesTrait;
use Klevu\IndexingApi\Model\MagentoAttributeInterface;
use Klevu\IndexingApi\Model\Source\Actions;
use Klevu\IndexingApi\Model\Source\IndexType;
use Klevu\IndexingProducts\Model\Source\Aspect;
use Klevu\IndexingProducts\Plugin\Catalog\Model\ResourceModel\TriggerEntityUpdateOnAttributeSavePlugin;
use Klevu\TestFixtures\Catalog\Attribute\AttributeFixturePool;
use Klevu\TestFixtures\Catalog\AttributeTrait;
use Klevu\TestFixtures\Catalog\ProductTrait;
use Klevu\TestFixtures\Store\StoreFixturesPool;
use Klevu\TestFixtures\Store\StoreTrait;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\SetAuthKeysTrait;
use Magento\Eav\Api\Data\AttributeInterface;
use Magento\Eav\Model\Entity\Attribute\AbstractAttribute;
use Magento\Eav\Model\ResourceModel\Entity\Attribute as AttributeEavResourceModel;
use Magento\Framework\DataObject;
use Magento\Framework\Interception\PluginList\PluginList;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use TddWizard\Fixtures\Catalog\ProductFixturePool;

/**
 * @covers TriggerEntityUpdateOnAttributeSavePlugin::class
 * @method TriggerEntityUpdateOnAttributeSavePlugin instantiateTestObject(?array $arguments = null)
 * @method TriggerEntityUpdateOnAttributeSavePlugin instantiateTestObjectFromInterface(?array $arguments = null)
 */
class TriggerEntityUpdateOnAttributeSavePluginTest extends TestCase
{
    use AttributeTrait;
    use IndexingEntitiesTrait;
    use ObjectInstantiationTrait;
    use ProductTrait;
    use SetAuthKeysTrait;
    use StoreTrait;

    /**
     * @var string|null
     */
    private ?string $pluginName = 'Klevu_IndexingProducts::TriggerEntityUpdateOnAttributeSavePlugin';
    /**
     * @var ObjectManagerInterface|null
     */
    private ?ObjectManagerInterface $objectManager = null; // @phpstan-ignore-line
    /**
     * @var AttributeEavResourceModel|null
     */
    private ?AttributeEavResourceModel $resourceModel = null;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->implementationFqcn = TriggerEntityUpdateOnAttributeSavePlugin::class;
        $this->objectManager = Bootstrap::getObjectManager();
        $this->resourceModel = $this->objectManager->get(AttributeEavResourceModel::class);
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
        $this->assertSame(
            expected: TriggerEntityUpdateOnAttributeSavePlugin::class,
            actual: $pluginInfo[$this->pluginName]['instance'],
        );
    }

    /**
     * @return mixed[]|null
     */
    private function getSystemConfigPluginInfo(): ?array
    {
        /** @var PluginList $pluginList */
        $pluginList = $this->objectManager->get(PluginList::class);

        return $pluginList->get(AttributeEavResourceModel::class, []);
    }

    /**
     * @magentoDbIsolation disabled
     */
    public function testAttributeSave_DoesNothing_WhenNoDataChanges(): void
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

        $this->createAttribute([
            'index_as' => IndexType::INDEX,
            'generate_config_for' => [
                'simple',
            ],
            'aspect' => Aspect::ATTRIBUTES,
        ]);
        $attributeFixture = $this->attributeFixturePool->get('test_attribute');
        /** @var AttributeInterface&DataObject $attribute */
        $attribute = $attributeFixture->getAttribute();

        $this->createProduct([
            'data' => [
                $attribute->getAttributeCode() => 'Some Value',
            ],
        ]);
        $productFixture1 = $this->productFixturePool->get('test_product');

        $this->cleanIndexingEntities($apiKey);
        $this->createIndexingEntity([
            IndexingEntity::TARGET_ID => $productFixture1->getId(),
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::TARGET_ENTITY_SUBTYPE => 'simple',
            IndexingEntity::NEXT_ACTION => Actions::NO_ACTION,
            IndexingEntity::LAST_ACTION => Actions::ADD,
            IndexingEntity::LAST_ACTION_TIMESTAMP => date('Y-m-d H:i:s'),
            IndexingEntity::IS_INDEXABLE => true,
        ]);
        $this->resourceModel->save($attribute); // @phpstan-ignore-line

        $indexingEntity1 = $this->getIndexingEntityForEntity(
            apiKey: $apiKey,
            entity: $productFixture1->getProduct(),
            type: 'KLEVU_PRODUCT',
        );
        $this->assertSame(
            expected: Actions::NO_ACTION,
            actual: $indexingEntity1->getNextAction(),
            message: 'Expected ' . Actions::NO_ACTION->value . ', received ' . $indexingEntity1->getNextAction()->value,
        );
    }

    /**
     * @magentoDbIsolation disabled
     */
    public function testAttributeSave_AddEntitySubTypes(): void
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

        $this->createAttribute([
            'index_as' => IndexType::INDEX,
            'generate_config_for' => [
                'configurable_variants',
            ],
            'aspect' => Aspect::ATTRIBUTES,
        ]);
        $attributeFixture = $this->attributeFixturePool->get('test_attribute');
        /** @var AttributeInterface&DataObject $attribute */
        $attribute = $attributeFixture->getAttribute();

        $this->createProduct([
            'data' => [
                $attribute->getAttributeCode() => 'Some Value',
            ],
        ]);
        $productFixture1 = $this->productFixturePool->get('test_product');

        $this->createProduct([
            'key' => 'test_product_2',
            'type_id' => 'virtual',
            'data' => [
                $attribute->getAttributeCode() => 'Some Value',
            ],
        ]);
        $productFixture2 = $this->productFixturePool->get('test_product_2');

        $this->cleanIndexingEntities($apiKey);
        $this->createIndexingEntity([
            IndexingEntity::TARGET_ID => $productFixture1->getId(),
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::TARGET_ENTITY_SUBTYPE => 'simple',
            IndexingEntity::NEXT_ACTION => Actions::NO_ACTION,
            IndexingEntity::LAST_ACTION => Actions::ADD,
            IndexingEntity::LAST_ACTION_TIMESTAMP => date('Y-m-d H:i:s'),
            IndexingEntity::IS_INDEXABLE => true,
        ]);
        $this->createIndexingEntity([
            IndexingEntity::TARGET_ID => $productFixture2->getId(),
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::TARGET_ENTITY_SUBTYPE => 'virtual',
            IndexingEntity::NEXT_ACTION => Actions::NO_ACTION,
            IndexingEntity::LAST_ACTION => Actions::ADD,
            IndexingEntity::LAST_ACTION_TIMESTAMP => date('Y-m-d H:i:s'),
            IndexingEntity::IS_INDEXABLE => true,
        ]);

        $attribute->setData(
            MagentoAttributeInterface::ATTRIBUTE_PROPERTY_GENERATE_CONFIGURATION_FOR_ENTITY_SUBTYPES,
            ['simple', 'configurable_variants'],
        );
        $this->resourceModel->save($attribute); // @phpstan-ignore-line

        $indexingEntity1 = $this->getIndexingEntityForEntity(
            apiKey: $apiKey,
            entity: $productFixture1->getProduct(),
            type: 'KLEVU_PRODUCT',
        );
        $this->assertSame(
            expected: Actions::UPDATE,
            actual: $indexingEntity1->getNextAction(),
            message: 'Expected ' . Actions::UPDATE->value . ', received ' . $indexingEntity1->getNextAction()->value,
        );

        $indexingEntity2 = $this->getIndexingEntityForEntity(
            apiKey: $apiKey,
            entity: $productFixture2->getProduct(),
            type: 'KLEVU_PRODUCT',
        );
        $this->assertSame(
            expected: Actions::NO_ACTION,
            actual: $indexingEntity2->getNextAction(),
            message: 'Expected ' . Actions::NO_ACTION->value . ', received ' . $indexingEntity2->getNextAction()->value,
        );
    }

    /**
     * @magentoDbIsolation disabled
     */
    public function testAttributeSave_RemoveEntitySubTypes(): void
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

        $this->createAttribute([
            'index_as' => IndexType::INDEX,
            'generate_config_for' => [
                'simple',
                'virtual',
                'downloadable',
                'configurable_variants',
            ],
            'aspect' => Aspect::ATTRIBUTES,
        ]);
        $attributeFixture = $this->attributeFixturePool->get('test_attribute');
        /** @var AttributeInterface&DataObject $attribute */
        $attribute = $attributeFixture->getAttribute();

        $this->createProduct([
            'data' => [
                $attribute->getAttributeCode() => 'Some Value',
            ],
        ]);
        $productFixture1 = $this->productFixturePool->get('test_product');

        $this->createProduct([
            'key' => 'test_product_2',
            'type_id' => 'virtual',
            'data' => [
                $attribute->getAttributeCode() => 'Some Value',
            ],
        ]);
        $productFixture2 = $this->productFixturePool->get('test_product_2');

        $this->createProduct([
            'key' => 'test_product_3',
            'type_id' => 'downloadable',
            'data' => [
                $attribute->getAttributeCode() => 'Some Value',
            ],
        ]);
        $productFixture3 = $this->productFixturePool->get('test_product_3');

        $this->cleanIndexingEntities($apiKey);
        $this->createIndexingEntity([
            IndexingEntity::TARGET_ID => $productFixture1->getId(),
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::TARGET_ENTITY_SUBTYPE => 'simple',
            IndexingEntity::NEXT_ACTION => Actions::NO_ACTION,
            IndexingEntity::LAST_ACTION => Actions::ADD,
            IndexingEntity::LAST_ACTION_TIMESTAMP => date('Y-m-d H:i:s'),
            IndexingEntity::IS_INDEXABLE => true,
        ]);
        $this->createIndexingEntity([
            IndexingEntity::TARGET_ID => $productFixture2->getId(),
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::TARGET_ENTITY_SUBTYPE => 'virtual',
            IndexingEntity::NEXT_ACTION => Actions::NO_ACTION,
            IndexingEntity::LAST_ACTION => Actions::ADD,
            IndexingEntity::LAST_ACTION_TIMESTAMP => date('Y-m-d H:i:s'),
            IndexingEntity::IS_INDEXABLE => true,
        ]);
        $this->createIndexingEntity([
            IndexingEntity::TARGET_ID => $productFixture3->getId(),
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::TARGET_ENTITY_SUBTYPE => 'downloadable',
            IndexingEntity::NEXT_ACTION => Actions::NO_ACTION,
            IndexingEntity::LAST_ACTION => Actions::ADD,
            IndexingEntity::LAST_ACTION_TIMESTAMP => date('Y-m-d H:i:s'),
            IndexingEntity::IS_INDEXABLE => true,
        ]);

        $attribute->setData(
            MagentoAttributeInterface::ATTRIBUTE_PROPERTY_GENERATE_CONFIGURATION_FOR_ENTITY_SUBTYPES,
            ['simple', 'configurable_variants'],
        );
        $this->resourceModel->save($attribute); // @phpstan-ignore-line

        $indexingEntity1 = $this->getIndexingEntityForEntity(
            apiKey: $apiKey,
            entity: $productFixture1->getProduct(),
            type: 'KLEVU_PRODUCT',
        );
        $this->assertSame(
            expected: Actions::NO_ACTION,
            actual: $indexingEntity1->getNextAction(),
            message: 'Expected ' . Actions::NO_ACTION->value . ', received ' . $indexingEntity1->getNextAction()->value,
        );

        $indexingEntity2 = $this->getIndexingEntityForEntity(
            apiKey: $apiKey,
            entity: $productFixture2->getProduct(),
            type: 'KLEVU_PRODUCT',
        );
        $this->assertSame(
            expected: Actions::UPDATE,
            actual: $indexingEntity2->getNextAction(),
            message: 'Expected ' . Actions::UPDATE->value . ', received ' . $indexingEntity2->getNextAction()->value,
        );

        $indexingEntity3 = $this->getIndexingEntityForEntity(
            apiKey: $apiKey,
            entity: $productFixture3->getProduct(),
            type: 'KLEVU_PRODUCT',
        );
        $this->assertSame(
            expected: Actions::UPDATE,
            actual: $indexingEntity3->getNextAction(),
            message: 'Expected ' . Actions::UPDATE->value . ', received ' . $indexingEntity3->getNextAction()->value,
        );
    }

    /**
     * @magentoDbIsolation disabled
     */
    public function testAttributeSave_EnableRegisterWithKlevu(): void
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

        $this->createAttribute([
            'index_as' => IndexType::NO_INDEX,
            'generate_config_for' => [
                'simple',
                'virtual',
            ],
            'aspect' => Aspect::ATTRIBUTES,
        ]);
        $attributeFixture = $this->attributeFixturePool->get('test_attribute');
        /** @var AttributeInterface&DataObject $attribute */
        $attribute = $attributeFixture->getAttribute();

        $this->createProduct([
            'data' => [
                $attribute->getAttributeCode() => 'Some Value',
            ],
        ]);
        $productFixture1 = $this->productFixturePool->get('test_product');

        $this->createProduct([
            'key' => 'test_product_2',
            'type_id' => 'virtual',
            'data' => [
                $attribute->getAttributeCode() => 'Some Other Value',
            ],
        ]);
        $productFixture2 = $this->productFixturePool->get('test_product_2');

        $this->createProduct([
            'key' => 'test_product_3',
            'type_id' => 'downloadable',
            'data' => [
                $attribute->getAttributeCode() => 'Another Value',
            ],
        ]);
        $productFixture3 = $this->productFixturePool->get('test_product_3');

        $this->cleanIndexingEntities($apiKey);
        $this->createIndexingEntity([
            IndexingEntity::TARGET_ID => $productFixture1->getId(),
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::TARGET_ENTITY_SUBTYPE => 'simple',
            IndexingEntity::NEXT_ACTION => Actions::NO_ACTION,
            IndexingEntity::LAST_ACTION => Actions::ADD,
            IndexingEntity::LAST_ACTION_TIMESTAMP => date('Y-m-d H:i:s'),
            IndexingEntity::IS_INDEXABLE => true,
        ]);
        $this->createIndexingEntity([
            IndexingEntity::TARGET_ID => $productFixture2->getId(),
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::TARGET_ENTITY_SUBTYPE => 'virtual',
            IndexingEntity::NEXT_ACTION => Actions::NO_ACTION,
            IndexingEntity::LAST_ACTION => Actions::ADD,
            IndexingEntity::LAST_ACTION_TIMESTAMP => date('Y-m-d H:i:s'),
            IndexingEntity::IS_INDEXABLE => true,
        ]);
        $this->createIndexingEntity([
            IndexingEntity::TARGET_ID => $productFixture3->getId(),
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::TARGET_ENTITY_SUBTYPE => 'downloadable',
            IndexingEntity::NEXT_ACTION => Actions::NO_ACTION,
            IndexingEntity::LAST_ACTION => Actions::ADD,
            IndexingEntity::LAST_ACTION_TIMESTAMP => date('Y-m-d H:i:s'),
            IndexingEntity::IS_INDEXABLE => true,
        ]);

        $attribute->setData(
            MagentoAttributeInterface::ATTRIBUTE_PROPERTY_IS_INDEXABLE,
            IndexType::INDEX->value,
        );
        $this->resourceModel->save($attribute); // @phpstan-ignore-line

        $indexingEntity1 = $this->getIndexingEntityForEntity(
            apiKey: $apiKey,
            entity: $productFixture1->getProduct(),
            type: 'KLEVU_PRODUCT',
        );
        $this->assertSame(
            expected: Actions::UPDATE,
            actual: $indexingEntity1->getNextAction(),
            message: 'Expected ' . Actions::UPDATE->value . ', received ' . $indexingEntity1->getNextAction()->value,
        );

        $indexingEntity2 = $this->getIndexingEntityForEntity(
            apiKey: $apiKey,
            entity: $productFixture2->getProduct(),
            type: 'KLEVU_PRODUCT',
        );
        $this->assertSame(
            expected: Actions::UPDATE,
            actual: $indexingEntity2->getNextAction(),
            message: 'Expected ' . Actions::UPDATE->value . ', received ' . $indexingEntity2->getNextAction()->value,
        );

        $indexingEntity3 = $this->getIndexingEntityForEntity(
            apiKey: $apiKey,
            entity: $productFixture3->getProduct(),
            type: 'KLEVU_PRODUCT',
        );
        $this->assertSame(
            expected: Actions::NO_ACTION,
            actual: $indexingEntity3->getNextAction(),
            message: 'Expected ' . Actions::NO_ACTION->value . ', received ' . $indexingEntity3->getNextAction()->value,
        );
    }

    /**
     * @magentoDbIsolation disabled
     */
    public function testAttributeSave_DisableRegisterWithKlevu(): void
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

        $this->createAttribute([
            'index_as' => IndexType::INDEX,
            'generate_config_for' => [
                'simple',
                'configurable',
            ],
            'aspect' => Aspect::ATTRIBUTES,
        ]);
        $attributeFixture = $this->attributeFixturePool->get('test_attribute');
        /** @var AttributeInterface&DataObject $attribute */
        $attribute = $attributeFixture->getAttribute();

        $this->createProduct([
            'data' => [
                $attribute->getAttributeCode() => 'Some Value',
            ],
        ]);
        $productFixture1 = $this->productFixturePool->get('test_product');

        $this->createProduct([
            'key' => 'test_product_2',
            'type_id' => 'grouped',
            'data' => [
                $attribute->getAttributeCode() => 'Some Other Value',
            ],
        ]);
        $productFixture2 = $this->productFixturePool->get('test_product_2');

        $this->createAttribute([
            'key' => 'configurable_attribute',
            'code' => 'klevu_configurable_attribute',
            'attribute_type' => 'configurable',
        ]);
        $configurableAttributeFixture = $this->attributeFixturePool->get('configurable_attribute');
        /** @var AbstractAttribute&\Magento\Eav\Model\Entity\Attribute\AttributeInterface $configurableAttribute */
        $configurableAttribute = $configurableAttributeFixture->getAttribute();
        $attributeSource = $configurableAttribute->getSource();

        $this->createProduct([
            'key' => 'variant_product_1',
            'data' => [
                $configurableAttributeFixture->getAttributeCode() => $attributeSource->getOptionId('Option 1'),
            ],
        ]);
        $variantProductFixture = $this->productFixturePool->get('variant_product_1');
        $this->createProduct([
            'key' => 'test_product_3',
            'type_id' => 'configurable',
            'data' => [
                $attributeFixture->getAttributeCode() => 'Another Value',
            ],
        ]);
        $productFixture3 = $this->productFixturePool->get('test_product_3');

        $this->cleanIndexingEntities($apiKey);
        $this->createIndexingEntity([
            IndexingEntity::TARGET_ID => $productFixture1->getId(),
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::TARGET_ENTITY_SUBTYPE => 'simple',
            IndexingEntity::NEXT_ACTION => Actions::NO_ACTION,
            IndexingEntity::LAST_ACTION => Actions::ADD,
            IndexingEntity::LAST_ACTION_TIMESTAMP => date('Y-m-d H:i:s'),
            IndexingEntity::IS_INDEXABLE => true,
        ]);
        $this->createIndexingEntity([
            IndexingEntity::TARGET_ID => $productFixture2->getId(),
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::TARGET_ENTITY_SUBTYPE => 'grouped',
            IndexingEntity::NEXT_ACTION => Actions::NO_ACTION,
            IndexingEntity::LAST_ACTION => Actions::ADD,
            IndexingEntity::LAST_ACTION_TIMESTAMP => date('Y-m-d H:i:s'),
            IndexingEntity::IS_INDEXABLE => true,
        ]);
        $this->createIndexingEntity([
            IndexingEntity::TARGET_ID => $productFixture3->getId(),
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::TARGET_ENTITY_SUBTYPE => 'configurable',
            IndexingEntity::NEXT_ACTION => Actions::NO_ACTION,
            IndexingEntity::LAST_ACTION => Actions::ADD,
            IndexingEntity::LAST_ACTION_TIMESTAMP => date('Y-m-d H:i:s'),
            IndexingEntity::IS_INDEXABLE => true,
        ]);
        $this->createIndexingEntity([
            IndexingEntity::TARGET_ID => $variantProductFixture->getId(),
            IndexingEntity::TARGET_PARENT_ID => $productFixture3->getId(),
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::TARGET_ENTITY_SUBTYPE => 'configurable_variants',
            IndexingEntity::NEXT_ACTION => Actions::NO_ACTION,
            IndexingEntity::LAST_ACTION => Actions::ADD,
            IndexingEntity::LAST_ACTION_TIMESTAMP => date('Y-m-d H:i:s'),
            IndexingEntity::IS_INDEXABLE => true,
        ]);

        $attribute->setData(
            MagentoAttributeInterface::ATTRIBUTE_PROPERTY_IS_INDEXABLE,
            IndexType::NO_INDEX->value,
        );
        $this->resourceModel->save($attribute); // @phpstan-ignore-line

        $indexingEntity1 = $this->getIndexingEntityForEntity(
            apiKey: $apiKey,
            entity: $productFixture1->getProduct(),
            type: 'KLEVU_PRODUCT',
        );
        $this->assertSame(
            expected: Actions::UPDATE,
            actual: $indexingEntity1->getNextAction(),
            message: 'Expected ' . Actions::UPDATE->value . ', received ' . $indexingEntity1->getNextAction()->value,
        );

        $indexingEntity2 = $this->getIndexingEntityForEntity(
            apiKey: $apiKey,
            entity: $productFixture2->getProduct(),
            type: 'KLEVU_PRODUCT',
        );
        $this->assertSame(
            expected: Actions::NO_ACTION,
            actual: $indexingEntity2->getNextAction(),
            message: 'Expected ' . Actions::NO_ACTION->value . ', received ' . $indexingEntity2->getNextAction()->value,
        );

        $indexingEntity3 = $this->getIndexingEntityForEntity(
            apiKey: $apiKey,
            entity: $productFixture3->getProduct(),
            type: 'KLEVU_PRODUCT',
        );
        $this->assertSame(
            expected: Actions::UPDATE,
            actual: $indexingEntity3->getNextAction(),
            message: 'Expected ' . Actions::UPDATE->value . ', received ' . $indexingEntity3->getNextAction()->value,
        );

        $indexingEntity4 = $this->getIndexingEntityForVariant(
            apiKey: $apiKey,
            entity: $variantProductFixture->getProduct(),
            parentEntity: $productFixture3->getProduct(),
            type: 'KLEVU_PRODUCT',
        );
        $this->assertSame(
            expected: Actions::NO_ACTION,
            actual: $indexingEntity4->getNextAction(),
            message: 'Expected ' . Actions::NO_ACTION->value . ', received ' . $indexingEntity4->getNextAction()->value,
        );
    }

    /**
     * @magentoDbIsolation disabled
     */
    public function testAttributeSave_EnableRegisterWithKlevu_UpdateSubTypesChanges(): void
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

        $this->createAttribute([
            'index_as' => IndexType::NO_INDEX,
            'generate_config_for' => [
                'simple',
            ],
            'aspect' => Aspect::ATTRIBUTES,
        ]);
        $attributeFixture = $this->attributeFixturePool->get('test_attribute');
        /** @var AttributeInterface&DataObject $attribute */
        $attribute = $attributeFixture->getAttribute();

        $this->createProduct([
            'data' => [
                $attribute->getAttributeCode() => 'Some Value',
            ],
        ]);
        $productFixture1 = $this->productFixturePool->get('test_product');

        $this->createProduct([
            'key' => 'test_product_2',
            'type_id' => 'virtual',
            'data' => [
                $attribute->getAttributeCode() => 'Some Other Value',
            ],
        ]);
        $productFixture2 = $this->productFixturePool->get('test_product_2');

        $this->createProduct([
            'key' => 'test_product_3',
            'type_id' => 'grouped',
            'data' => [
                $attribute->getAttributeCode() => 'Another Value',
            ],
        ]);
        $productFixture3 = $this->productFixturePool->get('test_product_3');

        $this->cleanIndexingEntities($apiKey);
        $this->createIndexingEntity([
            IndexingEntity::TARGET_ID => $productFixture1->getId(),
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::TARGET_ENTITY_SUBTYPE => 'simple',
            IndexingEntity::NEXT_ACTION => Actions::NO_ACTION,
            IndexingEntity::LAST_ACTION => Actions::ADD,
            IndexingEntity::LAST_ACTION_TIMESTAMP => date('Y-m-d H:i:s'),
            IndexingEntity::IS_INDEXABLE => true,
        ]);
        $this->createIndexingEntity([
            IndexingEntity::TARGET_ID => $productFixture2->getId(),
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::TARGET_ENTITY_SUBTYPE => 'virtual',
            IndexingEntity::NEXT_ACTION => Actions::NO_ACTION,
            IndexingEntity::LAST_ACTION => Actions::ADD,
            IndexingEntity::LAST_ACTION_TIMESTAMP => date('Y-m-d H:i:s'),
            IndexingEntity::IS_INDEXABLE => true,
        ]);
        $this->createIndexingEntity([
            IndexingEntity::TARGET_ID => $productFixture3->getId(),
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::TARGET_ENTITY_SUBTYPE => 'grouped',
            IndexingEntity::NEXT_ACTION => Actions::NO_ACTION,
            IndexingEntity::LAST_ACTION => Actions::ADD,
            IndexingEntity::LAST_ACTION_TIMESTAMP => date('Y-m-d H:i:s'),
            IndexingEntity::IS_INDEXABLE => true,
        ]);

        $attribute->setData(
            MagentoAttributeInterface::ATTRIBUTE_PROPERTY_IS_INDEXABLE,
            IndexType::INDEX->value,
        );
        $attribute->setData(
            MagentoAttributeInterface::ATTRIBUTE_PROPERTY_GENERATE_CONFIGURATION_FOR_ENTITY_SUBTYPES,
            ['simple', 'virtual', 'grouped'],
        );
        $this->resourceModel->save($attribute); // @phpstan-ignore-line

        $indexingEntity1 = $this->getIndexingEntityForEntity(
            apiKey: $apiKey,
            entity: $productFixture1->getProduct(),
            type: 'KLEVU_PRODUCT',
        );
        $this->assertSame(
            expected: Actions::UPDATE,
            actual: $indexingEntity1->getNextAction(),
            message: 'Expected ' . Actions::UPDATE->value . ', received ' . $indexingEntity1->getNextAction()->value,
        );

        $indexingEntity2 = $this->getIndexingEntityForEntity(
            apiKey: $apiKey,
            entity: $productFixture2->getProduct(),
            type: 'KLEVU_PRODUCT',
        );
        $this->assertSame(
            expected: Actions::UPDATE,
            actual: $indexingEntity2->getNextAction(),
            message: 'Expected ' . Actions::UPDATE->value . ', received ' . $indexingEntity2->getNextAction()->value,
        );

        $indexingEntity3 = $this->getIndexingEntityForEntity(
            apiKey: $apiKey,
            entity: $productFixture3->getProduct(),
            type: 'KLEVU_PRODUCT',
        );
        $this->assertSame(
            expected: Actions::UPDATE,
            actual: $indexingEntity3->getNextAction(),
            message: 'Expected ' . Actions::UPDATE->value . ', received ' . $indexingEntity3->getNextAction()->value,
        );
    }

    /**
     * @magentoDbIsolation disabled
     */
    public function testAttributeSave_DisableRegisterWithKlevu_UpdateSubTypesChanges(): void
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

        $this->createAttribute([
            'index_as' => IndexType::INDEX,
            'generate_config_for' => [
                'simple',
                'virtual',
                'grouped',
            ],
            'aspect' => Aspect::ATTRIBUTES,
        ]);
        $attributeFixture = $this->attributeFixturePool->get('test_attribute');
        /** @var AttributeInterface&DataObject $attribute */
        $attribute = $attributeFixture->getAttribute();

        $this->createProduct([
            'data' => [
                $attribute->getAttributeCode() => 'Some Value',
            ],
        ]);
        $productFixture1 = $this->productFixturePool->get('test_product');

        $this->createProduct([
            'key' => 'test_product_2',
            'type_id' => 'virtual',
            'data' => [
                $attribute->getAttributeCode() => 'Some Other Value',
            ],
        ]);
        $productFixture2 = $this->productFixturePool->get('test_product_2');

        $this->createProduct([
            'key' => 'test_product_3',
            'type_id' => 'grouped',
            'data' => [
                $attribute->getAttributeCode() => 'Another Value',
            ],
        ]);
        $productFixture3 = $this->productFixturePool->get('test_product_3');

        $this->cleanIndexingEntities($apiKey);
        $this->createIndexingEntity([
            IndexingEntity::TARGET_ID => $productFixture1->getId(),
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::TARGET_ENTITY_SUBTYPE => 'simple',
            IndexingEntity::NEXT_ACTION => Actions::NO_ACTION,
            IndexingEntity::LAST_ACTION => Actions::ADD,
            IndexingEntity::LAST_ACTION_TIMESTAMP => date('Y-m-d H:i:s'),
            IndexingEntity::IS_INDEXABLE => true,
        ]);
        $this->createIndexingEntity([
            IndexingEntity::TARGET_ID => $productFixture2->getId(),
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::TARGET_ENTITY_SUBTYPE => 'virtual',
            IndexingEntity::NEXT_ACTION => Actions::NO_ACTION,
            IndexingEntity::LAST_ACTION => Actions::ADD,
            IndexingEntity::LAST_ACTION_TIMESTAMP => date('Y-m-d H:i:s'),
            IndexingEntity::IS_INDEXABLE => true,
        ]);
        $this->createIndexingEntity([
            IndexingEntity::TARGET_ID => $productFixture3->getId(),
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::TARGET_ENTITY_SUBTYPE => 'grouped',
            IndexingEntity::NEXT_ACTION => Actions::NO_ACTION,
            IndexingEntity::LAST_ACTION => Actions::ADD,
            IndexingEntity::LAST_ACTION_TIMESTAMP => date('Y-m-d H:i:s'),
            IndexingEntity::IS_INDEXABLE => true,
        ]);

        $attribute->setData(
            MagentoAttributeInterface::ATTRIBUTE_PROPERTY_IS_INDEXABLE,
            IndexType::NO_INDEX->value,
        );
        $attribute->setData(
            MagentoAttributeInterface::ATTRIBUTE_PROPERTY_GENERATE_CONFIGURATION_FOR_ENTITY_SUBTYPES,
            ['grouped'],
        );
        $this->resourceModel->save($attribute); // @phpstan-ignore-line

        $indexingEntity1 = $this->getIndexingEntityForEntity(
            apiKey: $apiKey,
            entity: $productFixture1->getProduct(),
            type: 'KLEVU_PRODUCT',
        );
        $this->assertSame(
            expected: Actions::UPDATE,
            actual: $indexingEntity1->getNextAction(),
            message: 'Expected ' . Actions::UPDATE->value . ', received ' . $indexingEntity1->getNextAction()->value,
        );

        $indexingEntity2 = $this->getIndexingEntityForEntity(
            apiKey: $apiKey,
            entity: $productFixture2->getProduct(),
            type: 'KLEVU_PRODUCT',
        );
        $this->assertSame(
            expected: Actions::UPDATE,
            actual: $indexingEntity2->getNextAction(),
            message: 'Expected ' . Actions::UPDATE->value . ', received ' . $indexingEntity2->getNextAction()->value,
        );

        $indexingEntity3 = $this->getIndexingEntityForEntity(
            apiKey: $apiKey,
            entity: $productFixture3->getProduct(),
            type: 'KLEVU_PRODUCT',
        );
        $this->assertSame(
            expected: Actions::UPDATE,
            actual: $indexingEntity3->getNextAction(),
            message: 'Expected ' . Actions::UPDATE->value . ', received ' . $indexingEntity3->getNextAction()->value,
        );
    }
}
