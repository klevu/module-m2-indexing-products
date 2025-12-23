<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Service\Provider;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Store\Api\Data\StoreInterface;

interface ProductStatusProviderInterface
{
    /**
     * @param ProductInterface $product
     * @param StoreInterface|null $store
     * @param ProductInterface|null $parentProduct
     *
     * @return bool
     */
    public function get(
        ProductInterface $product,
        ?StoreInterface $store,
        ?ProductInterface $parentProduct = null,
    ): bool;
}
