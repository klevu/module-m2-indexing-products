<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Pipeline\Provider\Argument\Transformer;

use Klevu\IndexingProducts\Pipeline\Transformer\ToFinalPrice;
use Klevu\Pipelines\Exception\Transformation\InvalidTransformationArgumentsException;
use Klevu\Pipelines\Model\ArgumentIterator;
use Klevu\Pipelines\Provider\ArgumentProviderInterface;

class ToFinalPriceArgumentProvider
{
    public const ARGUMENT_INDEX_QUANTITY = 0;

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
     * @return int
     */
    public function getQuantityArgumentValue(
        ?ArgumentIterator $arguments,
        mixed $extractionPayload = null,
        ?\ArrayAccess $extractionContext = null,
    ): int {
        $argumentValue = $this->argumentProvider->getArgumentValueWithExtractionExpansion(
            arguments: $arguments,
            argumentKey: self::ARGUMENT_INDEX_QUANTITY,
            defaultValue: null,
            extractionPayload: $extractionPayload,
            extractionContext: $extractionContext,
        );
        if (!$this->isValidArgumentValue($argumentValue)) {
            throw new InvalidTransformationArgumentsException(
                transformerName: ToFinalPrice::class,
                errors: [
                    sprintf(
                        'Quantity argument (%s) must be int; Received %s',
                        self::ARGUMENT_INDEX_QUANTITY,
                        get_debug_type($argumentValue),
                    ),
                ],
                arguments: $arguments,
                data: $extractionPayload,
            );
        }

        return (int)$argumentValue;
    }

    /**
     * @param mixed $argumentValue
     *
     * @return bool
     */
    private function isValidArgumentValue(mixed $argumentValue): bool
    {
        return is_int($argumentValue)
            || (is_numeric($argumentValue) && (string)(int)$argumentValue === $argumentValue);
    }
}
