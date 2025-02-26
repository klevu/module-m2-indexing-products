<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Service\Action;

use Klevu\IndexingProducts\Model\ResourceModel\Product\Collection as ProductCollection;

interface JoinStockToCollectionActionInterface
{
    /**
     * @param ProductCollection $collection
     *
     * @return void
     */
    public function execute(ProductCollection $collection): void;
}
