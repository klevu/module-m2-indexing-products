<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Service\Provider;

use Klevu\IndexingProducts\Model\Source\StockStatusCalculationMethod;
use Magento\Bundle\Model\Product\Type as BundleType;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product\Type as ProductType;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable as ConfigurableType;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\GroupedProduct\Model\Product\Type\Grouped as GroupedType;
use Magento\Store\Api\Data\StoreInterface;
use Psr\Log\LoggerInterface;

class ProductStockStatusProvider implements ProductStockStatusProviderInterface
{
    public const XML_PATH_STOCK_STATUS_CALCULATION_METHOD = 'klevu/indexing/product_stock_status_calculation_method';

    /**
     * @var LoggerInterface
     */
    private readonly LoggerInterface $logger;
    /**
     * @var ScopeConfigInterface
     */
    private readonly ScopeConfigInterface $scopeConfig;
    /**
     * @var StockRegistryInterface
     */
    private readonly StockRegistryInterface $stockRegistry;
    /**
     * @var ProductType
     */
    private readonly ProductType $productType;
    /**
     * @var ProductRepositoryInterface
     */
    private readonly ProductRepositoryInterface $productRepository;

    /**
     * @param LoggerInterface $logger
     * @param ScopeConfigInterface $scopeConfig
     * @param StockRegistryInterface $stockRegistry
     * @param ProductType|null $productType
     * @param ProductRepositoryInterface|null $productRepository
     */
    public function __construct(
        LoggerInterface $logger,
        ScopeConfigInterface $scopeConfig,
        StockRegistryInterface $stockRegistry,
        ?ProductType $productType = null,
        ?ProductRepositoryInterface $productRepository = null,
    ) {
        $this->logger = $logger;
        $this->scopeConfig = $scopeConfig;
        $this->stockRegistry = $stockRegistry;

        $objectManager = ObjectManager::getInstance();
        $this->productType = $productType ?? $objectManager->get(ProductType::class);
        $this->productRepository = $productRepository ?? $objectManager->get(ProductRepositoryInterface::class);
    }

    /**
     * @param ProductInterface $product
     * @param StoreInterface|null $store
     * @param ProductInterface|null $parentProduct
     *
     * @return bool
     */
    public function get(
        ProductInterface $product,
        ?StoreInterface $store,
        ?ProductInterface $parentProduct = null,
    ): bool {
        $websiteIdForStore = (int)$store->getWebsiteId();
        $websiteIdsForProduct = array_map(
            callback: 'intval',
            array: $product->getWebsiteIds(),
        );
        if (!in_array($websiteIdForStore, $websiteIdsForProduct, true)) {
            return false;
        }

        if (null !== $parentProduct) {
            $parentProductStockStatus = $this->get(
                product: $parentProduct,
                store: $store,
            );
            if (!$parentProductStockStatus) {
                return false;
            }
        }

        $stockStatusCalculationMethodConfigValue = $this->scopeConfig->getValue(
            static::XML_PATH_STOCK_STATUS_CALCULATION_METHOD,
            ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
            0,
        );
        try {
            $stockStatusCalculationMethod = StockStatusCalculationMethod::from(
                value: $stockStatusCalculationMethodConfigValue,
            );
        } catch (\ValueError $exception) {
            $stockStatusCalculationMethod = StockStatusCalculationMethod::default();

            $this->logger->warning(
                message: 'Invalid stock status calculation method; falling back to default.',
                context: [
                    'error' => $exception->getMessage(),
                    'stockStatusCalculationMethodConfigValue' => $stockStatusCalculationMethodConfigValue,
                    'fallbackValue' => $stockStatusCalculationMethod->value,
                ],
            );
        }

        $targetProductStockStatus = match ($stockStatusCalculationMethod) {
            StockStatusCalculationMethod::STOCK_ITEM => $this->getFromStockItem($product, $store),
            StockStatusCalculationMethod::STOCK_REGISTRY => $this->getFromStockRegistry($product, $store),
            StockStatusCalculationMethod::IS_AVAILABLE => $this->getFromIsAvailable($product, $store),
            StockStatusCalculationMethod::IS_SALABLE => $this->getFromIsSalable($product, $store),
        };

        return $targetProductStockStatus && $this->hasChildrenInStock(
            product: $product,
            store: $store,
        );
    }

