<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Plugin\Catalog\Ui\Component\Listing\Columns;

use Klevu\Configuration\Service\Provider\ApiKeysProviderInterface;
use Magento\Catalog\Ui\Component\Listing\Columns\ProductActions;

class ProductActionsPlugin
{
    /**
     * @var ApiKeysProviderInterface
     */
    private readonly ApiKeysProviderInterface $apiKeysProvider;

    /**
     * @param ApiKeysProviderInterface $apiKeysProvider
     */
    public function __construct(
        ApiKeysProviderInterface $apiKeysProvider,
    ) {
        $this->apiKeysProvider = $apiKeysProvider;
    }

    /**
     * @param ProductActions $subject
     * @param mixed[] $result
     *
     * @return mixed[]
     */
    public function afterPrepareDataSource(
        ProductActions $subject,
        array $result,
    ): array {
        if (!$this->apiKeysProvider->get([])) {
            return $result;
        }

        if (isset($result['data']['items'])) {
            // phpcs:ignore SlevomatCodingStandard.PHP.DisallowReference.DisallowedAssigningByReference
            foreach ($result['data']['items'] as &$item) {
                $entityId = $item['entity_id'] ?? null;
                if (!$entityId) {
                    continue;
                }
                $item[$subject->getData('name')]['klevu_sync_info'] = $this->createAction(
                    entityId: (int)$item['entity_id'],
                );
            }
        }

        return $result;
    }

    /**
     * @param int $entityId
     *
     * @return mixed[][][][]
     */
    private function createAction(int $entityId): array
    {
        return [
            'href' => '#',
            'ariaLabel' => __('Klevu Sync Info'),
            'label' => __('Klevu Sync Info'),
            'hidden' => false,
            'callback' => $this->createCallback(entityId: $entityId),
        ];
    }

    /**
     * @param int $entityId
     *
     * @return mixed[][][]
     */
    private function createCallback(int $entityId): array
    {
        $productListing = 'product_listing.product_listing';
        $modal = $productListing . '.klevu_product_sync_info_modal';
        $container = $modal . '.klevu_product_sync_info_container';
        $infoFieldset = $container . '.klevu_product_entity_info_fieldset';
        $infoListing = $infoFieldset . '.klevu_product_entity_info_listing';
        $actionFieldset = $container . '.klevu_product_sync_next_action_fieldset';
        $actionListing = $actionFieldset . '.klevu_product_sync_next_action_listing';
        $historyFieldset = $container . '.klevu_product_sync_history_fieldset';
        $historyListing = $historyFieldset . '.klevu_product_sync_history';
        $historyConsolidationFieldset = $container . '.klevu_product_sync_consolidated_history_fieldset';
        $historyConsolidationListing = $historyConsolidationFieldset . '.klevu_product_sync_history_consolidation';

        return [
            [
                'provider' => $infoListing,
                'target' => 'destroyInserted',
            ],
            [
                'provider' => $actionListing,
                'target' => 'destroyInserted',
            ],
            [
                'provider' => $historyListing,
                'target' => 'destroyInserted',
            ],
            [
                'provider' => $historyConsolidationListing,
                'target' => 'destroyInserted',
            ],
            [
                'provider' => $infoListing,
                'target' => 'updateData',
                'params' => [
                    'target_id' => $entityId,
                ],
            ],
            [
                'provider' => $actionListing,
                'target' => 'updateData',
                'params' => [
                    'target_id' => $entityId,
                ],
            ],
            [
                'provider' => $historyListing,
                'target' => 'updateData',
                'params' => [
                    'target_id' => $entityId,
                ],
            ],
            [
                'provider' => $historyConsolidationListing,
                'target' => 'updateData',
                'params' => [
                    'target_id' => $entityId,
                ],
            ],
            [
                'provider' => $modal,
                'target' => 'setTitle',
                'params' => __(
                    'Klevu Entity Sync Information: Entity ID: %1',
                    $entityId,
                ),
            ],
            [
                'provider' => $modal,
                'target' => 'openModal',
            ],
        ];
    }
}
