<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Pipeline\Transformer;

use Klevu\IndexingProducts\Service\Provider\MinPriceProductProviderInterface;
use Klevu\Pipelines\Exception\Transformation\InvalidInputDataException;
use Klevu\Pipelines\Model\ArgumentIterator;
use Klevu\Pipelines\Transformer\TransformerInterface;
use Magento\Catalog\Api\Data\ProductInterface;

class ToMinPriceProduct implements TransformerInterface
{
    /**
     * @var MinPriceProductProviderInterface
     */
    private MinPriceProductProviderInterface $minPriceProductProvider;

    /**
     * @param MinPriceProductProviderInterface $minPriceProductProvider
     */
    public function __construct(MinPriceProductProviderInterface $minPriceProductProvider)
    {
        $this->minPriceProductProvider = $minPriceProductProvider;
    }

    /**
     * @param mixed $data
     * @param ArgumentIterator|null $arguments
     * @param \ArrayAccess<int|string, mixed>|null $context
     *
     * @return ProductInterface|null
     */
    public function transform(
        mixed $data,
        ?ArgumentIterator $arguments = null,
        // phpcs:ignore SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter
        ?\ArrayAccess $context = null,
    ): ?ProductInterface {
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

        return $this->minPriceProductProvider->get(product: $data);
    }
}
