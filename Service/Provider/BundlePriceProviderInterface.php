<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Service\Provider;

use Magento\Catalog\Api\Data\ProductInterface;

interface BundlePriceProviderInterface
{
    /**
     * @param ProductInterface $product
     * @param string $priceType
     *
     * @return float|null
     * @throws \InvalidArgumentException
     */
    public function get(ProductInterface $product, string $priceType): ?float;
}
