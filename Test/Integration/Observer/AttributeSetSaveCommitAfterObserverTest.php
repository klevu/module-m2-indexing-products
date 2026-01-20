<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Test\Integration\Observer;

use Klevu\Indexing\Model\IndexingEntity;
use Klevu\Indexing\Test\Integration\Traits\IndexingEntitiesTrait;
use Klevu\IndexingApi\Api\IndexingEntityRepositoryInterface;
use Klevu\IndexingApi\Model\Source\Actions;
use Klevu\IndexingCategories\Model\Source\Aspect;
use Klevu\TestFixtures\Catalog\Attribute\AttributeFixturePool;
use Klevu\TestFixtures\Catalog\AttributeTrait;
use Klevu\TestFixtures\Catalog\ProductTrait;
use Klevu\TestFixtures\Store\StoreFixturesPool;
use Klevu\TestFixtures\Store\StoreTrait;
use Magento\Catalog\Api\AttributeSetRepositoryInterface;
use Magento\Catalog\Api\Data\ProductAttributeInterface;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductAttributeManagementInterface;
use Magento\Catalog\Model\Config as CatalogConfig;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Attribute\Source\Status as SourceStatus;
use Magento\Catalog\Model\Product\Type;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Eav\Api\AttributeGroupRepositoryInterface;
use Magento\Eav\Api\Data\AttributeInterface;
use Magento\Eav\Api\Data\AttributeSetInterface;
use Magento\Eav\Model\Entity\Attribute\Group as AttributeGroup;
use Magento\Eav\Model\Entity\Attribute\GroupFactory as AttributeGroupFactory;
use Magento\Eav\Model\Entity\Attribute\Set as AttributeSet;
use Magento\Eav\Model\Entity\Attribute\SetFactory as AttributeSetFactory;
use Magento\Eav\Model\Entity\Type as EntityType;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use TddWizard\Fixtures\Catalog\ProductFixturePool;
use TddWizard\Fixtures\Core\ConfigFixture;

/**
 * @covers \Klevu\Indexing\Observer\AttributeSetSaveCommitAfterObserver::class
 */
class AttributeSetSaveCommitAfterObserverTest extends TestCase
{
    use AttributeTrait;
    use IndexingEntitiesTrait;
    use ProductTrait;
    use StoreTrait;

    /**
     * @var ObjectManagerInterface|null
     */
    private ?ObjectManagerInterface $objectManager = null; // @phpstan-ignore-line
    /**
     * @var SearchCriteriaBuilder|null
     */
    private ?SearchCriteriaBuilder $searchCriteriaBuilder = null; // @phpstan-ignore-line
    /**
     * @var AttributeSetRepositoryInterface|null
     */
    private ?AttributeSetRepositoryInterface $attributeSetRepository = null; // @phpstan-ignore-line
    /**
     * @var AttributeGroupRepositoryInterface|null
     */
    private ?AttributeGroupRepositoryInterface $attributeGroupRepository = null; // @phpstan-ignore-line
    /**
     * @var IndexingEntityRepositoryInterface|null
     */
    private ?IndexingEntityRepositoryInterface $indexingEntityRepository = null; // @phpstan-ignore-line

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->objectManager = Bootstrap::getObjectManager();

        $this->attributeFixturePool = $this->objectManager->get(AttributeFixturePool::class);
        $this->storeFixturesPool = $this->objectManager->get(StoreFixturesPool::class);
        $this->attributeFixturePool = $this->objectManager->get(AttributeFixturePool::class);
        $this->productFixturePool = $this->objectManager->get(ProductFixturePool::class);

        $this->searchCriteriaBuilder = $this->objectManager->get(SearchCriteriaBuilder::class);
        $this->attributeSetRepository = $this->objectManager->get(AttributeSetRepositoryInterface::class);
        $this->attributeGroupRepository = $this->objectManager->get(AttributeGroupRepositoryInterface::class);
        $this->indexingEntityRepository = $this->objectManager->create(IndexingEntityRepositoryInterface::class);
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

