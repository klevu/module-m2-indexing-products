<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Observer;

use Klevu\Indexing\Model\Update\Entity;
use Klevu\IndexingApi\Service\EntityUpdateResponderServiceInterface;
use Klevu\IndexingApi\Service\Provider\AttributesToWatchProviderInterface;
use Klevu\IndexingApi\Service\Provider\ProductIdProviderInterface;
use Magento\CatalogImportExport\Model\Import\Product as ProductImport;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class ProductImportObserver implements ObserverInterface
{
    /**
     * @var EntityUpdateResponderServiceInterface
     */
    private readonly EntityUpdateResponderServiceInterface $responderService;
    /**
     * @var ProductIdProviderInterface
     */
    private readonly ProductIdProviderInterface $productIdProvider;
    /**
     * @var AttributesToWatchProviderInterface
     */
    private readonly AttributesToWatchProviderInterface $attributesToWatchProvider;

    /**
     * @param EntityUpdateResponderServiceInterface $responderService
     * @param ProductIdProviderInterface $productIdProvider
     * @param AttributesToWatchProviderInterface $attributesToWatchProvider
     */
    public function __construct(
        EntityUpdateResponderServiceInterface $responderService,
        ProductIdProviderInterface $productIdProvider,
        AttributesToWatchProviderInterface $attributesToWatchProvider,
    ) {
        $this->responderService = $responderService;
        $this->productIdProvider = $productIdProvider;
        $this->attributesToWatchProvider = $attributesToWatchProvider;
    }

    /**
     * @param Observer $observer
     *
     * @return void
     */
    public function execute(Observer $observer): void
    {
        $event = $observer->getEvent();
        $bunch = $event->getData('bunch');
        if (is_array($bunch)) {
            /**
             * This observer will update trigger updates for ALL API keys.
             * We could get the website_id/store_id from the bunch data,
             * however at this point the products have already been updated,
             * we have no way of knowing what website_id/store_id was set to before the import happened
             * (unless we wrap the entire import in an around plugin :-( )
             * Therefore, we update ALL API keys.
             */
            $data = [
                Entity::ENTITY_IDS => $this->extractEntityIds(bunch: $bunch),
                EntityUpdateResponderServiceInterface::CHANGED_ATTRIBUTES => $this->extractAttributes(bunch: $bunch),
            ];
            $this->responderService->execute($data);
        }
    }

    /**
     * @param mixed[][] $bunch
     *
     * @return int[]
     */
    private function extractEntityIds(array $bunch): array
    {
        $skus = array_unique(
            array_map(
                callback: static fn (array $row): string => ($row[ProductImport::COL_SKU] ?? null),
                array: $bunch,
            ),
        );

        return array_values(
            array: $this->productIdProvider->getBySkus(skus: $skus),
        );
    }

    /**
     * @param mixed[][] $bunch
     *
     * @return string[]
     */
    private function extractAttributes(array $bunch): array
    {
        $return = [];
        $attributeCodes = $this->attributesToWatchProvider->getAttributeCodes();
        $attributesInBunch = array_keys($bunch[0] ?? []);
        foreach ($attributesInBunch as $attributeCode) {
            if (in_array($attributeCode, $attributeCodes, true)) {
                $return[] = $attributeCode;
            }
        }

        return $return;
    }
}
