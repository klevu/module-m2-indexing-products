<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Plugin\CatalogRule\Model\Indexer;

use Klevu\Indexing\Model\Update\Entity;
use Klevu\IndexingApi\Service\EntityUpdateResponderServiceInterface;
use Klevu\IndexingApi\Service\Provider\CatalogRule\CatalogRuleProductIdsProviderInterface;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\CatalogRule\Model\Indexer\IndexBuilder;

class IndexBuilderPlugin
{
    /**
     * @var EntityUpdateResponderServiceInterface
     */
    private readonly EntityUpdateResponderServiceInterface $responderService;
    /**
     * @var CatalogRuleProductIdsProviderInterface
     */
    private readonly CatalogRuleProductIdsProviderInterface $catalogRuleProductIdsProvider;

    /**
     * @param EntityUpdateResponderServiceInterface $responderService
     * @param CatalogRuleProductIdsProviderInterface $catalogRuleProductIdsProvider
     */
    public function __construct(
        EntityUpdateResponderServiceInterface $responderService,
        CatalogRuleProductIdsProviderInterface $catalogRuleProductIdsProvider,
    ) {
        $this->responderService = $responderService;
        $this->catalogRuleProductIdsProvider = $catalogRuleProductIdsProvider;
    }

    /**
     * @param IndexBuilder $subject
     * @param void $result
     * @param int $productId
     *
     * @return void
     */
    public function afterReindexById(
        // phpcs:ignore SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter
        IndexBuilder $subject,
        // phpcs:ignore SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter
        mixed $result,
        mixed $productId,
    ): void {
        $this->updateIndexingEntities([$productId]);

        // Magento\CatalogRule\Model\Indexer\IndexBuilder::reindexById returns void
    }

    /**
     * @param IndexBuilder $subject
     * @param void $result
     * @param int[] $productIds
     *
     * @return void
     */
    public function afterReindexByIds(
        // phpcs:ignore SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter
        IndexBuilder $subject,
        // phpcs:ignore SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter
        mixed $result,
        array $productIds,
    ): void {
        $this->updateIndexingEntities($productIds);

        // Magento\CatalogRule\Model\Indexer\IndexBuilder::reindexByIds returns void
    }

    /**
     * @param IndexBuilder $subject
     * @param callable $proceed
     *
     * @return void
     */
    public function aroundReindexFull(
        // phpcs:ignore SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter
        IndexBuilder $subject,
        callable $proceed,
    ): void {
        $initialProductIds = $this->catalogRuleProductIdsProvider->get();
        /**
         * \Magento\CatalogRule\Model\Indexer\IndexBuilder::reindexFull "$proceed()" returns void
         * so we do not need to return it from this method
         */
        $proceed();

        $finalProductIds = $this->catalogRuleProductIdsProvider->get();
        $entityIds = array_values(
            array_unique(
                array_merge($initialProductIds, $finalProductIds),
            ),
        );
        $this->updateIndexingEntities($entityIds);
    }

    /**
     * @param int[] $productIds
     *
     * @return void
     */
    private function updateIndexingEntities(array $productIds): void
    {
        $this->responderService->execute(data: [
            Entity::ENTITY_IDS => array_map('intval', $productIds),
            EntityUpdateResponderServiceInterface::CHANGED_ATTRIBUTES => [
                ProductInterface::PRICE,
            ],
        ]);
    }
}
