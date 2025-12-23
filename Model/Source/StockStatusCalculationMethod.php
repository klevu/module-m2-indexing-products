<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Model\Source;

use Klevu\Configuration\Traits\EnumTrait;

enum StockStatusCalculationMethod: string
{
    use EnumTrait;

    case STOCK_ITEM = 'stock_item';
    case STOCK_REGISTRY = 'stock_registry';
    case IS_AVAILABLE = 'is_available';
    case IS_SALABLE = 'is_salable';

    /**
     * @return self
     */
    public static function default(): self
    {
        return self::STOCK_ITEM;
    }

    /**
     * @return string
     */
    public function label(): string
    {
        return match ($this) //phpcs:ignore PHPCompatibility.Variables.ForbiddenThisUseContexts.OutsideObjectContext
        {
            self::STOCK_ITEM => 'Stock Item',
            self::STOCK_REGISTRY => 'Stock Registry',
            self::IS_AVAILABLE => 'Is Available',
            self::IS_SALABLE => 'Is Salable',
        };
    }
}
