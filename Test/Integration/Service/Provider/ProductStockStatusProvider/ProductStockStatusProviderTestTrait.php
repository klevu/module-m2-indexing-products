<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Test\Integration\Service\Provider\ProductStockStatusProvider;

use Klevu\TestFixtures\Catalog\Attribute\AttributeFixturePool;
use Klevu\TestFixtures\Store\StoreFixturesPool;
use Klevu\TestFixtures\Website\WebsiteFixturesPool;
use Magento\Catalog\Api\Data\ProductExtensionInterface;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product\Attribute\Source\Status as ProductStatus;
use Magento\Catalog\Model\Product\Type as ProductType;
use Magento\Catalog\Model\Product\Visibility as ProductVisibility;
use Magento\CatalogInventory\Api\Data\StockItemInterfaceFactory;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Eav\Api\Data\AttributeInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\GroupedProduct\Model\Product\Type\Grouped;
use TddWizard\Fixtures\Catalog\ProductFixture;
use TddWizard\Fixtures\Catalog\ProductFixturePool;

trait ProductStockStatusProviderTestTrait
{
    /**
     * @var ObjectManagerInterface|null
     */
    private ?ObjectManagerInterface $objectManager = null; // @phpstan-ignore-line
    /**
     * @var ProductRepositoryInterface|null
     */
    private ?ProductRepositoryInterface $productRepository = null; // @phpstan-ignore-line

    /**
     * @var StockRegistryInterface|mixed|null
     */
    private ?StockRegistryInterface $stockRegistry = null; // @phpstan-ignore-line
    /**
     * @var StockItemInterfaceFactory
     */
    private StockItemInterfaceFactory $stockItemFactory; // @phpstan-ignore-line
    /**
     * @var string
     */
    private string $fixtureIdentifier = '';
    /**
     * @var string
     */
    private string $fixtureName = '';

    /**
     * @return void
     */
    private function setUpProperties(): void
    {
        if (!(($this->objectManager ?? null) instanceof \Magento\Framework\ObjectManagerInterface)) {
            throw new \LogicException('ObjectManager is not defined');
        }

        $this->productRepository = $this->objectManager->get(ProductRepositoryInterface::class);
        $this->stockRegistry = $this->objectManager->get(StockRegistryInterface::class);
        $this->stockItemFactory = $this->objectManager->get(StockItemInterfaceFactory::class);

        if (property_exists($this, 'websiteFixturesPool')) {
            $this->websiteFixturesPool = $this->objectManager->get(WebsiteFixturesPool::class);
        }
        if (property_exists($this, 'storeFixturesPool')) {
            $this->storeFixturesPool = $this->objectManager->get(StoreFixturesPool::class);
        }
        if (property_exists($this, 'attributeFixturePool')) {
            $this->attributeFixturePool = $this->objectManager->get(AttributeFixturePool::class);
        }
        if (property_exists($this, 'productFixturePool')) {
            $this->productFixturePool = $this->objectManager->get(ProductFixturePool::class);
        }
    }

    /**
     * @return void
     * @throws \Exception
     */
    private function deleteFixtures(): void
    {
        if (property_exists($this, 'productFixturePool')) {
            $this->productFixturePool->rollback();
        }
        if (property_exists($this, 'attributeFixturePool')) {
            $this->attributeFixturePool->rollback();
        }
        if (property_exists($this, 'storeFixturesPool')) {
            $this->storeFixturesPool->rollback();
        }
        if (property_exists($this, 'websiteFixturesPool')) {
            $this->websiteFixturesPool->rollback();
        }
    }

