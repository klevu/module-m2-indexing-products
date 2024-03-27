<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Service\Provider\PipelineContext;

use Klevu\Configuration\Service\Provider\StoreScopeProviderInterface;
use Klevu\PlatformPipelines\Api\PipelineContextProviderInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\Store;

class ConfigProvider implements PipelineContextProviderInterface
{
    /**
     * @var StoreScopeProviderInterface
     */
    private readonly StoreScopeProviderInterface $storeScopeProvider;
    /**
     * @var ScopeConfigInterface
     */
    private readonly ScopeConfigInterface $scopeConfig;
    /**
     * @var mixed[][]
     */
    private array $contextForStoreId = [];

    /**
     * @param StoreScopeProviderInterface $storeScopeProvider
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        StoreScopeProviderInterface $storeScopeProvider,
        ScopeConfigInterface $scopeConfig,
    ) {
        $this->storeScopeProvider = $storeScopeProvider;
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * @return $this
     */
    public function get(): self
    {
        return $this;
    }

    /**
     * @return mixed[]
     */
    public function getForCurrentStore(): array
    {
        $currentStore = $this->storeScopeProvider->getCurrentStore();
        $storeId = (int)$currentStore?->getId() ?: Store::DEFAULT_STORE_ID;

        if (!array_key_exists($storeId, $this->contextForStoreId)) {
            $this->contextForStoreId[$storeId] = [
                'image_width' => $this->getProductImageWidth($storeId),
                'image_height' => $this->getProductImageHeight($storeId),
                'placeholder_image' => $this->getPlaceholderImage($storeId),
            ];
        }

        return $this->contextForStoreId[$storeId];
    }

    /**
     * @param int $storeId
     *
     * @return int|null
     */
    private function getProductImageWidth(int $storeId): ?int
    {
        $value = $this->scopeConfig->getValue(
            'klevu/indexing/image_width_product',
            ScopeInterface::SCOPE_STORES,
            $storeId,
        );

        return $value
            ? (int)$value
            : null;
    }

    /**
     * @param int $storeId
     *
     * @return int|null
     */
    private function getProductImageHeight(int $storeId): ?int
    {
        $value = $this->scopeConfig->getValue(
            'klevu/indexing/image_height_product',
            ScopeInterface::SCOPE_STORES,
            $storeId,
        );

        return $value
            ? (int)$value
            : null;
    }

    /**
     * @param int $storeId
     *
     * @return string|null
     */
    private function getPlaceholderImage(int $storeId): ?string
    {
        $value = $this->scopeConfig->getValue(
            'catalog/placeholder/klevu_image_placeholder',
            ScopeInterface::SCOPE_STORES,
            $storeId,
        );
        if (!$value) {
            $value = $this->scopeConfig->getValue(
                'catalog/placeholder/image_placeholder',
                ScopeInterface::SCOPE_STORES,
                $storeId,
            );
        }

        return $value;
    }
}
