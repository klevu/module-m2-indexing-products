<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;

class StockStatusCalculationMethodOptions implements OptionSourceInterface
{
    /**
     * @return mixed[]
     */
    public function toOptionArray(): array
    {
        return [
            [
                'value' => StockStatusCalculationMethod::STOCK_ITEM->value,
                'label' => __(StockStatusCalculationMethod::STOCK_ITEM->label()),
            ],
            [
                'value' => StockStatusCalculationMethod::STOCK_REGISTRY->value,
                'label' => __(StockStatusCalculationMethod::STOCK_REGISTRY->label()),
            ],
            [
                'value' => StockStatusCalculationMethod::IS_AVAILABLE->value,
                'label' => __(StockStatusCalculationMethod::IS_AVAILABLE->label()),
            ],
            [
                'value' => StockStatusCalculationMethod::IS_SALABLE->value,
                'label' => __(StockStatusCalculationMethod::IS_SALABLE->label()),
            ],
        ];
    }
}