<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Test\Integration\Plugin\Catalog\Model\Product\Attribute;

use Klevu\Configuration\Service\Provider\ScopeProviderInterface;
use Klevu\Indexing\Model\IndexingEntity;
use Klevu\Indexing\Test\Integration\Traits\IndexingEntitiesTrait;
use Klevu\IndexingApi\Model\Source\Actions;
use Klevu\IndexingProducts\Plugin\Catalog\Model\Product\Attribute\ManagementPlugin;
use Klevu\TestFixtures\Catalog\Attribute\AttributeFixturePool;
use Klevu\TestFixtures\Catalog\AttributeTrait;
use Klevu\TestFixtures\Catalog\ProductTrait;
use Klevu\TestFixtures\Store\StoreFixturesPool;
use Klevu\TestFixtures\Store\StoreTrait;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\SetAuthKeysTrait;
use Magento\Catalog\Api\AttributeSetRepositoryInterface;
use Magento\Catalog\Api\Data\ProductAttributeInterface;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductAttributeManagementInterface;
use Magento\Catalog\Model\Config as CatalogConfig;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Attribute\Management as ProductAttributeManagement;
use Magento\Eav\Api\AttributeGroupRepositoryInterface;
use Magento\Eav\Api\Data\AttributeInterface;
use Magento\Eav\Api\Data\AttributeSetInterface;
use Magento\Eav\Model\Entity\Attribute\Group as AttributeGroup;
use Magento\Eav\Model\Entity\Attribute\GroupFactory as AttributeGroupFactory;
use Magento\Eav\Model\Entity\Attribute\Set as AttributeSet;
use Magento\Eav\Model\Entity\Attribute\SetFactory as AttributeSetFactory;
use Magento\Eav\Model\Entity\Type as EntityType;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Interception\PluginList\PluginList;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use TddWizard\Fixtures\Catalog\ProductFixturePool;

/**
 * @covers \Klevu\IndexingProducts\Plugin\Catalog\Model\Product\Attribute\ManagementPlugin::class
 * @method ManagementPlugin instantiateTestObject(?array $arguments = null)
 * @method ManagementPlugin instantiateTestObjectFromInterface(?array $arguments = null)
 */
