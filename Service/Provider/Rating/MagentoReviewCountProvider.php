<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Service\Provider\Rating;

use Klevu\IndexingApi\Service\Provider\Rating\ReviewCountProviderInterface;
use Klevu\IndexingProducts\Exception\InvalidRatingValue;
use Magento\Review\Model\Review;
use Magento\Review\Model\ReviewFactory;

class MagentoReviewCountProvider implements ReviewCountProviderInterface
{
    /**
     * @var ReviewFactory
     */
    private readonly ReviewFactory $reviewFactory;
    /**
     * @var bool
     */
    private readonly bool $approvedOnly;

    /**
     * @param ReviewFactory $reviewFactory
     * @param bool $approvedOnly
     */
    public function __construct(
        ReviewFactory $reviewFactory,
        bool $approvedOnly = true,
    ) {
        $this->reviewFactory = $reviewFactory;
        $this->approvedOnly = $approvedOnly;
    }

    /**
     * @param int $productId
     * @param int $storeId
     *
     * @return int
     * @throws InvalidRatingValue
     */
    public function get(int $productId, int $storeId): int
    {
        $reviewCount = $this->getReviewCount(productId: $productId, storeId: $storeId);
        if ($reviewCount < 0) {
            throw new InvalidRatingValue(
                __(
                    'Invalid review count returned. Expected non-negative integer, received %1',
                    $reviewCount,
                ),
            );
        }

        return $reviewCount;
    }

    /**
     * @param int $productId
     * @param int $storeId
     *
     * @return int
     */
    private function getReviewCount(int $productId, int $storeId): int
    {
        /** @var Review $review */
        $review = $this->reviewFactory->create();

        return (int)$review->getTotalReviews(
            entityPkValue: $productId,
            approvedOnly: $this->approvedOnly,
            storeId: $storeId,
        );
    }
}
