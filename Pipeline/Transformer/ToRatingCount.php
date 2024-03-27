<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Pipeline\Transformer;

use Klevu\IndexingApi\Service\Provider\Rating\RatingProviderInterface;
use Klevu\IndexingProducts\Exception\InvalidRatingValue;
use Klevu\IndexingProducts\Pipeline\Provider\Argument\Transformer\ToRatingCountArgumentProvider;
use Klevu\Pipelines\Exception\Transformation\InvalidInputDataException;
use Klevu\Pipelines\Model\ArgumentIterator;
use Klevu\Pipelines\Transformer\TransformerInterface;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Framework\Exception\NoSuchEntityException;

class ToRatingCount implements TransformerInterface
{
    /**
     * @var RatingProviderInterface
     */
    private readonly RatingProviderInterface $ratingProvider;
    /**
     * @var ToRatingCountArgumentProvider
     */
    private ToRatingCountArgumentProvider $argumentProvider;

    /**
     * @param RatingProviderInterface $ratingProvider
     * @param ToRatingCountArgumentProvider $argumentProvider
     */
    public function __construct(
        RatingProviderInterface $ratingProvider,
        ToRatingCountArgumentProvider $argumentProvider,
    ) {
        $this->ratingProvider = $ratingProvider;
        $this->argumentProvider = $argumentProvider;
    }

    /**
     * @param mixed $data
     * @param ArgumentIterator|null $arguments
     * @param \ArrayAccess<int|string, mixed>|null $context
     *
     * @return int
     * @throws InvalidRatingValue
     * @throws NoSuchEntityException
     */
    public function transform(
        mixed $data,
        ?ArgumentIterator $arguments = null,
        // phpcs:ignore SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter
        ?\ArrayAccess $context = null,
    ): int {
        if (null === $data) {
            return 0;
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

        $rating = $this->ratingProvider->get(productId: (int)$data->getId(), storeId: (int)$storeIdArgumentValue);

        return (int)($rating[RatingProviderInterface::COUNT] ?? 0);
    }
}
