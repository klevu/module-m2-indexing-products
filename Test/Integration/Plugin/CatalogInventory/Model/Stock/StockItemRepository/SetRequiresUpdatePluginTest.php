<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Test\Integration\Plugin\CatalogInventory\Model\Stock\StockItemRepository;

use Klevu\Indexing\Model\IndexingEntity;
use Klevu\Indexing\Test\Integration\Traits\IndexingEntitiesTrait;
use Klevu\IndexingApi\Api\Data\IndexingEntityInterface;
use Klevu\IndexingApi\Model\Source\Actions;
use Klevu\IndexingProducts\Service\Determiner\RequiresUpdateCriteria\StockStatus as StockStatusCriteria;
use Klevu\TestFixtures\Catalog\Attribute\AttributeFixturePool;
use Klevu\TestFixtures\Catalog\AttributeTrait;
use Klevu\TestFixtures\Catalog\ProductTrait;
use Klevu\TestFixtures\Store\StoreFixturesPool;
use Klevu\TestFixtures\Store\StoreGroupFixturesPool;
use Klevu\TestFixtures\Store\StoreGroupTrait;
use Klevu\TestFixtures\Store\StoreTrait;
use Klevu\TestFixtures\Website\WebsiteFixturesPool;
use Klevu\TestFixtures\Website\WebsiteTrait;
use Magento\Catalog\Api\Data\ProductExtensionInterface;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product\Attribute\Source\Status as ProductStatus;
use Magento\Catalog\Model\Product\Type as ProductType;
use Magento\Catalog\Model\Product\Visibility as ProductVisibility;
use Magento\CatalogInventory\Api\Data\StockItemInterfaceFactory;
use Magento\CatalogInventory\Api\StockItemRepositoryInterface;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Eav\Api\Data\AttributeInterface;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\StateException;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use TddWizard\Fixtures\Catalog\ProductFixture;
use TddWizard\Fixtures\Catalog\ProductFixturePool;
use TddWizard\Fixtures\Core\ConfigFixture;

class SetRequiresUpdatePluginTest extends TestCase
{
    use AttributeTrait;
    use IndexingEntitiesTrait;
    use ProductTrait;
    use StoreTrait;
    use StoreGroupTrait;
    use WebsiteTrait;

    private const FIXTURE_IDENTIFIER = 'klevu_test_stockitemsave';
    private const FIXTURE_NAME = 'Klevu Test: StockItemRepository Save Plugin';

    /**
     * @var ObjectManagerInterface|null
     */
    private ?ObjectManagerInterface $objectManager = null; // @phpstan-ignore-line
    /**
     * @var StockItemInterfaceFactory|null
     */
    private ?StockItemInterfaceFactory $stockItemFactory = null; // @phpstan-ignore-line
    /**
     * @var StockRegistryInterface|mixed|null
     */
    private ?StockRegistryInterface $stockRegistry = null; // @phpstan-ignore-line
    /**
     * @var ProductRepositoryInterface|null
     */
    private ?ProductRepositoryInterface $productRepository = null; // @phpstan-ignore-line

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->objectManager = Bootstrap::getObjectManager();

        $this->storeFixturesPool = $this->objectManager->get(StoreFixturesPool::class);
        $this->storeGroupFixturesPool = $this->objectManager->get(StoreGroupFixturesPool::class);
        $this->websiteFixturesPool = $this->objectManager->get(WebsiteFixturesPool::class);
        $this->attributeFixturePool = $this->objectManager->get(AttributeFixturePool::class);
        $this->productFixturePool = $this->objectManager->get(ProductFixturePool::class);

