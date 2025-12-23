<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Service\Determiner;

use Klevu\Configuration\Service\Provider\ScopeProviderInterface;
use Klevu\IndexingApi\Service\Determiner\IsIndexableConditionInterface;
use Klevu\IndexingProducts\Service\Provider\ProductStockStatusProviderInterface;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\CatalogInventory\Model\Spi\StockRegistryProviderInterface;
use Magento\Cms\Api\Data\PageInterface;
use Magento\Framework\Api\ExtensibleDataInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ObjectManager;
use Magento\Store\Api\Data\StoreInterface;
use Psr\Log\LoggerInterface;

class OutOfStockProductsIsIndexableCondition implements IsIndexableConditionInterface
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
     * @deprecated 3.1.0
     * no longer in use
     *
     * @var StockRegistryProviderInterface|null
     */
    private readonly ?StockRegistryProviderInterface $stockRegistryProvider; // @phpstan-ignore-line
    /**
     * @deprecated 3.3.0
     *  no longer in use
     * @see ProductStockStatusProviderInterface
     *
     * @var StockRegistryInterface
     */
    private readonly StockRegistryInterface $stockRegistry;
    /**
     * @var ProductStockStatusProviderInterface
     */
    private readonly ProductStockStatusProviderInterface $productStockStatusProvider;

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param LoggerInterface $logger
     * @param ScopeProviderInterface $scopeProvider
     * @param StockRegistryProviderInterface|null $stockRegistryProvider
     * @param StockRegistryInterface|null $stockRegistry
     * @param ProductStockStatusProviderInterface|null $productStockStatusProvider
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        LoggerInterface $logger,
        ScopeProviderInterface $scopeProvider,
        ?StockRegistryProviderInterface $stockRegistryProvider = null,
        ?StockRegistryInterface $stockRegistry = null,
        ?ProductStockStatusProviderInterface $productStockStatusProvider = null,
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->logger = $logger;
        $this->scopeProvider = $scopeProvider;
        $this->stockRegistryProvider = $stockRegistryProvider;

        $objectManager = ObjectManager::getInstance();
        $this->stockRegistry = $stockRegistry
            ?: $objectManager->get(StockRegistryInterface::class);
        $this->productStockStatusProvider = $productStockStatusProvider
            ?: $objectManager->get(ProductStockStatusProviderInterface::class);
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
        $isProductInStock = $this->productStockStatusProvider->get(
            product: $product,
            store: $store,
        );
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
}
