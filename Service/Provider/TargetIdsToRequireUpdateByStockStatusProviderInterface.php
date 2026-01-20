<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Service\Provider;

use Klevu\IndexingProducts\Exception\ConflictingStockStatusesForTargetIdsException;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Api\Data\StoreInterface;

interface TargetIdsToRequireUpdateByStockStatusProviderInterface
{
    /**
     * @param int $productId
     * @param StoreInterface[] $stores
     *
     * @return array<int, array<string, int>>
     * @throws ConflictingStockStatusesForTargetIdsException
     * @throws NoSuchEntityException
     */
    public function getByProductId(int $productId, array $stores): array;

    /**
     * @param string $sku
     * @param StoreInterface[] $stores
     *
     * @return array<int, array<string, int>>
     * @throws ConflictingStockStatusesForTargetIdsException
     * @throws NoSuchEntityException
     */
    public function getBySku(string $sku, array $stores): array;

    /**
     * @param ProductInterface $product
     * @param StoreInterface[] $stores
     *
     * @return array<int, array<string, int>>
     * @throws ConflictingStockStatusesForTargetIdsException
     */
    public function get(ProductInterface $product, array $stores): array;
}