    /**
     * @return array<string, object>
     * @throws \Exception
     */
    private function createWebsiteAndStoreFixtures(): array
    {
        $this->createWebsite(
            websiteData: [
                'key' => 'klevu_test_productstockstatus_1',
                'code' => 'klevu_test_productstockstatus_1',
            ],
        );
        $websiteFixture1 = $this->websiteFixturesPool->get('klevu_test_productstockstatus_1');

        $this->createStore(
            storeData: [
                'key' => 'klevu_test_productstockstatus_1',
                'code' => 'klevu_test_productstockstatus_1',
                'name' => 'Klevu Test: Product Stock Status Provider (Simple) (1)',
                'is_active' => true,
                'website_id' => $websiteFixture1->getId(),
            ],
        );
        $storeFixture1 = $this->storeFixturesPool->get('klevu_test_productstockstatus_1');

        $this->createWebsite(
            websiteData: [
                'key' => 'klevu_test_productstockstatus_2',
                'code' => 'klevu_test_productstockstatus_2',
            ],
        );
        $websiteFixture2 = $this->websiteFixturesPool->get('klevu_test_productstockstatus_2');

        $this->createStore(
            storeData: [
                'key' => 'klevu_test_productstockstatus_2',
                'code' => 'klevu_test_productstockstatus_2',
                'name' => 'Klevu Test: Product Stock Status Provider (Simple) (2)',
                'is_active' => true,
                'website_id' => (int)$websiteFixture2->getId(),
            ],
        );
        $storeFixture2 = $this->storeFixturesPool->get('klevu_test_productstockstatus_2');

        return [
            'websiteFixture1' => $websiteFixture1,
            'storeFixture1' => $storeFixture1,
            'websiteFixture2' => $websiteFixture2,
            'storeFixture2' => $storeFixture2,
        ];
    }

    /**
     * @param string $appendIdentifier
     * @param array $websiteIds
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
                'key' => $this->fixtureIdentifier . '_' . $appendIdentifier,
                'sku' => $this->fixtureIdentifier . '_' . $appendIdentifier,
                'name' => $this->fixtureName . ' : ' . $appendIdentifier,
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
        $productFixtureSimple = $this->productFixturePool->get($this->fixtureIdentifier . '_' . $appendIdentifier);

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
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     * @throws \Magento\Framework\Exception\InputException
     * @throws \Magento\Framework\Exception\StateException
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
                'key' => $this->fixtureIdentifier . '_' . $appendIdentifier,
                'sku' => $this->fixtureIdentifier . '_' . $appendIdentifier,
                'name' => $this->fixtureName . ' : ' . $appendIdentifier,
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
            key: $this->fixtureIdentifier . '_' . $appendIdentifier,
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

    private function createGroupedProductFixture(
        string $appendIdentifier,
        array $websiteIds,
        array $groupedVariantFixtures,
        bool $stockStatus,
        int $status = ProductStatus::STATUS_ENABLED,
        int $visibility = ProductVisibility::VISIBILITY_BOTH,
        array $data = [],
    ): ProductFixture {
        $this->createProduct(
            productData: [
                'key' => $this->fixtureIdentifier . '_' . $appendIdentifier,
                'sku' => $this->fixtureIdentifier . '_' . $appendIdentifier,
                'name' => $this->fixtureName . ' : ' . $appendIdentifier,
                'status' => $status,
                'visibility' => $visibility,
                'in_stock' => $stockStatus,
                'qty' => (int)$stockStatus,
                'type_id' => Grouped::TYPE_CODE,
                'website_ids' => $websiteIds,
                'linked_products' => $groupedVariantFixtures,
                'data' => $data,
            ],
        );

        $groupedProductFixture = $this->productFixturePool->get(
            key: $this->fixtureIdentifier . '_' . $appendIdentifier,
        );
        $groupedProduct = $groupedProductFixture->getProduct();

        $extensionAttributes = $groupedProduct->getExtensionAttributes();
        if (!$extensionAttributes) {
            $extensionAttributes = $this->objectManager->create(ProductExtensionInterface::class);
        }

        $groupedProduct->setStockData(
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
        $groupedProduct->setExtensionAttributes($extensionAttributes);

        return new ProductFixture(
            product: $this->productRepository->save($groupedProduct),
        );
    }
}
