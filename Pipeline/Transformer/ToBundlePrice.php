<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Pipeline\Transformer;

use Klevu\IndexingProducts\Pipeline\Provider\Argument\Transformer\ToBundlePriceArgumentProvider;
use Klevu\IndexingProducts\Service\Provider\BundlePriceProviderInterface;
use Klevu\Pipelines\Exception\Transformation\InvalidInputDataException;
use Klevu\Pipelines\Model\ArgumentIterator;
use Klevu\Pipelines\Transformer\TransformerInterface;
use Magento\Catalog\Api\Data\ProductInterface;

class ToBundlePrice implements TransformerInterface
{
    /**
     * @var ToBundlePriceArgumentProvider
     */
    private readonly ToBundlePriceArgumentProvider $argumentProvider;
    /**
     * @var BundlePriceProviderInterface
     */
    private readonly BundlePriceProviderInterface $bundlePriceProvider;

    /**
     * @param ToBundlePriceArgumentProvider $argumentProvider
     * @param BundlePriceProviderInterface $bundlePriceProvider
     */
    public function __construct(
        ToBundlePriceArgumentProvider $argumentProvider,
        BundlePriceProviderInterface $bundlePriceProvider,
    ) {
        $this->argumentProvider = $argumentProvider;
        $this->bundlePriceProvider = $bundlePriceProvider;
    }

    /**
     * @param mixed $data
     * @param ArgumentIterator|null $arguments
     * @param \ArrayAccess<int|string, mixed>|null $context
     *
     * @return float|null
     * @throws \InvalidArgumentException
     */
    public function transform(
        mixed $data,
        ?ArgumentIterator $arguments = null,
        // phpcs:ignore SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter
        ?\ArrayAccess $context = null,
    ): ?float {
        if (null === $data) {
            return null;
        }
        if (!($data instanceof ProductInterface)) {
            throw new InvalidInputDataException(
                transformerName: $this::class,
                expectedType: ProductInterface::class,
                arguments: $arguments,
                data: $data,
            );
        }
        $priceType = $this->argumentProvider->getPriceTypeArgument(
            arguments: $arguments,
            extractionPayload: $data,
            extractionContext: $context,
        );

        return $this->bundlePriceProvider->get(product: $data, priceType: $priceType);
    }
}