class ManagementPluginTest extends TestCase
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
    private ?string $pluginName = 'Klevu_IndexingProducts::ProductAttributeManagementPlugin';
    /**
     * @var AttributeSetRepositoryInterface|null
     */
    private ?AttributeSetRepositoryInterface $attributeSetRepository = null;
    /**
     * @var AttributeGroupRepositoryInterface|null
     */
    private ?AttributeGroupRepositoryInterface $attributeGroupRepository = null;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->implementationFqcn = ManagementPlugin::class;
        $this->objectManager = Bootstrap::getObjectManager();
        $this->storeFixturesPool = $this->objectManager->get(StoreFixturesPool::class);
        $this->attributeFixturePool = $this->objectManager->get(AttributeFixturePool::class);
        $this->productFixturePool = $this->objectManager->get(ProductFixturePool::class);
        $this->attributeSetRepository = $this->objectManager->get(AttributeSetRepositoryInterface::class);
        $this->attributeGroupRepository = $this->objectManager->get(AttributeGroupRepositoryInterface::class);
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
        $this->assertSame(ManagementPlugin::class, $pluginInfo[$this->pluginName]['instance']);
    }

    /**
     * @magentoDbIsolation disabled
     * @magentoConfigFixture klevu_test_store_1_store klevu_configuration/auth_keys/js_api_key klevu-js-api-key
     * @magentoConfigFixture klevu_test_store_1_store klevu_configuration/auth_keys/rest_auth_key klevu-rest-auth-key
     */
    public function testAfterAssign_AllProductsInAttributeSetAreSetToUpdate(): void
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
            'code' => 'klevu_test_attribute_1',
            'key' => 'klevu_test_attribute_1',
        ]);
        $attributeFixture1 = $this->attributeFixturePool->get('klevu_test_attribute_1');
        /** @var ProductAttributeInterface $attribute1 */
        $attribute1 = $attributeFixture1->getAttribute();

        $this->createAttribute([
            'code' => 'klevu_test_attribute_2',
            'key' => 'klevu_test_attribute_2',
        ]);
        $attributeFixture2 = $this->attributeFixturePool->get('klevu_test_attribute_2');
        /** @var ProductAttributeInterface $attribute2 */
        $attribute2 = $attributeFixture2->getAttribute();

        $attributeSet = $this->createAttributeSet(attributes: [$attribute1]);
        $attributeSetId = $attributeSet->getAttributeSetId();

        $this->createProduct([
            'key' => 'test_product_1',
            'data' => [
                ProductInterface::ATTRIBUTE_SET_ID => $attributeSetId,
                $attribute1->getAttributeCode() => 'Some Text',
                $attribute2->getAttributeCode() => 'Other TEXT',
            ],
        ]);
        $productFixture1 = $this->productFixturePool->get('test_product_1');
        $this->createProduct([
            'key' => 'test_product_2',
        ]);
        $productFixture2 = $this->productFixturePool->get('test_product_2');

        $this->cleanIndexingEntities($apiKey);

        $this->createIndexingEntity([
            IndexingEntity::TARGET_ID => $productFixture1->getId(),
            IndexingEntity::TARGET_ENTITY_SUBTYPE => 'simple',
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::NEXT_ACTION => Actions::NO_ACTION,
            IndexingEntity::LAST_ACTION => Actions::ADD,
            IndexingEntity::IS_INDEXABLE => true,
        ]);
        $this->createIndexingEntity([
            IndexingEntity::TARGET_ID => $productFixture2->getId(),
            IndexingEntity::TARGET_ENTITY_SUBTYPE => 'simple',
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::NEXT_ACTION => Actions::NO_ACTION,
            IndexingEntity::LAST_ACTION => Actions::ADD,
            IndexingEntity::IS_INDEXABLE => true,
        ]);

        $attributeManagement = $this->objectManager->get(ProductAttributeManagementInterface::class);
        $attributeManagement->assign(
            attributeSetId: $attributeSetId,
            attributeGroupId: $this->getAttributeGroupId($attributeSetId),
            attributeCode: $attribute2->getAttributeCode(),
            sortOrder: 55,
        );

        $indexingEntity1 = $this->getIndexingEntityForEntity(
            apiKey: $apiKey,
            entity: $productFixture1->getProduct(),
            type: 'KLEVU_PRODUCT',
        );
        $this->assertTrue(condition: $indexingEntity1->getIsIndexable());
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
        $this->assertTrue(condition: $indexingEntity2->getIsIndexable());
        $this->assertSame(
            expected: Actions::NO_ACTION,
            actual: $indexingEntity2->getNextAction(),
            message: 'Expected ' . Actions::NO_ACTION->value . ', received ' . $indexingEntity2->getNextAction()->value,
        );

        $this->removeAttributeSet($attributeSet);
    }

    /**
     * @magentoDbIsolation disabled
     * @magentoConfigFixture klevu_test_store_1_store klevu_configuration/auth_keys/js_api_key klevu-js-api-key
     * @magentoConfigFixture klevu_test_store_1_store klevu_configuration/auth_keys/rest_auth_key klevu-rest-auth-key
     */
    public function testAfterUnassign_AllProductsInAttributeSetAreSetToUpdate(): void
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
            'code' => 'klevu_test_attribute_1',
            'key' => 'klevu_test_attribute_1',
        ]);
        $attributeFixture1 = $this->attributeFixturePool->get('klevu_test_attribute_1');
        /** @var ProductAttributeInterface $attribute1 */
        $attribute1 = $attributeFixture1->getAttribute();

        $this->createAttribute([
            'code' => 'klevu_test_attribute_2',
            'key' => 'klevu_test_attribute_2',
        ]);
        $attributeFixture2 = $this->attributeFixturePool->get('klevu_test_attribute_2');
        /** @var ProductAttributeInterface $attribute2 */
        $attribute2 = $attributeFixture2->getAttribute();

        $attributeSet = $this->createAttributeSet(attributes: [$attribute1, $attribute2]);
        $attributeSetId = $attributeSet->getAttributeSetId();

        $this->createProduct([
            'key' => 'test_product_1',
            'data' => [
                ProductInterface::ATTRIBUTE_SET_ID => $attributeSetId,
                $attribute1->getAttributeCode() => 'Some Text',
                $attribute2->getAttributeCode() => 'Other TEXT',
            ],
        ]);
        $productFixture1 = $this->productFixturePool->get('test_product_1');
        $this->createProduct([
            'key' => 'test_product_2',
        ]);
        $productFixture2 = $this->productFixturePool->get('test_product_2');

        $this->cleanIndexingEntities($apiKey);

        $this->createIndexingEntity([
            IndexingEntity::TARGET_ID => $productFixture1->getId(),
            IndexingEntity::TARGET_ENTITY_SUBTYPE => 'simple',
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::NEXT_ACTION => Actions::NO_ACTION,
            IndexingEntity::LAST_ACTION => Actions::ADD,
            IndexingEntity::IS_INDEXABLE => true,
        ]);
        $this->createIndexingEntity([
            IndexingEntity::TARGET_ID => $productFixture2->getId(),
            IndexingEntity::TARGET_ENTITY_SUBTYPE => 'simple',
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::NEXT_ACTION => Actions::NO_ACTION,
            IndexingEntity::LAST_ACTION => Actions::ADD,
            IndexingEntity::IS_INDEXABLE => true,
        ]);

        $attributeManagement = $this->objectManager->get(ProductAttributeManagementInterface::class);
        $attributeManagement->unassign(
            attributeSetId: $attributeSetId,
            attributeCode: $attribute2->getAttributeCode(),
        );

        $indexingEntity1 = $this->getIndexingEntityForEntity(
            apiKey: $apiKey,
            entity: $productFixture1->getProduct(),
            type: 'KLEVU_PRODUCT',
        );
        $this->assertTrue(condition: $indexingEntity1->getIsIndexable());
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
        $this->assertTrue(condition: $indexingEntity2->getIsIndexable());
        $this->assertSame(
            expected: Actions::NO_ACTION,
            actual: $indexingEntity2->getNextAction(),
            message: 'Expected ' . Actions::NO_ACTION->value . ', received ' . $indexingEntity2->getNextAction()->value,
        );

        $this->removeAttributeSet($attributeSet);
    }

    /**
     * @return mixed[]|null
     */
    private function getSystemConfigPluginInfo(): ?array
    {
        /** @var PluginList $pluginList */
        $pluginList = $this->objectManager->get(PluginList::class);

        return $pluginList->get(ProductAttributeManagement::class, []);
    }

    /**
     * @param AttributeInterface[] $attributes
     * @param mixed[] $data
     *
     * @return AttributeSet
     * @throws LocalizedException
     */
    private function createAttributeSet(array $attributes, ?array $data = []): AttributeSet
    {
        /** @var Product $product */
        $product = $this->objectManager->get(ProductInterface::class);
        $defaultAttributeSetId = $product->getDefaultAttributeSetId();
        $entityType = $this->objectManager->get(EntityType::class);
        $entityType->loadByCode('catalog_product');
        $attributeSetData = [
            'attribute_set_name' => $data['attribute_set_name'] ?? 'KlevuTestAttributeSet',
            'entity_type_id' => $data['entity_type_id'] ?? $entityType->getEntityTypeId(),
            'sort_order' => $data['sort_order'] ?? 200,
        ];

        $attributeSetFactory = $this->objectManager->get(AttributeSetFactory::class);
        /** @var AttributeSet $attributeSet */
        $attributeSet = $attributeSetFactory->create();
        $attributeSet->setData($attributeSetData);
        $attributeSet->validate();
        $this->attributeSetRepository->save($attributeSet);
        $attributeSet->initFromSkeleton($defaultAttributeSetId);
        $savedAttributeSet = $this->attributeSetRepository->save($attributeSet);

        $attributeSetId = $savedAttributeSet->getAttributeSetId();

        $attributeGroupFactory = $this->objectManager->get(AttributeGroupFactory::class);
        /** @var AttributeGroup $attributeGroup */
        $attributeGroup = $attributeGroupFactory->create();
        $attributeGroup->setAttributeSetId($attributeSetId);
        $attributeGroup->setAttributeGroupName('Klevu Group');
        $this->attributeGroupRepository->save($attributeGroup);

        $attributeGroupId = $this->getAttributeGroupId($attributeSetId);
        $attributeManagement = $this->objectManager->get(ProductAttributeManagementInterface::class);
        foreach ($attributes as $attribute) {
            $attributeManagement->assign(
                attributeSetId: $attributeSetId,
                attributeGroupId: $attributeGroupId,
                attributeCode: $attribute->getAttributeCode(),
                sortOrder: 99,
            );
        }

        return $attributeSet;
    }

    /**
     * @param AttributeSetInterface $attributeSet
     *
     * @return void
     * @throws InputException
     * @throws NoSuchEntityException
     */
    public function removeAttributeSet(AttributeSetInterface $attributeSet): void
    {
        $attributeSetRepository = $this->objectManager->get(AttributeSetRepositoryInterface::class);
        $attributeSetRepository->delete($attributeSet);
    }

    /**
     * @param mixed $attributeSetId
     *
     * @return bool|float|int|string
     */
    private function getAttributeGroupId(mixed $attributeSetId): string|int|bool|float
    {
        $config = $this->objectManager->create(CatalogConfig::class);

        return $config->getAttributeGroupId($attributeSetId, 'Klevu Group');
    }
}
