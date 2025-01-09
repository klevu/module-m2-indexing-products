<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Pipeline\Transformer;

use Klevu\IndexingApi\Service\Provider\Tax\ProductTaxProviderInterface;
use Klevu\IndexingProducts\Pipeline\Provider\Argument\Transformer\ToPriceIncludingTaxArgumentProvider;
use Klevu\Pipelines\Exception\Transformation\InvalidInputDataException;
use Klevu\Pipelines\Exception\TransformationException;
use Klevu\Pipelines\Model\ArgumentIterator;
use Klevu\Pipelines\Transformer\TransformerInterface;

class ToPriceIncludingTax implements TransformerInterface
{
    /**
     * @var ToPriceIncludingTaxArgumentProvider
     */
    private readonly ToPriceIncludingTaxArgumentProvider $argumentProvider;
    /**
     * @var ProductTaxProviderInterface
     */
    private readonly ProductTaxProviderInterface $productPriceProvider;

    /**
     * @param ToPriceIncludingTaxArgumentProvider $argumentProvider
     * @param ProductTaxProviderInterface $productPriceProvider
     */
    public function __construct(
        ToPriceIncludingTaxArgumentProvider $argumentProvider,
        ProductTaxProviderInterface $productPriceProvider,
    ) {
        $this->argumentProvider = $argumentProvider;
        $this->productPriceProvider = $productPriceProvider;
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
        if (!is_numeric($data)) {
            throw new InvalidInputDataException(
                transformerName: $this::class,
                expectedType: 'numeric',
                arguments: $arguments,
                data: $data,
            );
        }
        $product = $this->argumentProvider->getProductArgumentValue(
            arguments: $arguments,
            extractionPayload: $data,
            extractionContext: $context,
        );
        $customerGroup = $this->argumentProvider->getCustomerGroupArgumentValue(
            arguments: $arguments,
            extractionPayload: $data,
            extractionContext: $context,
        );

        return $this->productPriceProvider->get(
            product: $product,
            price: (float)$data,
            customerGroupId: $customerGroup,
        );
    }
}
