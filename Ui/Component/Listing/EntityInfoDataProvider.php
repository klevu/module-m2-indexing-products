<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Ui\Component\Listing;

use Klevu\Configuration\Service\Provider\ApiKeysProviderInterface;
use Klevu\Configuration\Service\Provider\StoresProviderInterface;
use Klevu\IndexingApi\Api\Data\EntitySyncConditionsValuesInterface;
use Klevu\IndexingApi\Api\Data\IndexingEntityInterface;
use Klevu\IndexingApi\Service\Provider\EntitySyncConditionsValuesProviderInterface;
use Klevu\IndexingApi\Service\Provider\IndexingEntityProviderInterface;
use Klevu\IndexingProducts\Service\Provider\ProductStatusProviderInterface;
use Klevu\IndexingProducts\Service\Provider\ProductStockStatusProviderInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product\Type;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable as ConfigurableType;
use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\Search\ReportingInterface;
use Magento\Framework\Api\Search\SearchCriteriaBuilder;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\View\Element\UiComponent\DataProvider\DataProvider;

class EntityInfoDataProvider extends DataProvider
{
    /**
     * @var EntitySyncConditionsValuesProviderInterface 
     */
    private readonly EntitySyncConditionsValuesProviderInterface $entitySyncConditionsValuesProvider;

    /**
     * @param string $name
     * @param string $primaryFieldName
     * @param string $requestFieldName
     * @param ReportingInterface $reporting
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param RequestInterface $request
     * @param FilterBuilder $filterBuilder
     * @param EntitySyncConditionsValuesProviderInterface $entitySyncConditionsValuesProvider
     * @param mixed[] $meta
     * @param mixed[] $data
     */
    public function __construct(
        string $name,
        string $primaryFieldName,
        string $requestFieldName,
        ReportingInterface $reporting,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        RequestInterface $request,
        FilterBuilder $filterBuilder,
        EntitySyncConditionsValuesProviderInterface $entitySyncConditionsValuesProvider,
        array $meta = [],
        array $data = [],
    ) {
        parent::__construct(
            $name,
            $primaryFieldName,
            $requestFieldName,
            $reporting,
            $searchCriteriaBuilder,
            $request,
            $filterBuilder,
            $meta,
            $data,
        );

        $this->entitySyncConditionsValuesProvider = $entitySyncConditionsValuesProvider;
        $this->prepareUpdateUrl();
    }
    
    public function getData(): array
    {
        $targetId = $this->request->getParam('target_id');
        
        $entitySyncConditionsValues = $this->entitySyncConditionsValuesProvider->get(
            targetEntityType: 'KLEVU_PRODUCT',
            targetEntityId: (int)$targetId,
        );
        
        $items = array_filter(
            array: array_map(
                callback: [$this, 'formatRecord'],
                array: $entitySyncConditionsValues,
            ),
        );
        
        return [
            'items' => $items,
            'totalRecords' => count($items),
        ];
    }

    /**
     * @param EntitySyncConditionsValuesInterface $entitySyncConditionsValues
     *
     * @return mixed[]
     */
    private function formatRecord(
        EntitySyncConditionsValuesInterface $entitySyncConditionsValues,
    ): array {
        $targetEntity = $entitySyncConditionsValues->getTargetEntity();
        $websiteIds = method_exists($targetEntity, 'getWebsiteIds')
            ? $targetEntity->getWebsiteIds()
            : [];
        $assignedToWebsite = in_array(
            needle: $entitySyncConditionsValues->getStore()?->getWebsiteId(),
            haystack: $websiteIds,
            strict: false,
        );

        $syncConditionsValues = $entitySyncConditionsValues->getSyncConditionsValues();
        $isEnabled = $syncConditionsValues['is_enabled'] ?? null;
        $isInStock = $syncConditionsValues['is_in_stock'] ?? null;
        $isSalable = $targetEntity?->isSalable() ?? null;
        $isAvailable = $targetEntity?->isAvailable() ?? null;

        return [
            'target_id' => $targetEntity?->getId(),
            'api_key' => $entitySyncConditionsValues->getApiKey(),
            'store_code' => $entitySyncConditionsValues->getStore()?->getCode() ?? '',
            'target_parent_id' => $entitySyncConditionsValues->getTargetParentEntity()?->getId() ?? '',
            'is_discovered' => $entitySyncConditionsValues->getIndexingEntity()
                ? __('Yes')
                : __('No'),
            'is_indexable' => $entitySyncConditionsValues->getIsIndexable()
                ? __('Yes')
                : __('No'),
            'assigned_to_website' => $assignedToWebsite
                ? __('Yes')
                : __('No'),
            'product_status' => match ($isEnabled) {
                true => __('Enabled'),
                false => __('Disabled'),
                default => __('n/a'),
            },
            'stock_status' => match ($isInStock) {
                true => __('In Stock'),
                false => __('Out Of Stock'),
                default => __('n/a'),
            },
            'is_salable' => match ($isSalable) {
                true => __('Yes'),
                false => __('No'),
                default => __('n/a'),
            },
            'is_available' => match ($isAvailable) {
                true => __('Yes'),
                false => __('No'),
                default => __('n/a'),
            },
        ];
    }
}
