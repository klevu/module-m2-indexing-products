<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Service\Provider;

use Magento\Catalog\Api\Data\ProductInterface;

interface MinPriceProductProviderInterface
{
    /**
     * @param ProductInterface $product
     *
     * @return ProductInterface|null
     * @throws \LogicException
     */
    public function get(ProductInterface $product): ?ProductInterface;
}
