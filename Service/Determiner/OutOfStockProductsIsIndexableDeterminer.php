<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Service\Determiner;

use Klevu\Configuration\Service\Provider\ScopeProviderInterface;
use Klevu\IndexingApi\Service\Determiner\IsIndexableDeterminerInterface;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\CatalogInventory\Api\Data\StockItemInterface;
use Magento\CatalogInventory\Model\Spi\StockRegistryProviderInterface;
use Magento\Cms\Api\Data\PageInterface;
use Magento\Framework\Api\ExtensibleDataInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\DataObject;
use Magento\Store\Api\Data\StoreInterface;
use Psr\Log\LoggerInterface;

class OutOfStockProductsIsIndexableDeterminer implements IsIndexableDeterminerInterface
{
    public const XML_PATH_EXCLUDE_OOS_PRODUCTS = 'klevu/indexing/exclude_oos_products';

    /**
     * @var ScopeConfigInterface
     */
    private readonly ScopeConfigInterface $scopeConfig;
    /**
     * @var LoggerInterface
     */
    private readonly LoggerInterface $logger;
    /**
     * @var ScopeProviderInterface
     */
    private readonly ScopeProviderInterface $scopeProvider;
    /**
     * @var StockRegistryProviderInterface
     */
    private readonly StockRegistryProviderInterface $stockRegistryProvider;

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param LoggerInterface $logger
     * @param ScopeProviderInterface $scopeProvider
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        LoggerInterface $logger,
        ScopeProviderInterface $scopeProvider,
        ?StockRegistryProviderInterface $stockRegistryProvider = null,
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->logger = $logger;
        $this->scopeProvider = $scopeProvider;

        $objectManager = ObjectManager::getInstance();
        $this->stockRegistryProvider = $stockRegistryProvider
            ?: $objectManager->get(StockRegistryProviderInterface::class);
    }

    /**
     * @param ExtensibleDataInterface|PageInterface $entity
     * @param StoreInterface $store
     * @param string $entitySubtype
     *
     * @return bool
     */
    public function execute(
        ExtensibleDataInterface|PageInterface $entity,
        StoreInterface $store,
        string $entitySubtype = '', // phpcs:ignore SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter
    ): bool {
        if (!($entity instanceof ProductInterface)) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Invalid argument provided for "$entity". Expected %s, received %s.',
                    ProductInterface::class,
                    get_debug_type($entity),
                ),
            );
        }

        return !$this->isCheckEnabled() || $this->isIndexable(product: $entity, store: $store);
    }

    /**
     * @return bool
     */
    private function isCheckEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_EXCLUDE_OOS_PRODUCTS,
            ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
            null,
        );
    }

    /**
     * @param ProductInterface $product
     * @param StoreInterface $store
     *
     * @return bool
     */
    private function isIndexable(ProductInterface $product, StoreInterface $store): bool
    {
        $isProductInStock = $this->isProductInStock(product: $product);
        if (!$isProductInStock) {
            $currentScope = $this->scopeProvider->getCurrentScope();
            $this->scopeProvider->setCurrentScope(scope: $store);
            $this->logger->debug(
                // phpcs:ignore Generic.Files.LineLength.TooLong
                message: 'Store ID: {storeId} Product ID: {productId} not indexable due to Stock Status: {stock} in {method}',
                context: [
                    'storeId' => $store->getId(),
                    'productId' => $product->getId(),
                    'stock' => $isProductInStock,
                    'method' => __METHOD__,
                ],
            );
            if ($currentScope->getScopeObject()) {
                $this->scopeProvider->setCurrentScope(scope: $currentScope->getScopeObject());
            } else {
                $this->scopeProvider->unsetCurrentScope();
            }
        }

        return $isProductInStock;
    }

    /**
     * @param ProductInterface $product
     *
     * @return bool
     */
    private function isProductInStock(ProductInterface $product): bool
    {
        $stockItem = $this->getStockItemFromProductExtensionAttributes($product)
            ?: $this->getStockItemFromStockRegistryProvider($product);

        return $stockItem
            ? (bool)$stockItem->getIsInStock()
            : $this->getStockFromProduct($product);
    }

    /**
     * @param ProductInterface $product
     *
     * @return StockItemInterface|null
     */
    private function getStockItemFromProductExtensionAttributes(ProductInterface $product): ?StockItemInterface
    {
        $extensionAttributes = $product->getExtensionAttributes();

        return $extensionAttributes->getStockItem();
    }

    /**
     * @param ProductInterface $product
     *
     * @return StockItemInterface|null
     */
    private function getStockItemFromStockRegistryProvider(ProductInterface $product): ?StockItemInterface
    {
        $scope = $this->scopeProvider->getCurrentScope();
        $scopeObject = $scope->getScopeObject();
        if ($scope->getScopeType() === 'stores') {
            $scopeId = $scopeObject->getWebsiteId();
        } else {
            $scopeId = $scope->getScopeId();
        }

        return $this->stockRegistryProvider->getStockItem(
            productId: $product->getId(),
            scopeId: $scopeId,
        );
    }

    /**
     * @param ProductInterface $product
     *
     * @return bool
     */
    private function getStockFromProduct(ProductInterface $product): bool
    {
        /** @var DataObject & ProductInterface $product */
        $stockData = $product->getData('quantity_and_stock_status');
        $return = (is_array($stockData) && ($stockData['is_in_stock'] ?? false))
            ? $stockData['is_in_stock']
            : $stockData;

        return (bool)$return;
    }
}
