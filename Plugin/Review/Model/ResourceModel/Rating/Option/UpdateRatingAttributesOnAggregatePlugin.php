<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Plugin\Review\Model\ResourceModel\Rating\Option;

use Klevu\IndexingApi\Service\UpdateRatingServiceInterface;
use Klevu\IndexingProducts\Exception\KlevuProductAttributeMissingException;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Review\Model\ResourceModel\Rating\Option as RatingOptionResourceModel;
use Psr\Log\LoggerInterface;

class UpdateRatingAttributesOnAggregatePlugin
{
    /**
     * @var ProductRepositoryInterface
     */
    private readonly ProductRepositoryInterface $productRepository;
    /**
     * @var LoggerInterface
     */
    private readonly LoggerInterface $logger;
    /**
     * @var UpdateRatingServiceInterface
     */
    private readonly UpdateRatingServiceInterface $updateRatingService;

    /**
     * @param ProductRepositoryInterface $productRepository
     * @param LoggerInterface $logger
     * @param UpdateRatingServiceInterface $updateRatingService
     */
    public function __construct(
        ProductRepositoryInterface $productRepository,
        LoggerInterface $logger,
        UpdateRatingServiceInterface $updateRatingService,
    ) {
        $this->productRepository = $productRepository;
        $this->logger = $logger;
        $this->updateRatingService = $updateRatingService;
    }

    /**
     * @param RatingOptionResourceModel $subject
     * @param void $result
     * @param int $ratingId
     * @param int $entityPkValue
     *
     * @return void
     */
    public function afterAggregateEntityByRatingId(
        // phpcs:ignore SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter
        RatingOptionResourceModel $subject,
        // phpcs:ignore SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter
        mixed $result,
        // phpcs:ignore SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter
        mixed $ratingId,
        mixed $entityPkValue,
    ): void {
        try {
            $product = $this->productRepository->getById($entityPkValue);
            $this->updateRatingService->execute($product);
        } catch (NoSuchEntityException | KlevuProductAttributeMissingException $exception) {
            $this->logger->error(
                message: 'Method: {method}, Error: {message}',
                context: [
                    'method' => __METHOD__,
                    'message' => $exception->getMessage(),
                ],
            );
        }
    }
}
