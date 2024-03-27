<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Service\Provider\Rating;

use Klevu\IndexingApi\Service\Provider\Rating\AverageRatingProviderInterface;
use Klevu\IndexingApi\Service\Provider\Rating\RatingProviderInterface;
use Klevu\IndexingApi\Service\Provider\Rating\ReviewCountProviderInterface;
use Klevu\IndexingProducts\Exception\InvalidRatingValue;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;

class RatingProvider implements RatingProviderInterface
{
    /**
     * @var StoreManagerInterface
     */
    private readonly StoreManagerInterface $storeManager;
    /**
     * @var AverageRatingProviderInterface
     */
    private readonly AverageRatingProviderInterface $averageRatingProvider;
    /**
     * @var ReviewCountProviderInterface
     */
    private readonly ReviewCountProviderInterface $reviewCountProvider;
    /**
     * @var int
     */
    private readonly int $ratingOutOf;
    /**
     * @var int
     */
    private readonly int $numberOfStars;

    /**
     * @param StoreManagerInterface $storeManager
     * @param AverageRatingProviderInterface $averageRatingProvider
     * @param ReviewCountProviderInterface $reviewCountProvider
     * @param int $ratingOutOf
     * @param int $numberOfStars
     */
    public function __construct(
        StoreManagerInterface $storeManager,
        AverageRatingProviderInterface $averageRatingProvider,
        ReviewCountProviderInterface $reviewCountProvider,
        int $ratingOutOf = 100,
        int $numberOfStars = 5,
    ) {
        $this->storeManager = $storeManager;
        $this->averageRatingProvider = $averageRatingProvider;
        $this->reviewCountProvider = $reviewCountProvider;
        $this->ratingOutOf = $ratingOutOf;
        $this->numberOfStars = $numberOfStars;
    }

    /**
     * @inheritdoc
     *
     * @param int $productId
     * @param int|null $storeId
     *
     * @return array<string, int|float>
     * @throws NoSuchEntityException|InvalidRatingValue
     */
    public function get(int $productId, ?int $storeId = null): array
    {
        $storeId = $this->getStoreId($storeId);

        return [
            static::PRODUCT_ID => $productId,
            static::STORE_ID => $storeId,
            static::RATING => $this->calculateAverageRating($productId, $storeId),
            static::COUNT => $this->reviewCountProvider->get(productId: $productId, storeId: $storeId),
        ];
    }

    /**
     * @param int|null $storeId
     *
     * @return int
     * @throws NoSuchEntityException
     */
    private function getStoreId(?int $storeId): int
    {
        if (null === $storeId) {
            $store = $this->storeManager->getStore();
            $storeId = $store->getId();
        }

        return $storeId;
    }

    /**
     * @param int $productId
     * @param int $storeId
     *
     * @return float|null
     * @throws InvalidRatingValue
     * @throws NoSuchEntityException
     */
    private function calculateAverageRating(int $productId, int $storeId): ?float
    {
        $rating = $this->averageRatingProvider->get(productId: $productId, storeId: $storeId);
        if (!$rating) {
            return null;
        }

        return $this->numberOfStars * $rating / $this->ratingOutOf;
    }
}
