<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Pipeline\Transformer;

use Klevu\IndexingProducts\Pipeline\Provider\Argument\Transformer\ToFinalPriceArgumentProvider;
use Klevu\Pipelines\Exception\Transformation\InvalidInputDataException;
use Klevu\Pipelines\Exception\TransformationException;
use Klevu\Pipelines\Model\ArgumentIterator;
use Klevu\Pipelines\Transformer\TransformerInterface;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\Product;

class ToFinalPrice implements TransformerInterface
{
    /**
     * @var ToFinalPriceArgumentProvider
     */
    private readonly ToFinalPriceArgumentProvider $argumentProvider;

    /**
     * @param ToFinalPriceArgumentProvider $argumentProvider
     */
    public function __construct(
        ToFinalPriceArgumentProvider $argumentProvider,
    ) {
        $this->argumentProvider = $argumentProvider;
    }

    /**
     * @param mixed $data
     * @param ArgumentIterator|null $arguments
     * @param \ArrayAccess<int|string, mixed>|null $context
     *
     * @return float
     * @throws TransformationException
     */
    public function transform(
        mixed $data,
        ?ArgumentIterator $arguments = null,
        // phpcs:ignore SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter
        ?\ArrayAccess $context = null,
    ): float {
        if (!($data instanceof ProductInterface)) {
            throw new InvalidInputDataException(
                transformerName: $this::class,
                expectedType: ProductInterface::class,
                arguments: $arguments,
                data: $data,
            );
        }
        $qty = $this->argumentProvider->getQuantityArgumentValue(
            arguments: $arguments,
            extractionPayload: $data,
            extractionContext: $context,
        );

        /** @var Product $data */
        return (float)$data->getFinalPrice($qty);
    }
}
