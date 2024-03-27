<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Service\Provider;

use Magento\Catalog\Api\Data\ProductInterface;

interface BundlePriceTypeProviderInterface
{
    /**
     * @param ProductInterface $product
     *
     * @return float|null
     */
    public function get(ProductInterface $product): ?float;
}
