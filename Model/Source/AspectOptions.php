<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;

class AspectOptions implements OptionSourceInterface
{
    /**
     * @return mixed[]
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => Aspect::NONE->value, 'label' => __(Aspect::NONE->label())],
            ['value' => Aspect::ALL->value, 'label' => __(Aspect::ALL->label())],
            ['value' => Aspect::ATTRIBUTES->value, 'label' => __(Aspect::ATTRIBUTES->label())],
            ['value' => Aspect::RELATIONS->value, 'label' => __(Aspect::RELATIONS->label())],
            ['value' => Aspect::PRICE->value, 'label' => __(Aspect::PRICE->label())],
            ['value' => Aspect::STOCK->value, 'label' => __(Aspect::STOCK->label())],
            ['value' => Aspect::VISIBILITY->value, 'label' => __(Aspect::VISIBILITY->label())],
        ];
    }
}
