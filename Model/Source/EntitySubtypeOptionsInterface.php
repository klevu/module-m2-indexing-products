<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;

interface EntitySubtypeOptionsInterface extends OptionSourceInterface
{
    /**
     * @return string[]
     */
    public function getValues(): array;
}
