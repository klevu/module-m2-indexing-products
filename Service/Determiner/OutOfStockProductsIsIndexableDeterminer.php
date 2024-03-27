<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Service\Determiner;

use Klevu\Configuration\Service\Provider\ScopeProviderInterface;
use Klevu\IndexingApi\Service\Determiner\IsIndexableDeterminerInterface;
use Magento\Catalog\Api\Data\ProductExtensionInterface; //@phpstan-ignore-line
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Cms\Api\Data\PageInterface;
use Magento\Framework\Api\ExtensibleDataInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\DataObject;
use Magento\Store\Api\Data\StoreInterface;
use Psr\Log\LoggerInterface;

class OutOfStockProductsIsIndexableDeterminer implements IsIndexableDeterminerInterface
{
    private const XML_PATH_EXCLUDE_OOS_PRODUCTS = 'klevu/indexing/exclude_oos_products';

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
     * @param ScopeConfigInterface $scopeConfig
     * @param LoggerInterface $logger
     * @param ScopeProviderInterface $scopeProvider
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        LoggerInterface $logger,
        ScopeProviderInterface $scopeProvider,
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->logger = $logger;
        $this->scopeProvider = $scopeProvider;
    }

    /**
     * @param ExtensibleDataInterface|PageInterface $entity
     * @param StoreInterface $store
     *
     * @return bool
     */
    public function execute(
        ExtensibleDataInterface|PageInterface $entity,
        StoreInterface $store,
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
        /** @var ProductExtensionInterface $extensionAttributes */
        $extensionAttributes = $product->getExtensionAttributes();
        $stockItem = $extensionAttributes->getStockItem();

        return $stockItem
            ? (bool)$stockItem->getIsInStock()
            : $this->getStockFromProduct($product);
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