    /**
     * @note Public method to allow plugins
     *
     * @param ProductInterface $product
     * @param StoreInterface $store
     *
     * @return bool
     */
    public function hasChildrenInStock(
        ProductInterface $product,
        StoreInterface $store,
    ): bool {
        $applicableTypeIds = [
            BundleType::TYPE_CODE,
            ConfigurableType::TYPE_CODE,
            GroupedType::TYPE_CODE,
        ];
        if (!in_array($product->getTypeId(), $applicableTypeIds, true)) {
            return true;
        }

        $productType = $this->productType->factory($product);
        $childProductIdsByOption = $productType->getChildrenIds(
            parentId: (int)$product->getId(),
            required: true,
        );

        $hasChildrenInStock = true;
        foreach ($childProductIdsByOption as $childProductIds) {
            $optionIsInStock = false;
            foreach ($childProductIds as $childProductId) {
                try {
                    $childProduct = $this->productRepository->getById(
                        productId: (int)$childProductId,
                        editMode: false,
                        storeId: (int)$store->getId(),
                    );
                } catch (NoSuchEntityException) {
                    continue;
                }

                $optionIsInStock = $this->get(
                    product: $childProduct,
                    store: $store,
                    parentProduct: null,
                );

                if ($optionIsInStock) {
                    break;
                }
            }

            if (!$optionIsInStock) {
                $hasChildrenInStock = false;
                break;
            }
        }

        return $hasChildrenInStock;
    }

    /**
     * @param ProductInterface $product
     * @param StoreInterface|null $store
     *
     * @return bool
     */
    private function getFromStockItem(
        ProductInterface $product,
        ?StoreInterface $store,
    ): bool {
        $extensionAttributes = $product->getExtensionAttributes();
        $stockItem = $extensionAttributes?->getStockItem();

        // Replicating original behaviour pre: 4.4.0
        return $stockItem
            ? (bool)$stockItem->getIsInStock()
            : $this->getFromStockRegistry($product, $store);
    }

    /**
     * @param ProductInterface $product
     * @param StoreInterface|null $store
     *
     * @return bool
     */
    private function getFromStockRegistry(
        ProductInterface $product,
        ?StoreInterface $store,
    ): bool {
        /**
         * MSI has a plugin on this method to get the correct stock.
         */
        $stockStatus = $this->stockRegistry->getStockStatus(
            productId: $product->getId(),
            scopeId: $store
                ? (int)$store->getWebsiteId()
                : null,
        );

        return (bool)$stockStatus->getStockStatus();
    }

    /**
     * @param ProductInterface $product
     * @param StoreInterface|null $store
     *
     * @return bool
     */
    private function getFromIsAvailable(
        ProductInterface $product,
        ?StoreInterface $store,
    ): bool {
        if ((int)$store->getId() === (int)$product->getStoreId()) {
            return $product->isAvailable();
        }

        try {
            $productInStore = $this->productRepository->getById(
                productId: (int)$product->getId(),
                editMode: false,
                storeId: (int)$store->getId(),
                forceReload: false,
            );
        } catch (NoSuchEntityException) {
            return false;
        }

        return $productInStore->isAvailable();
    }

    /**
     * @param ProductInterface $product
     * @param StoreInterface|null $store
     *
     * @return bool
     */
    private function getFromIsSalable(
        ProductInterface $product,
        ?StoreInterface $store,
    ): bool {
        if ((int)$store->getId() === (int)$product->getStoreId()) {
            return $product->isSalable();
        }

        try {
            $productInStore = $this->productRepository->getById(
                productId: (int)$product->getId(),
                editMode: false,
                storeId: (int)$store->getId(),
                forceReload: false,
            );
        } catch (NoSuchEntityException) {
            return false;
        }

        return $productInStore->isSalable();
    }
}
