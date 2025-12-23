<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Service\Provider;

use Klevu\IndexingProducts\Model\Source\StockStatusCalculationMethod;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
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
     * @param LoggerInterface $logger
     * @param ScopeConfigInterface $scopeConfig
     * @param StockRegistryInterface $stockRegistry
     */
    public function __construct(
        LoggerInterface $logger,
        ScopeConfigInterface $scopeConfig,
        StockRegistryInterface $stockRegistry,
    ) {
        $this->logger = $logger;
        $this->scopeConfig = $scopeConfig;
        $this->stockRegistry = $stockRegistry;
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

        return match ($stockStatusCalculationMethod) {
            StockStatusCalculationMethod::STOCK_ITEM => $this->getFromStockItem($product, $store),
            StockStatusCalculationMethod::STOCK_REGISTRY => $this->getFromStockRegistry($product, $store),
            StockStatusCalculationMethod::IS_AVAILABLE => $this->getFromIsAvailable($product),
            StockStatusCalculationMethod::IS_SALABLE => $this->getFromIsSalable($product),
        };
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
     *
     * @return bool
     */
    private function getFromIsAvailable(
        ProductInterface $product,
    ): bool {
        return $product->isAvailable();
    }

    /**
     * @param ProductInterface $product
     *
     * @return bool
     */
    private function getFromIsSalable(
        ProductInterface $product,
    ): bool {
        return $product->isSalable();
    }
}
