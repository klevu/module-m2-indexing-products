<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Pipeline\Provider\Argument\Transformer;

use Klevu\IndexingProducts\Pipeline\Transformer\GetAttributeText;
use Klevu\Pipelines\Exception\Transformation\InvalidTransformationArgumentsException;
use Klevu\Pipelines\Model\ArgumentIterator;
use Klevu\Pipelines\Provider\ArgumentProviderInterface;

class GetAttributeTextArgumentProvider
{
    public const ARGUMENT_INDEX_ATTRIBUTE_CODE = 0;

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
     * @return string
     */
    public function getAttributeCodeArgumentValue(
        ?ArgumentIterator $arguments,
        mixed $extractionPayload = null,
        ?\ArrayAccess $extractionContext = null,
    ): string {
        $argumentValue = $this->argumentProvider->getArgumentValueWithExtractionExpansion(
            arguments: $arguments,
            argumentKey: self::ARGUMENT_INDEX_ATTRIBUTE_CODE,
            defaultValue: null,
            extractionPayload: $extractionPayload,
            extractionContext: $extractionContext,
        );
        if (!is_string($argumentValue)) {
            throw new InvalidTransformationArgumentsException(
                transformerName: GetAttributeText::class,
                errors: [
                    sprintf(
                        'Attribute Code argument (%s) must be string; Received %s',
                        self::ARGUMENT_INDEX_ATTRIBUTE_CODE,
                        is_scalar($argumentValue)
                            ? $argumentValue
                            : get_debug_type($argumentValue),
                    ),
                ],
                arguments: $arguments,
                data: $extractionPayload,
            );
        }

        return $argumentValue;
    }
}
