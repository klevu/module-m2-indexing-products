<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Exception;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Phrase;

class ConflictingStockStatusesForTargetIdsException extends LocalizedException
{
    /**
     * @var array<int, array<string, array<int|null>>>
     */
    private readonly array $targetIdsByStockStatus;

    /**
     * @param array<int, array<string, array<int|null>>> $targetIdsByStockStatus
     * @param Phrase|null $phrase
     */
    public function __construct(
        array $targetIdsByStockStatus,
        ?Phrase $phrase = null,
    ) {
        $this->targetIdsByStockStatus = $targetIdsByStockStatus;

        parent::__construct(
            phrase: $phrase ?? __('Conflicting stock statuses found for target ids'),
            cause: null,
            code: 0,
        );
    }

    /**
     * @return array
     */
    public function getTargetIdsByStockStatus(): array
    {
        return $this->targetIdsByStockStatus;
    }
}
