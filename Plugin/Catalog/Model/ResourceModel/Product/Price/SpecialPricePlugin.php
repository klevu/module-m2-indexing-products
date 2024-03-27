<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Plugin\Catalog\Model\ResourceModel\Product\Price;

use Klevu\Indexing\Model\Update\Entity;
use Klevu\IndexingApi\Service\EntityUpdateResponderServiceInterface;
use Klevu\IndexingApi\Service\Provider\ProductIdProviderInterface;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\Data\SpecialPriceInterface;
use Magento\Catalog\Model\ResourceModel\Product\Price\SpecialPrice;

class SpecialPricePlugin
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
     * @param EntityUpdateResponderServiceInterface $responderService
     * @param ProductIdProviderInterface $productIdProvider
     */
    public function __construct(
        EntityUpdateResponderServiceInterface $responderService,
        ProductIdProviderInterface $productIdProvider,
    ) {
        $this->responderService = $responderService;
        $this->productIdProvider = $productIdProvider;
    }

    /**
     * @param SpecialPrice $subject
     * @param bool $result
     * @param SpecialPriceInterface[] $prices
     *
     * @return bool
     */
    public function afterUpdate(
        // phpcs:ignore SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter
        SpecialPrice $subject,
        bool $result,
        array $prices,
    ): bool {
        $this->handlePriceChanges($prices);

        return $result;
    }

    /**
     * @param SpecialPrice $subject
     * @param bool $result
     * @param SpecialPriceInterface[] $prices
     *
     * @return bool
     */
    public function afterDelete(
        // phpcs:ignore SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter
        SpecialPrice $subject,
        bool $result,
        array $prices,
    ): bool {
        $this->handlePriceChanges($prices);

        return $result;
    }

    /**
     * @param mixed[] $prices
     *
     * @return void
     */
    private function handlePriceChanges(array $prices): void
    {
        $entityIds = $this->getEntityIdsFromPrices($prices);
        if ($entityIds) {
            $this->responderService->execute([
                Entity::ENTITY_IDS => $entityIds,
                EntityUpdateResponderServiceInterface::CHANGED_ATTRIBUTES => [
                    ProductInterface::PRICE,
                ],
            ]);
        }
    }

    /**
     * @param SpecialPriceInterface[] $prices
     *
     * @return int[]
     */
    private function getEntityIdsFromPrices(array $prices): array
    {
        $skus = array_map(
            callback: static fn (SpecialPriceInterface $specialPrice): string => ($specialPrice->getSku()),
            array: $prices,
        );

        return $this->productIdProvider->getBySkus($skus);
    }
}
