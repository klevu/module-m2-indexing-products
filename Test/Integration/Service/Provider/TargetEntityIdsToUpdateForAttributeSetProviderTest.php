<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Test\Integration\Service\Provider;

use Klevu\IndexingApi\Service\Provider\TargetEntityIdsToUpdateForAttributeSetProviderInterface;
use Klevu\IndexingProducts\Service\Provider\TargetEntityIdsToUpdateForAttributeSetProvider;
use Klevu\TestFixtures\Catalog\Attribute\AttributeFixturePool;
use Klevu\TestFixtures\Catalog\AttributeTrait;
use Klevu\TestFixtures\Catalog\ProductTrait;
use Klevu\TestFixtures\Store\StoreFixturesPool;
use Klevu\TestFixtures\Store\StoreTrait;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Magento\Catalog\Api\AttributeSetRepositoryInterface;
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

/**
 * @covers \Klevu\IndexingProducts\Service\Provider\TargetEntityIdsToUpdateForAttributeSetProvider::class
 * @method TargetEntityIdsToUpdateForAttributeSetProvider instantiateTestObject(?array $arguments = null)
 * @method TargetEntityIdsToUpdateForAttributeSetProvider instantiateTestObjectFromInterface(?array $arguments = null)
 */
class TargetEntityIdsToUpdateForAttributeSetProviderTest extends TestCase
{
    use AttributeTrait;
    use ObjectInstantiationTrait;
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
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->implementationFqcn = TargetEntityIdsToUpdateForAttributeSetProvider::class;
        $this->interfaceFqcn = TargetEntityIdsToUpdateForAttributeSetProviderInterface::class;

        $this->objectManager = Bootstrap::getObjectManager();

        $this->attributeFixturePool = $this->objectManager->get(AttributeFixturePool::class);
        $this->storeFixturesPool = $this->objectManager->get(StoreFixturesPool::class);
        $this->productFixturePool = $this->objectManager->get(ProductFixturePool::class);

        $this->searchCriteriaBuilder = $this->objectManager->get(SearchCriteriaBuilder::class);
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
        $this->storeFixturesPool->rollback();
    }

    /**
     * @testWith [null]
     *           [1]
     *           [2]
     *           [4]
     *
     * @param int|null $pageSize
     *
     * @return void
     * @throws LocalizedException
     */
    public function testGet(?int $pageSize): void
    {
        $this->createStore(
            storeData: [
                'key' => 'klevu_test_targetidsprovider',
                'code' => 'klevu_test_targetidsprovider',
                'name' => 'Klevu Test: Target Entity Ids to Update Provider',
                'is_active' => true,
            ],
        );
        $storeFixture = $this->storeFixturesPool->get('klevu_test_targetidsprovider');

        $this->removeAttributeSetByName('klevu_test_targetidsprovider');
        $attributeSet = $this->createAttributeSet(
            attributes: [],
            data: [
                'attribute_set_name' => 'klevu_test_targetidsprovider',
                'entity_type_id' => 4,
            ],
        );
        $attributeSetId = (int)$attributeSet->getAttributeSetId();

        /** @var Product $product */
        $product = $this->objectManager->get(ProductInterface::class);
        $defaultAttributeSetId = (int)$product->getDefaultAttributeSetId();

        $this->createProduct(
            productData: [
                'key' => 'klevu_test_targetidsprovider_1',
                'sku' => 'klevu_test_targetidsprovider_1',
                'name' => 'Klevu Test: Target Entity Ids to Update Provider (1)',
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

        $this->createProduct(
            productData: [
                'key' => 'klevu_test_targetidsprovider_2',
                'sku' => 'klevu_test_targetidsprovider_2',
                'name' => 'Klevu Test: Target Entity Ids to Update Provider (2)',
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
        $productFixture2 = $this->productFixturePool->get('klevu_test_targetidsprovider_2');

        $this->createProduct(
            productData: [
                'key' => 'klevu_test_targetidsprovider_3',
                'sku' => 'klevu_test_targetidsprovider_3',
                'name' => 'Klevu Test: Target Entity Ids to Update Provider (3)',
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

        $this->createProduct(
            productData: [
                'key' => 'klevu_test_targetidsprovider_4',
                'sku' => 'klevu_test_targetidsprovider_4',
                'name' => 'Klevu Test: Target Entity Ids to Update Provider (4)',
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
        $productFixture4 = $this->productFixturePool->get('klevu_test_targetidsprovider_4');

        if ($pageSize) {
            $targetEntityIdsToUpdateForAttributeSetProvider = $this->instantiateTestObject(
                arguments: [
                    'pageSize' => $pageSize,
                ],
            );
        } else {
            $targetEntityIdsToUpdateForAttributeSetProvider = $this->instantiateTestObject();
        }

        $expectedIds = [
            (int)$productFixture2->getId(),
            (int)$productFixture4->getId(),
        ];
        $actualIds = [];
        foreach ($targetEntityIdsToUpdateForAttributeSetProvider->get($attributeSetId) as $iteration => $targetEntityIds) {
            $actualIds[] = $targetEntityIds;

            if ($iteration > 2) {
                $this->fail('Suspected infinite loop on get()');
            }
        }
        $actualIds = array_merge([], ...$actualIds);

        $this->removeAttributeSet($attributeSet);

        $this->assertSame(
            expected: $expectedIds,
            actual: $actualIds,
        );
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