        $this->stockItemFactory = $this->objectManager->get(StockItemInterfaceFactory::class);
        $this->stockRegistry = $this->objectManager->get(StockRegistryInterface::class);
        $this->productRepository = $this->objectManager->get(ProductRepositoryInterface::class);
    }

    /**
     * @return void
     * @throws \Exception
     */
    protected function tearDown(): void
    {
        parent::tearDown();

        $this->cleanIndexingEntities('klevu-1234567890');
        $this->productFixturePool->rollback();
        $this->attributeFixturePool->rollback();
        $this->storeFixturesPool->rollback();
        $this->storeGroupFixturesPool->rollback();
        $this->websiteFixturesPool->rollback();
    }

    public function testSave_StoreNotIntegrated(): void
    {
        extract($this->createWebsiteAndStoreFixtures());

        $productFixture = $this->createSimpleProductFixture(
            appendIdentifier: 's',
            websiteIds: [
                (int)$websiteFixture->getId(),
            ],
            quantity: 0,
            stockStatus: false,
        );

        $this->cleanIndexingEntities(
            apiKey: 'klevu-1234567890',
        );

        $product = $productFixture->getProduct();
        $extensionAttributes = $product->getExtensionAttributes();

        $stockItem = $extensionAttributes->getStockItem();
        $stockItem->setStoreId(
            value: (int)$storeFixture->getId(),
        );
        $stockItem->setQty(1);
        $stockItem->setIsInStock(true);

        $stockItemRepository = $this->objectManager->create(StockItemRepositoryInterface::class);
        $stockItemRepository->save($stockItem);

        $indexingEntities = $this->getIndexingEntities(
            type: 'KLEVU_PRODUCT',
            apiKey: 'klevu-1234567890',
        );

        $this->assertEmpty($indexingEntities);
    }

    public function testSave_ProductNotExists(): void
    {
        extract($this->createWebsiteAndStoreFixtures());

        $productFixture = $this->createSimpleProductFixture(
            appendIdentifier: 's',
            websiteIds: [
                (int)$websiteFixture->getId(),
            ],
            quantity: 0,
            stockStatus: false,
        );

        $this->cleanIndexingEntities(
            apiKey: 'klevu-1234567890',
        );

        $product = $productFixture->getProduct();
        $extensionAttributes = $product->getExtensionAttributes();

        $stockItem = $extensionAttributes->getStockItem();
        $stockItem->setStoreId(
            value: (int)$storeFixture->getId(),
        );
        $stockItem->setQty(1);
        $stockItem->setIsInStock(true);
        $stockItem->setProductId(999999999);

        $stockItemRepository = $this->objectManager->create(StockItemRepositoryInterface::class);
        $stockItemRepository->save($stockItem);

        $indexingEntities = $this->getIndexingEntities(
            type: 'KLEVU_PRODUCT',
            apiKey: 'klevu-1234567890',
        );

        $this->assertEmpty($indexingEntities);
    }

    public function testSave_SimpleProduct_NoIndexingRecord(): void
    {
        extract($this->createWebsiteAndStoreFixtures());

        ConfigFixture::setForStore(
            path: 'klevu_configuration/auth_keys/js_api_key',
            value: 'klevu-1234567890',
            storeCode: $storeFixture->getCode(),
        );
        ConfigFixture::setForStore(
            path: 'klevu_configuration/auth_keys/rest_auth_key',
            value: 'ABCDE1234567890',
            storeCode: $storeFixture->getCode(),
        );

        $productFixture = $this->createSimpleProductFixture(
            appendIdentifier: 's',
            websiteIds: [
                (int)$websiteFixture->getId(),
            ],
            quantity: 0,
            stockStatus: false,
        );

        $this->cleanIndexingEntities(
            apiKey: 'klevu-1234567890',
        );

        $product = $productFixture->getProduct();
        $extensionAttributes = $product->getExtensionAttributes();

        $stockItem = $extensionAttributes->getStockItem();
        $stockItem->setStoreId(
            value: (int)$storeFixture->getId(),
        );
        $stockItem->setQty(1);
        $stockItem->setIsInStock(true);

        $stockItemRepository = $this->objectManager->create(StockItemRepositoryInterface::class);
        $stockItemRepository->save($stockItem);

        $indexingEntities = $this->getIndexingEntities(
            type: 'KLEVU_PRODUCT',
            apiKey: 'klevu-1234567890',
        );

        $this->assertEmpty($indexingEntities);
    }

    public function testSave_SimpleProduct_IndexingRecordExists_DifferentStoreId(): void
    {
        extract($this->createWebsiteAndStoreFixtures());

        ConfigFixture::setForStore(
            path: 'klevu_configuration/auth_keys/js_api_key',
            value: 'klevu-1234567890',
            storeCode: $storeFixture->getCode(),
        );
        ConfigFixture::setForStore(
            path: 'klevu_configuration/auth_keys/rest_auth_key',
            value: 'ABCDE1234567890',
            storeCode: $storeFixture->getCode(),
        );

        $productFixture = $this->createSimpleProductFixture(
            appendIdentifier: 's',
            websiteIds: [
                (int)$websiteFixture->getId(),
            ],
            quantity: 0,
            stockStatus: false,
        );

        $this->cleanIndexingEntities(
            apiKey: 'klevu-1234567890',
        );
        $this->createIndexingEntity(
            data: [
                IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
                IndexingEntity::API_KEY => 'klevu-1234567890',
                IndexingEntity::TARGET_ID => $productFixture->getId(),
                IndexingEntity::NEXT_ACTION => Actions::NO_ACTION,
                IndexingEntity::IS_INDEXABLE => true,
                IndexingEntity::REQUIRES_UPDATE => false,
                IndexingEntity::REQUIRES_UPDATE_ORIG_VALUES => [],
            ],
        );

        $product = $productFixture->getProduct();
        $extensionAttributes = $product->getExtensionAttributes();

        $stockItem = $extensionAttributes->getStockItem();
        $stockItem->setStoreId(
            value: 1,
        );
        $stockItem->setQty(1);
        $stockItem->setIsInStock(true);

        $stockItemRepository = $this->objectManager->create(StockItemRepositoryInterface::class);
        $stockItemRepository->save($stockItem);

        $indexingEntities = $this->getIndexingEntities(
            type: 'KLEVU_PRODUCT',
            apiKey: 'klevu-1234567890',
        );

        $this->assertCount(1, $indexingEntities);
        /** @var IndexingEntityInterface $indexingEntity */
        $indexingEntity = current($indexingEntities);

        $this->assertSame(
            expected: (int)$productFixture->getId(),
            actual: $indexingEntity->getTargetId(),
        );
        $this->assertSame(
            expected: Actions::NO_ACTION,
            actual: $indexingEntity->getNextAction(),
        );
        $this->assertFalse(
            condition: $indexingEntity->getRequiresUpdate(),
        );
        $this->assertCount(
            expectedCount: 0,
            haystack: $indexingEntity->getRequiresUpdateOrigValues(),
        );
    }

    public function testSave_SimpleProduct_IndexingRecordExists(): void
    {
        extract($this->createWebsiteAndStoreFixtures());

        ConfigFixture::setForStore(
            path: 'klevu_configuration/auth_keys/js_api_key',
            value: 'klevu-1234567890',
            storeCode: $storeFixture->getCode(),
        );
        ConfigFixture::setForStore(
            path: 'klevu_configuration/auth_keys/rest_auth_key',
            value: 'ABCDE1234567890',
            storeCode: $storeFixture->getCode(),
        );

        $productFixture = $this->createSimpleProductFixture(
            appendIdentifier: 's',
            websiteIds: [
                (int)$websiteFixture->getId(),
            ],
            quantity: 0,
            stockStatus: false,
        );

        $this->cleanIndexingEntities(
            apiKey: 'klevu-1234567890',
        );
        $this->createIndexingEntity(
            data: [
                IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
                IndexingEntity::API_KEY => 'klevu-1234567890',
                IndexingEntity::TARGET_ID => $productFixture->getId(),
                IndexingEntity::NEXT_ACTION => Actions::NO_ACTION,
                IndexingEntity::IS_INDEXABLE => true,
                IndexingEntity::REQUIRES_UPDATE => false,
                IndexingEntity::REQUIRES_UPDATE_ORIG_VALUES => [],
            ],
        );

        $product = $productFixture->getProduct();
        $extensionAttributes = $product->getExtensionAttributes();

        $stockItem = $extensionAttributes->getStockItem();
        $stockItem->setStoreId(
            value: (int)$storeFixture->getId(),
        );
        $stockItem->setQty(1);
        $stockItem->setIsInStock(true);

        $stockItemRepository = $this->objectManager->create(StockItemRepositoryInterface::class);
        $stockItemRepository->save($stockItem);

        $indexingEntities = $this->getIndexingEntities(
            type: 'KLEVU_PRODUCT',
            apiKey: 'klevu-1234567890',
        );

        $this->assertCount(1, $indexingEntities);
        /** @var IndexingEntityInterface $indexingEntity */
        $indexingEntity = current($indexingEntities);

        $this->assertSame(
            expected: (int)$productFixture->getId(),
            actual: $indexingEntity->getTargetId(),
        );
        $this->assertSame(
            expected: Actions::NO_ACTION,
            actual: $indexingEntity->getNextAction(),
        );
        $this->assertTrue(
            condition: $indexingEntity->getRequiresUpdate(),
        );
        $this->assertCount(
            expectedCount: 1,
            haystack: $indexingEntity->getRequiresUpdateOrigValues(),
        );
        $requiresUpdateOrigValues = $indexingEntity->getRequiresUpdateOrigValues();
        $this->assertArrayHasKey(
            key: StockStatusCriteria::CRITERIA_IDENTIFIER,
            array: $requiresUpdateOrigValues,
        );
        $this->assertFalse(
            condition: $requiresUpdateOrigValues[StockStatusCriteria::CRITERIA_IDENTIFIER],
        );
    }

    public function testSave_ConfigurableProduct(): void
    {
        extract($this->createWebsiteAndStoreFixtures());

        ConfigFixture::setForStore(
            path: 'klevu_configuration/auth_keys/js_api_key',
            value: 'klevu-1234567890',
            storeCode: $storeFixture->getCode(),
        );
        ConfigFixture::setForStore(
            path: 'klevu_configuration/auth_keys/rest_auth_key',
            value: 'ABCDE1234567890',
            storeCode: $storeFixture->getCode(),
        );

        $this->createAttribute(
            attributeData: [
                'key' => self::FIXTURE_IDENTIFIER,
                'code' => self::FIXTURE_IDENTIFIER,
                'attribute_type' => 'configurable',
                'options' => [
                    '1' => 'Option 1',
                    '2' => 'Option 2',
                    '3' => 'Option 3',
                ],
            ],
        );
        $attributeFixture = $this->attributeFixturePool->get(self::FIXTURE_IDENTIFIER);

        $variantProductFixture1 = $this->createSimpleProductFixture(
            appendIdentifier: 'v1',
            websiteIds: [
                (int)$websiteFixture->getId(),
            ],
            quantity: 1,
            stockStatus: true,
            data: [
                $attributeFixture->getAttributeCode() => '1',
            ],
        );
        $variantProductFixture2 = $this->createSimpleProductFixture(
            appendIdentifier: 'v2',
            websiteIds: [
                (int)$websiteFixture->getId(),
            ],
            quantity: 1,
            stockStatus: true,
            data: [
                $attributeFixture->getAttributeCode() => '2',
            ],
        );
        $configurableProductFixture = $this->createConfigurableProductFixture(
            appendIdentifier: 'c',
            websiteIds: [
                (int)$websiteFixture->getId(),
            ],
            configurableAttributes: [
                $attributeFixture->getAttribute(),
            ],
            configurableVariants: [
                $variantProductFixture1->getProduct(),
                $variantProductFixture2->getProduct(),
            ],
            stockStatus: false,
        );

        $this->cleanIndexingEntities(
            apiKey: 'klevu-1234567890',
        );
        $this->createIndexingEntity(
            data: [
                IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
                IndexingEntity::TARGET_ENTITY_SUBTYPE => 'simple',
                IndexingEntity::API_KEY => 'klevu-1234567890',
                IndexingEntity::TARGET_ID => $variantProductFixture1->getId(),
                IndexingEntity::TARGET_PARENT_ID => null,
                IndexingEntity::NEXT_ACTION => Actions::NO_ACTION,
                IndexingEntity::IS_INDEXABLE => true,
                IndexingEntity::REQUIRES_UPDATE => false,
                IndexingEntity::REQUIRES_UPDATE_ORIG_VALUES => [],
            ],
        );
        $this->createIndexingEntity(
            data: [
                IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
                IndexingEntity::TARGET_ENTITY_SUBTYPE => 'simple',
                IndexingEntity::API_KEY => 'klevu-1234567890',
                IndexingEntity::TARGET_ID => $variantProductFixture2->getId(),
                IndexingEntity::TARGET_PARENT_ID => null,
                IndexingEntity::NEXT_ACTION => Actions::NO_ACTION,
                IndexingEntity::IS_INDEXABLE => true,
                IndexingEntity::REQUIRES_UPDATE => false,
                IndexingEntity::REQUIRES_UPDATE_ORIG_VALUES => [],
            ],
        );
        $this->createIndexingEntity(
            data: [
                IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
                IndexingEntity::TARGET_ENTITY_SUBTYPE => 'configurable_variants',
                IndexingEntity::API_KEY => 'klevu-1234567890',
                IndexingEntity::TARGET_ID => $variantProductFixture1->getId(),
                IndexingEntity::TARGET_PARENT_ID => $configurableProductFixture->getId(),
                IndexingEntity::NEXT_ACTION => Actions::NO_ACTION,
                IndexingEntity::IS_INDEXABLE => true,
                IndexingEntity::REQUIRES_UPDATE => false,
                IndexingEntity::REQUIRES_UPDATE_ORIG_VALUES => [],
            ],
        );
        $this->createIndexingEntity(
            data: [
                IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
                IndexingEntity::TARGET_ENTITY_SUBTYPE => 'configurable_variants',
                IndexingEntity::API_KEY => 'klevu-1234567890',
                IndexingEntity::TARGET_ID => $variantProductFixture2->getId(),
                IndexingEntity::TARGET_PARENT_ID => $configurableProductFixture->getId(),
                IndexingEntity::NEXT_ACTION => Actions::NO_ACTION,
                IndexingEntity::IS_INDEXABLE => true,
                IndexingEntity::REQUIRES_UPDATE => false,
                IndexingEntity::REQUIRES_UPDATE_ORIG_VALUES => [],
            ],
        );
        $this->createIndexingEntity(
            data: [
                IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
                IndexingEntity::TARGET_ENTITY_SUBTYPE => 'configurable',
                IndexingEntity::API_KEY => 'klevu-1234567890',
                IndexingEntity::TARGET_ID => $configurableProductFixture->getId(),
                IndexingEntity::TARGET_PARENT_ID => null,
                IndexingEntity::NEXT_ACTION => Actions::NO_ACTION,
                IndexingEntity::IS_INDEXABLE => true,
                IndexingEntity::REQUIRES_UPDATE => false,
                IndexingEntity::REQUIRES_UPDATE_ORIG_VALUES => [],
            ],
        );

        $configurableProduct = $configurableProductFixture->getProduct();
        $extensionAttributes = $configurableProduct->getExtensionAttributes();

        $stockItem = $extensionAttributes->getStockItem();
        $stockItem->setStoreId(
            value: (int)$storeFixture->getId(),
        );
        $stockItem->setQty(1);
        $stockItem->setIsInStock(true);

        $stockItemRepository = $this->objectManager->create(StockItemRepositoryInterface::class);
        $stockItemRepository->save($stockItem);

        $indexingEntities = $this->getIndexingEntities(
            type: 'KLEVU_PRODUCT',
            apiKey: 'klevu-1234567890',
        );

        $this->assertCount(5, $indexingEntities);
        foreach ($indexingEntities as $indexingEntity) {
            $this->assertSame(
                expected: Actions::NO_ACTION,
                actual: $indexingEntity->getNextAction(),
            );
            $requiresUpdateOrigValues = $indexingEntity->getRequiresUpdateOrigValues();
            switch ($indexingEntity->getTargetEntitySubtype()) {
                case 'configurable':
                    $this->assertSame(
                        expected: (int)$configurableProductFixture->getId(),
                        actual: $indexingEntity->getTargetId(),
                    );
                    $this->assertTrue(
                        condition: $indexingEntity->getRequiresUpdate(),
                    );
                    $this->assertCount(
                        expectedCount: 1,
                        haystack: $requiresUpdateOrigValues,
                    );
                    $this->assertArrayHasKey(
                        key: StockStatusCriteria::CRITERIA_IDENTIFIER,
                        array: $requiresUpdateOrigValues,
                    );
                    $this->assertFalse(
                        condition: $requiresUpdateOrigValues[StockStatusCriteria::CRITERIA_IDENTIFIER],
                    );
                    break;

                case 'configurable_variants':
                    $this->assertContains(
                        needle: $indexingEntity->getTargetId(),
                        haystack: [
                            (int)$variantProductFixture1->getId(),
                            (int)$variantProductFixture2->getId(),
                        ],
                    );
                    $this->assertSame(
                        expected: (int)$configurableProductFixture->getId(),
                        actual: $indexingEntity->getTargetParentId(),
                    );
                    $this->assertTrue(
                        condition: $indexingEntity->getRequiresUpdate(),
                    );
                    $this->assertCount(
                        expectedCount: 1,
                        haystack: $requiresUpdateOrigValues,
                    );
                    $this->assertArrayHasKey(
                        key: StockStatusCriteria::CRITERIA_IDENTIFIER,
                        array: $requiresUpdateOrigValues,
                    );
                    $this->assertFalse(
                        condition: $requiresUpdateOrigValues[StockStatusCriteria::CRITERIA_IDENTIFIER],
                    );
                    break;

                default:
                    $this->assertContains(
                        needle: $indexingEntity->getTargetId(),
                        haystack: [
                            (int)$variantProductFixture1->getId(),
                            (int)$variantProductFixture2->getId(),
                        ],
                    );
                    $this->assertNull(
                        actual: $indexingEntity->getTargetParentId(),
                    );
                    $this->assertFalse(
                        condition: $indexingEntity->getRequiresUpdate(),
                    );
                    $this->assertCount(
                        expectedCount: 0,
                        haystack: $requiresUpdateOrigValues,
                    );
                    break;
            }
        }
    }

    /**
     * @group wip
     */
    public function testSave_ConfigurableVariant(): void
    {
        extract($this->createWebsiteAndStoreFixtures());

        ConfigFixture::setForStore(
            path: 'klevu_configuration/auth_keys/js_api_key',
            value: 'klevu-1234567890',
            storeCode: $storeFixture->getCode(),
        );
        ConfigFixture::setForStore(
            path: 'klevu_configuration/auth_keys/rest_auth_key',
            value: 'ABCDE1234567890',
            storeCode: $storeFixture->getCode(),
        );

        $this->createAttribute(
            attributeData: [
                'key' => self::FIXTURE_IDENTIFIER,
                'code' => self::FIXTURE_IDENTIFIER,
                'attribute_type' => 'configurable',
                'options' => [
                    '1' => 'Option 1',
                    '2' => 'Option 2',
                    '3' => 'Option 3',
                ],
            ],
        );
        $attributeFixture = $this->attributeFixturePool->get(self::FIXTURE_IDENTIFIER);

        $variantProductFixture1 = $this->createSimpleProductFixture(
            appendIdentifier: 'v1',
            websiteIds: [
                (int)$websiteFixture->getId(),
            ],
            quantity: 1,
            stockStatus: true,
            data: [
                $attributeFixture->getAttributeCode() => '1',
            ],
        );
        $variantProductFixture2 = $this->createSimpleProductFixture(
            appendIdentifier: 'v2',
            websiteIds: [
                (int)$websiteFixture->getId(),
            ],
            quantity: 1,
            stockStatus: true,
            data: [
                $attributeFixture->getAttributeCode() => '2',
            ],
        );
        $configurableProductFixture = $this->createConfigurableProductFixture(
            appendIdentifier: 'c',
            websiteIds: [
                (int)$websiteFixture->getId(),
            ],
            configurableAttributes: [
                $attributeFixture->getAttribute(),
            ],
            configurableVariants: [
                $variantProductFixture1->getProduct(),
                $variantProductFixture2->getProduct(),
            ],
            stockStatus: false,
        );

        $this->cleanIndexingEntities(
            apiKey: 'klevu-1234567890',
        );
        $this->createIndexingEntity(
            data: [
                IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
                IndexingEntity::TARGET_ENTITY_SUBTYPE => 'simple',
                IndexingEntity::API_KEY => 'klevu-1234567890',
                IndexingEntity::TARGET_ID => $variantProductFixture1->getId(),
                IndexingEntity::TARGET_PARENT_ID => null,
                IndexingEntity::NEXT_ACTION => Actions::NO_ACTION,
                IndexingEntity::IS_INDEXABLE => true,
                IndexingEntity::REQUIRES_UPDATE => false,
                IndexingEntity::REQUIRES_UPDATE_ORIG_VALUES => [],
            ],
        );
        $this->createIndexingEntity(
            data: [
                IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
                IndexingEntity::TARGET_ENTITY_SUBTYPE => 'simple',
                IndexingEntity::API_KEY => 'klevu-1234567890',
                IndexingEntity::TARGET_ID => $variantProductFixture2->getId(),
                IndexingEntity::TARGET_PARENT_ID => null,
                IndexingEntity::NEXT_ACTION => Actions::NO_ACTION,
                IndexingEntity::IS_INDEXABLE => true,
                IndexingEntity::REQUIRES_UPDATE => false,
                IndexingEntity::REQUIRES_UPDATE_ORIG_VALUES => [],
            ],
        );
        $this->createIndexingEntity(
            data: [
                IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
                IndexingEntity::TARGET_ENTITY_SUBTYPE => 'configurable_variants',
                IndexingEntity::API_KEY => 'klevu-1234567890',
                IndexingEntity::TARGET_ID => $variantProductFixture1->getId(),
                IndexingEntity::TARGET_PARENT_ID => $configurableProductFixture->getId(),
                IndexingEntity::NEXT_ACTION => Actions::NO_ACTION,
                IndexingEntity::IS_INDEXABLE => true,
                IndexingEntity::REQUIRES_UPDATE => false,
                IndexingEntity::REQUIRES_UPDATE_ORIG_VALUES => [],
            ],
        );
        $this->createIndexingEntity(
            data: [
                IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
                IndexingEntity::TARGET_ENTITY_SUBTYPE => 'configurable_variants',
                IndexingEntity::API_KEY => 'klevu-1234567890',
                IndexingEntity::TARGET_ID => $variantProductFixture2->getId(),
                IndexingEntity::TARGET_PARENT_ID => $configurableProductFixture->getId(),
                IndexingEntity::NEXT_ACTION => Actions::NO_ACTION,
                IndexingEntity::IS_INDEXABLE => true,
                IndexingEntity::REQUIRES_UPDATE => false,
                IndexingEntity::REQUIRES_UPDATE_ORIG_VALUES => [],
            ],
        );
        $this->createIndexingEntity(
            data: [
                IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
                IndexingEntity::TARGET_ENTITY_SUBTYPE => 'configurable',
                IndexingEntity::API_KEY => 'klevu-1234567890',
                IndexingEntity::TARGET_ID => $configurableProductFixture->getId(),
                IndexingEntity::TARGET_PARENT_ID => null,
                IndexingEntity::NEXT_ACTION => Actions::NO_ACTION,
                IndexingEntity::IS_INDEXABLE => true,
                IndexingEntity::REQUIRES_UPDATE => false,
                IndexingEntity::REQUIRES_UPDATE_ORIG_VALUES => [],
            ],
        );

        $variantProduct1 = $variantProductFixture1->getProduct();
        $extensionAttributes = $variantProduct1->getExtensionAttributes();

        $stockItem = $extensionAttributes->getStockItem();
        $stockItem->setStoreId(
            value: (int)$storeFixture->getId(),
        );
        $stockItem->setQty(0);
        $stockItem->setIsInStock(false);

        $stockItemRepository = $this->objectManager->create(StockItemRepositoryInterface::class);
        $stockItemRepository->save($stockItem);

        $indexingEntities = $this->getIndexingEntities(
            type: 'KLEVU_PRODUCT',
            apiKey: 'klevu-1234567890',
        );

        $this->assertCount(5, $indexingEntities);
        foreach ($indexingEntities as $indexingEntity) {
            $this->assertSame(
                expected: Actions::NO_ACTION,
                actual: $indexingEntity->getNextAction(),
            );
            $requiresUpdateOrigValues = $indexingEntity->getRequiresUpdateOrigValues();
            switch (true) {
                case (int)$configurableProductFixture->getId() === $indexingEntity->getTargetId():
                    $this->assertSame(
                        expected: 'configurable',
                        actual: $indexingEntity->getTargetEntitySubtype(),
                    );
                    $this->assertTrue(
                        condition: $indexingEntity->getRequiresUpdate(),
                    );
                    $this->assertCount(
                        expectedCount: 1,
                        haystack: $requiresUpdateOrigValues,
                    );
                    $this->assertArrayHasKey(
                        key: StockStatusCriteria::CRITERIA_IDENTIFIER,
                        array: $requiresUpdateOrigValues,
                    );
                    $this->assertFalse(
                        condition: $requiresUpdateOrigValues[StockStatusCriteria::CRITERIA_IDENTIFIER],
                    );
                    break;

                case (int)$variantProductFixture1->getId() === $indexingEntity->getTargetId()
                    && 'configurable_variants' === $indexingEntity->getTargetEntitySubtype():
                    $this->assertSame(
                        expected: (int)$configurableProductFixture->getId(),
                        actual: $indexingEntity->getTargetParentId(),
                    );
                    $this->assertTrue(
                        condition: $indexingEntity->getRequiresUpdate(),
                    );
                    $this->assertCount(
                        expectedCount: 1,
                        haystack: $requiresUpdateOrigValues,
                    );
                    $this->assertArrayHasKey(
                        key: StockStatusCriteria::CRITERIA_IDENTIFIER,
                        array: $requiresUpdateOrigValues,
                    );
                    $this->assertFalse(
                        condition: $requiresUpdateOrigValues[StockStatusCriteria::CRITERIA_IDENTIFIER],
                    );
                    break;

                case (int)$variantProductFixture1->getId() === $indexingEntity->getTargetId()
                    && 'simple' === $indexingEntity->getTargetEntitySubtype():
                    $this->assertNull(
                        actual: $indexingEntity->getTargetParentId(),
                    );
                    $this->assertTrue(
                        condition: $indexingEntity->getRequiresUpdate(),
                    );
                    $this->assertCount(
                        expectedCount: 1,
                        haystack: $requiresUpdateOrigValues,
                    );
                    $this->assertArrayHasKey(
                        key: StockStatusCriteria::CRITERIA_IDENTIFIER,
                        array: $requiresUpdateOrigValues,
                    );
                    $this->assertTrue(
                        condition: $requiresUpdateOrigValues[StockStatusCriteria::CRITERIA_IDENTIFIER],
                    );
                    break;

                default:
                    $this->assertContains(
                        needle: $indexingEntity->getTargetEntitySubtype(),
                        haystack: [
                            'configurable_variants',
                            'simple'
                        ],
                    );
                    $this->assertSame(
                        expected: (int)$variantProductFixture2->getId(),
                        actual: $indexingEntity->getTargetId(),
                    );
                    $this->assertSame(
                        expected: 'configurable_variants' === $indexingEntity->getTargetEntitySubtype()
                            ? (int)$configurableProductFixture->getId()
                            : null,
                        actual: $indexingEntity->getTargetParentId(),
                    );
                    $this->assertFalse(
                        condition: $indexingEntity->getRequiresUpdate(),
                    );
                    $this->assertCount(
                        expectedCount: 0,
                        haystack: $requiresUpdateOrigValues,
                    );
                    break;
            }
        }
    }

    private function createWebsiteAndStoreFixtures(): array
    {
        $this->createWebsite(
            websiteData: [
                'key' => self::FIXTURE_IDENTIFIER,
                'code' => self::FIXTURE_IDENTIFIER,
                'name' => self::FIXTURE_NAME,
            ],
        );
        $websiteFixture = $this->websiteFixturesPool->get(self::FIXTURE_IDENTIFIER);

        $this->createStoreGroup(
            storeData: [
                'key' => self::FIXTURE_IDENTIFIER,
                'code' => self::FIXTURE_IDENTIFIER,
                'name' => self::FIXTURE_NAME,
                'website_id' => (int)$websiteFixture->getId(),
            ],
        );
        $storeGroupFixture = $this->storeGroupFixturesPool->get(self::FIXTURE_IDENTIFIER);

        $this->createStore(
            storeData: [
                'key' => self::FIXTURE_IDENTIFIER,
                'code' => self::FIXTURE_IDENTIFIER,
                'name' => self::FIXTURE_NAME,
                'group_id' => (int)$storeGroupFixture->getId(),
                'website_id' => (int)$websiteFixture->getId(),
            ],
        );
        $storeFixture = $this->storeFixturesPool->get(self::FIXTURE_IDENTIFIER);

        return [
            'websiteFixture' => $websiteFixture,
            'storeGroupFixture' => $storeGroupFixture,
            'storeFixture' => $storeFixture,
        ];
    }

    /**
     * @param string $appendIdentifier
     * @param int[] $websiteIds
     * @param int $quantity
     * @param bool $stockStatus
     * @param int $status
     * @param int $visibility
     * @param array $data
     *
     * @return ProductFixture
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    private function createSimpleProductFixture(
        string $appendIdentifier,
        array $websiteIds,
        int $quantity,
        bool $stockStatus,
        int $status = ProductStatus::STATUS_ENABLED,
        int $visibility = ProductVisibility::VISIBILITY_BOTH,
        array $data = [],
    ): ProductFixture {
        $this->createProduct(
            productData: [
                'key' => self::FIXTURE_IDENTIFIER . '_' . $appendIdentifier,
                'sku' => self::FIXTURE_IDENTIFIER . '_' . $appendIdentifier,
                'name' => self::FIXTURE_NAME . ' : ' . $appendIdentifier,
                'status' => $status,
                'visibility' => $visibility,
                'in_stock' => $stockStatus,
                'qty' => $quantity,
                'price' => 100.00,
                'type_id' => ProductType::TYPE_SIMPLE,
                'website_ids' => $websiteIds,
                'data' => $data,
            ],
        );
        $productFixtureSimple = $this->productFixturePool->get(self::FIXTURE_IDENTIFIER . '_' . $appendIdentifier);

        $product = $productFixtureSimple->getProduct();
        $extensionAttributes = $product->getExtensionAttributes();
        $stockItem = $extensionAttributes->getStockItem();

        $stockItem->setQty($quantity);
        $stockItem->setIsInStock($stockStatus);
        $extensionAttributes->setStockItem($stockItem);
        $this->stockRegistry->updateStockItemBySku(
            productSku: $product->getSku(),
            stockItem: $stockItem,
        );

        return $productFixtureSimple;
    }
    /**
     * @param string $appendIdentifier
     * @param int[] $websiteIds
     * @param array<string, AttributeInterface> $configurableAttributes
     * @param ProductInterface[] $configurableVariants
     * @param bool $stockStatus
     * @param int $status
     * @param int $visibility
     * @param array<string, mixed> $data
     *
     * @return ProductFixture
     * @throws CouldNotSaveException
     * @throws InputException
     * @throws StateException
     */
    private function createConfigurableProductFixture(
        string $appendIdentifier,
        array $websiteIds,
        array $configurableAttributes,
        array $configurableVariants,
        bool $stockStatus,
        int $status = ProductStatus::STATUS_ENABLED,
        int $visibility = ProductVisibility::VISIBILITY_BOTH,
        array $data = [],
    ): ProductFixture {
        $this->createProduct(
            productData: [
                'key' => self::FIXTURE_IDENTIFIER . '_' . $appendIdentifier,
                'sku' => self::FIXTURE_IDENTIFIER . '_' . $appendIdentifier,
                'name' => self::FIXTURE_NAME . ' : ' . $appendIdentifier,
                'status' => $status,
                'visibility' => $visibility,
                'in_stock' => $stockStatus,
                'qty' => (int)$stockStatus,
                'type_id' => Configurable::TYPE_CODE,
                'website_ids' => $websiteIds,
                'configurable_attributes' => $configurableAttributes,
                'variants' => $configurableVariants,
                'data' => $data,
            ],
        );

        $configurableProductFixture = $this->productFixturePool->get(
            key: self::FIXTURE_IDENTIFIER . '_' . $appendIdentifier,
        );
        $configurableProduct = $configurableProductFixture->getProduct();

        $extensionAttributes = $configurableProduct->getExtensionAttributes();
        if (!$extensionAttributes) {
            $extensionAttributes = $this->objectManager->create(ProductExtensionInterface::class);
        }

        $configurableProduct->setStockData(
            stockData: [
                'manage_stock' => 1,
                'is_in_stock' => (int)$stockStatus,
            ],
        );
        $stockItem = $this->stockItemFactory->create();
        $stockItem->setManageStock(true);
        $stockItem->setQty((int)$stockStatus);
        $stockItem->setIsQtyDecimal(false);
        $stockItem->setIsInStock($stockStatus);

        $extensionAttributes->setStockItem($stockItem);
        $configurableProduct->setExtensionAttributes($extensionAttributes);

        return new ProductFixture(
            product: $this->productRepository->save($configurableProduct),
        );
    }
}
