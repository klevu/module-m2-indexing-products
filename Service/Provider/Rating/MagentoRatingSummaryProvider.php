<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Service\Provider\Rating;

use Klevu\IndexingApi\Service\Provider\Rating\RatingSummaryProviderInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Review\Model\Rating;
use Magento\Review\Model\RatingFactory;
use Magento\Store\Model\StoreManagerInterface;

class MagentoRatingSummaryProvider implements RatingSummaryProviderInterface
{
    /**
     * @var RatingFactory
     */
    private readonly RatingFactory $ratingFactory;
    /**
     * @var StoreManagerInterface
     */
    private readonly StoreManagerInterface $storeManager;

    /**
     * @param RatingFactory $ratingFactory
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        RatingFactory $ratingFactory,
        StoreManagerInterface $storeManager,
    ) {
        $this->ratingFactory = $ratingFactory;
        $this->storeManager = $storeManager;
    }

    /**
     * @param int $productId
     * @param int $storeId
     *
     * @return Rating|null
     * @throws NoSuchEntityException
     */
    public function get(int $productId, int $storeId): ?Rating
    {
        /**
         * Initially this method contained lazy-load for ratings for each store
         *   e.g. $this->ratingSummaries[$storeId] = $ratingSummary
         * However, if ratings are assigned to a review in a loop,
         *   then the first rating is cached and reused for each subsequent call.
         * Therefore, lazy-load has been removed.
         */
        $ratingSummary = $this->getRatingSummary($productId, $storeId);

        return null !== $ratingSummary->getData(MagentoAverageRatingProvider::RATING_COUNT)
            ? $ratingSummary
            : null;
    }

    /**
     * @param int $productId
     * @param int $storeId
     *
     * @return Rating
     * @throws NoSuchEntityException
     */
    private function getRatingSummary(int $productId, int $storeId): Rating
    {
        $this->storeManager->setCurrentStore(store: $storeId);
        /** @var Rating $rating */
        $rating = $this->ratingFactory->create();
        /**
         * Note: With "onlyForCurrentStore: true", $rating->getEntitySummary returns Rating.
         * With "onlyForCurrentStore: false", $rating->getEntitySummary returns array<string, Rating>
         *   with storeId as the key, neither of which is correctly type hinted in Magento :-(
         * @var Rating $ratingSummary
         */
        $ratingSummary = $rating->getEntitySummary(
            entityPkValue: $productId,
            onlyForCurrentStore: true,
        );

        return $ratingSummary;
    }
}