    public function testExecute(): void
    {
        $apiKey = 'klevu-1234567890';
        $this->cleanIndexingEntities($apiKey);

        $this->createStore(
            storeData: [
                'key' => 'klevu_test_attributesetobserver',
                'code' => 'klevu_test_attributesetobserver',
                'name' => 'Klevu Test: Attribute Set Save Observer',
                'is_active' => true,
            ],
        );
        $storeFixture = $this->storeFixturesPool->get('klevu_test_attributesetobserver');

        $this->createAttribute([
            'code' => 'klevu_test_attributesetobserver',
            'key' => 'klevu_test_attributesetobserver',
            'generate_config_for' => [
                Type::TYPE_SIMPLE,
            ],
            'aspect' => Aspect::ALL,
        ]);
        $attributeFixture = $this->attributeFixturePool->get('klevu_test_attributesetobserver');
        /** @var ProductAttributeInterface $attribute1 */
        $attribute = $attributeFixture->getAttribute();

        $this->removeAttributeSetByName('klevu_test_attributesetobserver');
        $attributeSet = $this->createAttributeSet(
            attributes: [
                $attribute,
            ],
            data: [
                'attribute_set_name' => 'klevu_test_attributesetobserver',
                'entity_type_id' => 4,
            ],
        );
        $attributeSetId = (int)$attributeSet->getAttributeSetId();

        /** @var Product $product */
        $product = $this->objectManager->get(ProductInterface::class);
        $defaultAttributeSetId = (int)$product->getDefaultAttributeSetId();

        $this->createProduct(
            productData: [
                'key' => 'klevu_test_attributesetobserver_1',
                'sku' => 'klevu_test_attributesetobserver_1',
                'name' => 'Klevu Test: Attribute Set Save Observer (1)',
                'status' => SourceStatus::STATUS_ENABLED,
                'visibility' => Visibility::VISIBILITY_BOTH,
                'in_stock' => true,
                'qty' => 100,
                'price' => 100.00,
                'website_ids' => array_unique([
                    $storeFixture->getWebsiteId(),
                ]),
                'type_id' => Type::TYPE_SIMPLE,
                'data' => [
                    ProductInterface::ATTRIBUTE_SET_ID => $defaultAttributeSetId,
                ],
            ],
        );
        $productFixture1 = $this->productFixturePool->get('klevu_test_attributesetobserver_1');

        $this->createProduct(
            productData: [
                'key' => 'klevu_test_attributesetobserver_2',
                'sku' => 'klevu_test_attributesetobserver_2',
                'name' => 'Klevu Test: Attribute Set Save Observer (2)',
                'status' => SourceStatus::STATUS_ENABLED,
                'visibility' => Visibility::VISIBILITY_BOTH,
                'in_stock' => true,
                'qty' => 100,
                'price' => 100.00,
                'website_ids' => array_unique([
                    $storeFixture->getWebsiteId(),
                ]),
                'type_id' => Type::TYPE_SIMPLE,
                'data' => [
                    ProductInterface::ATTRIBUTE_SET_ID => $attributeSetId,
                ],
            ],
        );
        $productFixture2 = $this->productFixturePool->get('klevu_test_attributesetobserver_2');

        $this->createProduct(
            productData: [
                'key' => 'klevu_test_attributesetobserver_3',
                'sku' => 'klevu_test_attributesetobserver_3',
                'name' => 'Klevu Test: Attribute Set Save Observer (3)',
                'status' => SourceStatus::STATUS_ENABLED,
                'visibility' => Visibility::VISIBILITY_BOTH,
                'in_stock' => true,
                'qty' => 100,
                'price' => 100.00,
                'website_ids' => array_unique([
                    $storeFixture->getWebsiteId(),
                ]),
                'type_id' => Type::TYPE_SIMPLE,
                'data' => [
                    ProductInterface::ATTRIBUTE_SET_ID => $attributeSetId,
                ],
            ],
        );
        $productFixture3 = $this->productFixturePool->get('klevu_test_attributesetobserver_3');

        $this->createProduct(
            productData: [
                'key' => 'klevu_test_attributesetobserver_4',
                'sku' => 'klevu_test_attributesetobserver_4',
                'name' => 'Klevu Test: Attribute Set Save Observer (4)',
                'status' => SourceStatus::STATUS_ENABLED,
                'visibility' => Visibility::VISIBILITY_BOTH,
                'in_stock' => true,
                'qty' => 100,
                'price' => 100.00,
                'website_ids' => array_unique([
                    $storeFixture->getWebsiteId(),
                ]),
                'type_id' => Type::TYPE_SIMPLE,
                'data' => [
                    ProductInterface::ATTRIBUTE_SET_ID => $attributeSetId,
                ],
            ],
        );
        $productFixture4 = $this->productFixturePool->get('klevu_test_attributesetobserver_4');

        ConfigFixture::setForStore(
            path: 'klevu_configuration/auth_keys/js_api_key',
            value: $apiKey,
            storeCode: 'klevu_test_attributesetobserver',
        );
        ConfigFixture::setForStore(
            path: 'klevu_configuration/auth_keys/rest_auth_key',
            value: $apiKey,
            storeCode: 'klevu_test_attributesetobserver',
        );

        $indexingEntity1 = $this->createIndexingEntity(
            data: [
                IndexingEntity::TARGET_ID => $productFixture1->getId(),
                IndexingEntity::TARGET_ENTITY_SUBTYPE => 'simple',
                IndexingEntity::API_KEY => $apiKey,
                IndexingEntity::NEXT_ACTION => Actions::NO_ACTION,
                IndexingEntity::LAST_ACTION => Actions::ADD,
                IndexingEntity::IS_INDEXABLE => true,
            ],
        );
        $indexingEntity2 = $this->createIndexingEntity(
            data: [
                IndexingEntity::TARGET_ID => $productFixture2->getId(),
                IndexingEntity::TARGET_ENTITY_SUBTYPE => 'simple',
                IndexingEntity::API_KEY => $apiKey,
                IndexingEntity::NEXT_ACTION => Actions::NO_ACTION,
                IndexingEntity::LAST_ACTION => Actions::ADD,
                IndexingEntity::IS_INDEXABLE => true,
            ],
        );
        $indexingEntity3 = $this->createIndexingEntity(
            data: [
                IndexingEntity::TARGET_ID => $productFixture3->getId(),
                IndexingEntity::TARGET_ENTITY_SUBTYPE => 'simple',
                IndexingEntity::API_KEY => $apiKey,
                IndexingEntity::NEXT_ACTION => Actions::ADD,
                IndexingEntity::LAST_ACTION => Actions::ADD,
                IndexingEntity::IS_INDEXABLE => true,
            ],
        );
        $indexingEntity4 = $this->createIndexingEntity(
            data: [
                IndexingEntity::TARGET_ID => $productFixture4->getId(),
                IndexingEntity::TARGET_ENTITY_SUBTYPE => 'simple',
                IndexingEntity::API_KEY => $apiKey,
                IndexingEntity::NEXT_ACTION => Actions::NO_ACTION,
                IndexingEntity::LAST_ACTION => Actions::ADD,
                IndexingEntity::IS_INDEXABLE => false,
            ],
        );

        $attributeSet->setData(
            key: 'remove_attributes',
            value: [
                $attribute,
            ],
        );
        $this->attributeSetRepository->save($attributeSet);

        $indexingEntity1Reloaded = $this->indexingEntityRepository->getById(
            indexingEntityId: $indexingEntity1->getId(),
        );
        $this->assertSame(
            expected: Actions::NO_ACTION,
            actual: $indexingEntity1Reloaded->getNextAction(),
        );

        $indexingEntity2Reloaded = $this->indexingEntityRepository->getById(
            indexingEntityId: $indexingEntity2->getId(),
        );
        $this->assertSame(
            expected: Actions::UPDATE,
            actual: $indexingEntity2Reloaded->getNextAction(),
        );

        $indexingEntity3Reloaded = $this->indexingEntityRepository->getById(
            indexingEntityId: $indexingEntity3->getId(),
        );
        $this->assertSame(
            expected: Actions::ADD,
            actual: $indexingEntity3Reloaded->getNextAction(),
        );

        $indexingEntity4Reloaded = $this->indexingEntityRepository->getById(
            indexingEntityId: $indexingEntity4->getId(),
        );
        $this->assertSame(
            expected: Actions::NO_ACTION,
            actual: $indexingEntity4Reloaded->getNextAction(),
        );

        $this->removeAttributeSet($attributeSet);
        $this->cleanIndexingEntities($apiKey);
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
        $attributeGroup->setAttributeGroupName('Klevu Test Group');
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
     * @param string $attributeSetName
     *
     * @return void
     */
    private function removeAttributeSetByName(string $attributeSetName): void
    {
        $this->searchCriteriaBuilder->addFilter(
            field: 'attribute_set_name',
            value: $attributeSetName,
            conditionType: 'eq',
        );
        $attributeSetResult = $this->attributeSetRepository->getList(
            searchCriteria: $this->searchCriteriaBuilder->create(),
        );

        foreach ($attributeSetResult->getItems() as $attributeSet) {
            $this->removeAttributeSet($attributeSet);
        }
    }

    /**
     * @param AttributeSetInterface $attributeSet
     *
     * @return void
     */
    private function removeAttributeSet(AttributeSetInterface $attributeSet): void
    {
        try {
            $this->attributeSetRepository->delete($attributeSet);
        } catch (\Exception) {
            // this is fine
        }
    }

    /**
     * @param mixed $attributeSetId
     *
     * @return bool|float|int|string
     */
    private function getAttributeGroupId(mixed $attributeSetId): string|int|bool|float
    {
        $config = $this->objectManager->create(CatalogConfig::class);

        return $config->getAttributeGroupId($attributeSetId, 'Klevu Test Group');
    }
}
