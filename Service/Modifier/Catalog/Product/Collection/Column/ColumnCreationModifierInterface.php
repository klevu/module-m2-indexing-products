<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Service\Modifier\Catalog\Product\Collection\Column;

use Klevu\IndexingProducts\Model\ResourceModel\Catalog\ConfigurableVariantProduct\Collection;
use Magento\Catalog\Api\Data\ProductAttributeInterface;

interface ColumnCreationModifierInterface
{
    /**
     * @param Collection $collection
     * @param ProductAttributeInterface $attribute
     * @param string $columnName
     *
     * @return void
     */
    public function execute(
        Collection $collection,
        ProductAttributeInterface $attribute,
        string $columnName,
    ): void;
}
