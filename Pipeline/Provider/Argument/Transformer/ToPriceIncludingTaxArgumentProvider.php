<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Pipeline\Provider\Argument\Transformer;

use Klevu\IndexingProducts\Pipeline\Transformer\ToPriceIncludingTax;
use Klevu\Pipelines\Exception\Transformation\InvalidTransformationArgumentsException;
use Klevu\Pipelines\Model\ArgumentIterator;
use Klevu\Pipelines\Provider\ArgumentProviderInterface;
use Magento\Catalog\Api\Data\ProductInterface;

class ToPriceIncludingTaxArgumentProvider
{
    public const ARGUMENT_INDEX_PRODUCT = 0;
    public const ARGUMENT_INDEX_CUSTOMER_GROUP = 1;

    /**
     * @var ArgumentProviderInterface
     */
    private ArgumentProviderInterface $argumentProvider;

    /**
     * @param ArgumentProviderInterface $argumentProvider
     */
    public function __construct(ArgumentProviderInterface $argumentProvider)
    {
        $this->argumentProvider = $argumentProvider;
    }

    /**
     * @param ArgumentIterator|null $arguments
     * @param mixed|null $extractionPayload
     * @param \ArrayAccess<int|string, mixed>|null $extractionContext
     *
     * @return ProductInterface
     */
    public function getProductArgumentValue(
        ?ArgumentIterator $arguments,
        mixed $extractionPayload = null,
        ?\ArrayAccess $extractionContext = null,
    ): ProductInterface {
        $argumentValue = $this->argumentProvider->getArgumentValueWithExtractionExpansion(
            arguments: $arguments,
            argumentKey: self::ARGUMENT_INDEX_PRODUCT,
            defaultValue: null,
            extractionPayload: $extractionPayload,
            extractionContext: $extractionContext,
        );
        if (!($argumentValue instanceof ProductInterface)) {
            throw new InvalidTransformationArgumentsException(
                transformerName: ToPriceIncludingTax::class,
                errors: [
                    sprintf(
                        'ToPriceIncludingTax argument (%s) must be instance of %s; Received %s',
                        self::ARGUMENT_INDEX_PRODUCT,
                        ProductInterface::class,
                        get_debug_type($argumentValue),
                    ),
                ],
                arguments: $arguments,
                data: $extractionPayload,
            );
        }

        return $argumentValue;
    }

    /**
     * @param ArgumentIterator|null $arguments
     * @param mixed|null $extractionPayload
     * @param \ArrayAccess<int|string, mixed>|null $extractionContext
     *
     * @return int|null
     */
    public function getCustomerGroupArgumentValue(
        ?ArgumentIterator $arguments,
        mixed $extractionPayload = null,
        ?\ArrayAccess $extractionContext = null,
    ): ?int {
        $argumentValue = $this->argumentProvider->getArgumentValueWithExtractionExpansion(
            arguments: $arguments,
            argumentKey: self::ARGUMENT_INDEX_CUSTOMER_GROUP,
            defaultValue: null,
            extractionPayload: $extractionPayload,
            extractionContext: $extractionContext,
        );
        if (!(null === $argumentValue || is_numeric($argumentValue))) {
            throw new InvalidTransformationArgumentsException(
                transformerName: ToPriceIncludingTax::class,
                errors: [
                    sprintf(
                        'ToPriceIncludingTax argument (%s) must be an integer or null; Received %s',
                        self::ARGUMENT_INDEX_CUSTOMER_GROUP,
                        get_debug_type($argumentValue),
                    ),
                ],
                arguments: $arguments,
                data: $extractionPayload,
            );
        }

        return $argumentValue ? (int)$argumentValue : null;
    }
}
