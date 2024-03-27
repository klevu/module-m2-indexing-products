<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Service\Determiner;

use Klevu\Configuration\Service\Provider\ScopeProviderInterface;
use Klevu\IndexingApi\Service\Determiner\IsIndexableDeterminerInterface;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Cms\Api\Data\PageInterface;
use Magento\Framework\Api\ExtensibleDataInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Api\Data\StoreInterface;
use Psr\Log\LoggerInterface;

class DisabledProductsIsIndexableDeterminer implements IsIndexableDeterminerInterface
{
    private const XML_PATH_EXCLUDE_DISABLED_PRODUCTS = 'klevu/indexing/exclude_disabled_products';

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
        return !$this->isCheckEnabled() || $this->isIndexable(entity: $entity, store: $store);
    }

    /**
     * @return bool
     */
    private function isCheckEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_EXCLUDE_DISABLED_PRODUCTS,
            ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
            null,
        );
    }

    /**
     * @param ProductInterface $entity
     * @param StoreInterface $store
     *
     * @return bool
     */
    private function isIndexable(ProductInterface $entity, StoreInterface $store): bool
    {
        $isProductEnabled = $this->isProductEnabled(entity: $entity);
        if (!$isProductEnabled) {
            $currentScope = $this->scopeProvider->getCurrentScope();
            $this->scopeProvider->setCurrentScope(scope: $store);
            $this->logger->debug(
                // phpcs:ignore Generic.Files.LineLength.TooLong
                message: 'Store ID: {storeId} Product ID: {productId} not indexable due to Status: {status} in {method}',
                context: [
                    'storeId' => $store->getId(),
                    'productId' => $entity->getId(),
                    'status' => $entity->getStatus(),
                    'method' => __METHOD__,
                ],
            );
            if ($currentScope->getScopeObject()) {
                $this->scopeProvider->setCurrentScope(scope: $currentScope->getScopeObject());
            } else {
                $this->scopeProvider->unsetCurrentScope();
            }
        }

        return $isProductEnabled;
    }

    /**
     * @param ProductInterface $entity
     *
     * @return bool
     */
    private function isProductEnabled(ProductInterface $entity): bool
    {
        return (int)$entity->getStatus() !== Status::STATUS_DISABLED;
    }
}
