<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Model\Source;

use Klevu\Configuration\Traits\EnumTrait;

enum Aspect: int
{
    use EnumTrait;

    case NONE = 0;
    case ALL = 1;
    case ATTRIBUTES = 2;
    case RELATIONS = 3;
    case PRICE = 5;
    case STOCK = 6;
    case VISIBILITY = 7;

    /**
     * @return string
     */
    public function label(): string
    {
        return match($this) //phpcs:ignore PHPCompatibility.Variables.ForbiddenThisUseContexts.OutsideObjectContext
        {
            self::NONE => 'Nothing',
            self::ALL => 'Everything',
            self::ATTRIBUTES => 'Attributes',
            self::RELATIONS => 'Relations',
            self::PRICE => 'Price',
            self::STOCK => 'Stock',
            self::VISIBILITY => 'Visibility',
        };
    }
}
