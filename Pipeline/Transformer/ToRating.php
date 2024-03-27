<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Pipeline\Transformer;

use Klevu\IndexingApi\Service\Provider\Rating\RatingProviderInterface;
use Klevu\IndexingProducts\Exception\InvalidRatingValue;
use Klevu\IndexingProducts\Pipeline\Provider\Argument\Transformer\ToRatingArgumentProvider;
use Klevu\Pipelines\Exception\Transformation\InvalidInputDataException;
use Klevu\Pipelines\Model\ArgumentIterator;
use Klevu\Pipelines\Transformer\TransformerInterface;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Framework\Exception\NoSuchEntityException;

class ToRating implements TransformerInterface
{
    /**
     * @var RatingProviderInterface
     */
    private readonly RatingProviderInterface $ratingProvider;
    /**
     * @var ToRatingArgumentProvider
     */
    private ToRatingArgumentProvider $argumentProvider;

    /**
     * @param RatingProviderInterface $ratingProvider
     * @param ToRatingArgumentProvider $argumentProvider
     */
    public function __construct(
        RatingProviderInterface $ratingProvider,
        ToRatingArgumentProvider $argumentProvider,
    ) {
        $this->ratingProvider = $ratingProvider;
        $this->argumentProvider = $argumentProvider;
    }

    /**
     * @param mixed $data
     * @param ArgumentIterator|null $arguments
     * @param \ArrayAccess<int|string, mixed>|null $context
     *
     * @return float
     * @throws InvalidRatingValue
     * @throws NoSuchEntityException
     */
    public function transform(
        mixed $data,
        ?ArgumentIterator $arguments = null,
        // phpcs:ignore SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter
        ?\ArrayAccess $context = null,
    ): float {
        if (null === $data) {
            return 0.0;
        }
        if (!($data instanceof ProductInterface)) {
            throw new InvalidInputDataException(
                transformerName: $this::class,
                expectedType: ProductInterface::class,
                arguments: $arguments,
                data: $data,
            );
        }
        $storeIdArgumentValue = $this->argumentProvider->getStoreIdArgumentValue(
            arguments: $arguments,
            extractionPayload: $data,
            extractionContext: $context,
        );

        $ratingSummary = $this->ratingProvider->get(
            productId: (int)$data->getId(),
            storeId: (int)$storeIdArgumentValue,
        );

        return (float)($ratingSummary[RatingProviderInterface::RATING] ?? 0);
    }
}
