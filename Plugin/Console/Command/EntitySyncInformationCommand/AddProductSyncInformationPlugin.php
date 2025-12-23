<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Plugin\Console\Command\EntitySyncInformationCommand;

use Klevu\Indexing\Console\Command\EntitySyncInformationCommand;
use Klevu\IndexingApi\Api\Data\EntitySyncConditionsValuesInterface;
use Klevu\IndexingProducts\Constants;
use Klevu\IndexingProducts\Plugin\PlatformPipelines\Service\ConfigurationOverridesBuilder\StockStatusPlugin;
use Klevu\IndexingProducts\Service\Determiner\DisabledProductsIsIndexableCondition;
use Klevu\IndexingProducts\Service\Determiner\OutOfStockProductsIsIndexableCondition;
use Klevu\IndexingProducts\Service\Provider\ProductStatusProviderInterface;
use Klevu\IndexingProducts\Service\Provider\ProductStockStatusProvider;
use Klevu\IndexingProducts\Service\Provider\ProductStockStatusProviderInterface;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class AddProductSyncInformationPlugin
{
    /**
     * @var ScopeConfigInterface
     */
    private readonly ScopeConfigInterface $scopeConfig;
    /**
     * @var ProductRepositoryInterface
     */
    private readonly ProductRepositoryInterface $productRepository;
    /**
     * @var ProductStatusProviderInterface
     */
    private readonly ProductStatusProviderInterface $productStatusProvider;
    /**
     * @var ProductStockStatusProviderInterface
     */
    private readonly ProductStockStatusProviderInterface $productStockStatusProvider;

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param ProductRepositoryInterface $productRepository
     * @param ProductStatusProviderInterface $productStatusProvider
     * @param ProductStockStatusProviderInterface $productStockStatusProvider
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        ProductRepositoryInterface $productRepository,
        ProductStatusProviderInterface $productStatusProvider,
        ProductStockStatusProviderInterface $productStockStatusProvider,
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->productRepository = $productRepository;
        $this->productStatusProvider = $productStatusProvider;
        $this->productStockStatusProvider = $productStockStatusProvider;
    }

    /**
     * @param array<array<string, mixed>> $result
     *
     * @return array<array<string, mixed>>
     */
    public function afterGetGlobalConfigurationData(
        EntitySyncInformationCommand $subject, // phpcs:ignore SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter, Generic.Files.LineLength.TooLong
        array $result,
        string $targetEntityType,
    ): array {
        if ('KLEVU_PRODUCT' !== $targetEntityType) {
            return $result;
        }

        $productStockStatusCalculationMethod = $this->scopeConfig->getValue(
            ProductStockStatusProvider::XML_PATH_STOCK_STATUS_CALCULATION_METHOD,
            ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
        );
        $useProviderForPipelinesStockStatus = $this->scopeConfig->isSetFlag(
            StockStatusPlugin::CONFIG_PATH_USE_PROVIDER_FOR_PIPELINES_STOCK_STATUS,
            ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
        );
        $excludeDisabledProducts = $this->scopeConfig->isSetFlag(
            DisabledProductsIsIndexableCondition::XML_PATH_EXCLUDE_DISABLED_PRODUCTS,
            ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
        );
        $excludeOosProducts = $this->scopeConfig->isSetFlag(
            OutOfStockProductsIsIndexableCondition::XML_PATH_EXCLUDE_OOS_PRODUCTS,
            ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
        );
        $result[] = [
            'Product Stock Status Calculation Method' => $productStockStatusCalculationMethod,
            'Use Provider For Pipelines Stock Status' => __(
                $useProviderForPipelinesStockStatus ? 'Yes' : 'No',
            )->render(),
            'Exclude Disabled Products' => __($excludeDisabledProducts ? 'Yes' : 'No')->render(),
            'Exclude OOS Products' => __($excludeOosProducts ? 'Yes' : 'No')->render(),
        ];

        return $result;
    }

    /**
     * @param EntitySyncInformationCommand $subject
     * @param array<array<string, mixed>> $result
     * @param string $targetEntityType
     * @param EntitySyncConditionsValuesInterface $conditionsValuesData
     *
     * @return array<array<string, mixed>>
     */
    public function afterGetSyncInformationSummaryData(
        EntitySyncInformationCommand $subject, // phpcs:ignore SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter, Generic.Files.LineLength.TooLong
        array $result,
        string $targetEntityType,
        EntitySyncConditionsValuesInterface $conditionsValuesData,
    ): array {
        if ('KLEVU_PRODUCT' !== $targetEntityType) {
            return $result;
        }

        $store = $conditionsValuesData->getStore();
        $syncEnabled = $this->scopeConfig->getValue(
            Constants::XML_PATH_PRODUCT_SYNC_ENABLED,
            ($store)
                ? ScopeInterface::SCOPE_STORE
                : ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
            $store?->getId() ?? 0,
        );
        $result[] = [
            'Sync Enabled' => __($syncEnabled ? 'Yes' : 'No')->render(),
        ];

        return $result;
    }

    /**
     * @param EntitySyncInformationCommand $subject
     * @param array<array<string, mixed>> $result
     * @param string $targetEntityType
     * @param EntitySyncConditionsValuesInterface $conditionsValuesData
     *
     * @return array<array<string, mixed>>
     */
    public function afterGetRealTimeSyncInformationData(
        EntitySyncInformationCommand $subject, // phpcs:ignore SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter, Generic.Files.LineLength.TooLong
        array $result,
        string $targetEntityType,
        EntitySyncConditionsValuesInterface $conditionsValuesData,
    ): array {
        if ('KLEVU_PRODUCT' !== $targetEntityType) {
            return $result;
        }

        $product = $conditionsValuesData->getTargetEntity();
        if (!($product instanceof ProductInterface)) {
            return $result;
        }

        $websiteIds = method_exists($product, 'getWebsiteIds')
            ? $product->getWebsiteIds()
            : [];
        $assignedToWebsite = in_array(
            needle: $conditionsValuesData->getStore()?->getWebsiteId(),
            haystack: $websiteIds,
            strict: false,
        );

        $result[] = [
            'Product assigned to website' => __($assignedToWebsite ? 'Yes' : 'No')->render(),
            'is_salable' => __($product->isSalable() ? 'Yes' : 'No')->render(),
            'is_available' => __($product->isAvailable() ? 'Yes' : 'No')->render(),
        ];
        $result[] = [
            'Calculated Product Status' => $this->productStatusProvider->get(
                    product: $product,
                    store: $conditionsValuesData->getStore(),
                    parentProduct: $conditionsValuesData->getTargetParentEntity(),
                )
                ? __('Enabled')->render()
                : __('Disabled')->render(),
            'Calculated Stock Status' => $this->productStockStatusProvider->get(
                    product: $product,
                    store: $conditionsValuesData->getStore(),
                    parentProduct: $conditionsValuesData->getTargetParentEntity(),
                )
                ? __('In Stock')->render()
                : __('Out Of Stock')->render(),
        ];

        return $result;
    }
}
