<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Service\Provider\Rating;

use Klevu\IndexingApi\Service\Provider\Rating\AverageRatingProviderInterface;
use Klevu\IndexingApi\Service\Provider\Rating\RatingSummaryProviderInterface;
use Klevu\IndexingProducts\Exception\InvalidRatingValue;
use Magento\Framework\Exception\NoSuchEntityException;

class MagentoAverageRatingProvider implements AverageRatingProviderInterface
{
    public const RATING_SUM = 'sum';
    public const RATING_COUNT = 'count';

    /**
     * @var RatingSummaryProviderInterface
     */
    private readonly RatingSummaryProviderInterface $ratingSummaryProvider;

    /**
     * @param RatingSummaryProviderInterface $ratingSummaryProvider
     */
    public function __construct(
        RatingSummaryProviderInterface $ratingSummaryProvider,
    ) {
        $this->ratingSummaryProvider = $ratingSummaryProvider;
    }

    /**
     * @param int $productId
     * @param int $storeId
     *
     * @return float|null
     * @throws InvalidRatingValue
     * @throws NoSuchEntityException
     */
    public function get(int $productId, int $storeId): ?float
    {
        $sum = $this->getRatingSum(productId: $productId, storeId: $storeId);
        $count = $this->getRatingCount(productId: $productId, storeId: $storeId);

        return ($sum && $count)
            ? ($sum / $count)
            : null;
    }

    /**
     * @param int $productId
     * @param int $storeId
     *
     * @return float|null
     * @throws InvalidRatingValue
     * @throws NoSuchEntityException
     */
    private function getRatingSum(int $productId, int $storeId): ?float
    {
        $ratingSummary = $this->ratingSummaryProvider->get(productId: $productId, storeId: $storeId);
        if (null === $ratingSummary) {
            return null;
        }
        $ratingSum = $ratingSummary->getData(key: static::RATING_SUM);

        if (null !== $ratingSum && !(is_numeric($ratingSum) && (float)$ratingSum >= 0)) {
            throw new InvalidRatingValue(
                __(
                    'Invalid rating sum returned. Expected non-negative numeric value or null, received %1. '
                    . 'Product ID: %2 and Store ID: %3',
                    $ratingSum,
                    $productId,
                    $storeId,
                ),
            );
        }

        return $ratingSum
            ? (float)$ratingSum
            : null;
    }

    /**
     * @param int $productId
     * @param int $storeId
     *
     * @return int
     * @throws InvalidRatingValue
     * @throws NoSuchEntityException
     */
    private function getRatingCount(int $productId, int $storeId): int
    {
        $ratingSummary = $this->ratingSummaryProvider->get(productId: $productId, storeId: $storeId);
        if (null === $ratingSummary) {
            return 0;
        }
        $ratingCount = $ratingSummary->getData(key: static::RATING_COUNT);

        if (null !== $ratingCount && !(is_numeric($ratingCount) && (int)$ratingCount > 0)) {
            throw new InvalidRatingValue(
                __(
                    'Invalid rating count returned. Expected positive numeric value or null, received %1. '
                    . 'Product ID: %2 and Store ID: %3',
                    $ratingCount,
                    $productId,
                    $storeId,
                ),
            );
        }

        return $ratingCount
            ? (int)$ratingCount
            : 0;
    }
}
