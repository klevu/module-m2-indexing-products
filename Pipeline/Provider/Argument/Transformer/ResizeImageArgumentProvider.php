<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Pipeline\Provider\Argument\Transformer;

use Klevu\IndexingProducts\Pipeline\Transformer\ResizeImage;
use Klevu\Pipelines\Exception\Transformation\InvalidTransformationArgumentsException;
use Klevu\Pipelines\Model\ArgumentIterator;
use Klevu\Pipelines\Provider\ArgumentProviderInterface;

class ResizeImageArgumentProvider
{
    public const ARGUMENT_INDEX_IMAGE_TYPE = 0;
    public const ARGUMENT_INDEX_IMAGE_WIDTH = 1;
    public const ARGUMENT_INDEX_IMAGE_HEIGHT = 2;
    public const ARGUMENT_INDEX_IMAGE_STORE_ID = 3;

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
     * @return string|null
     */
    public function getImageTypeArgumentValue(
        ?ArgumentIterator $arguments,
        mixed $extractionPayload = null,
        ?\ArrayAccess $extractionContext = null,
    ): ?string {
        $argumentValue = $this->argumentProvider->getArgumentValueWithExtractionExpansion(
            arguments: $arguments,
            argumentKey: self::ARGUMENT_INDEX_IMAGE_TYPE,
            defaultValue: null,
            extractionPayload: $extractionPayload,
            extractionContext: $extractionContext,
        );
        if (!is_string($argumentValue)) {
            throw new InvalidTransformationArgumentsException(
                transformerName: ResizeImage::class,
                errors: [
                    sprintf(
                        'Image type argument (%s) must be a string; Received %s',
                        self::ARGUMENT_INDEX_IMAGE_TYPE,
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

    /**
     * @param ArgumentIterator|null $arguments
     * @param mixed|null $extractionPayload
     * @param \ArrayAccess<int|string, mixed>|null $extractionContext
     *
     * @return int|null
     */
    public function getImageWidthArgumentValue(
        ?ArgumentIterator $arguments,
        mixed $extractionPayload = null,
        ?\ArrayAccess $extractionContext = null,
    ): ?int {
        $argumentValue = $this->argumentProvider->getArgumentValueWithExtractionExpansion(
            arguments: $arguments,
            argumentKey: self::ARGUMENT_INDEX_IMAGE_WIDTH,
            defaultValue: null,
            extractionPayload: $extractionPayload,
            extractionContext: $extractionContext,
        );
        if (null !== $argumentValue && !is_int($argumentValue)) {
            throw new InvalidTransformationArgumentsException(
                transformerName: ResizeImage::class,
                errors: [
                    sprintf(
                        'Image width argument (%s) must be integer or null; Received %s',
                        self::ARGUMENT_INDEX_IMAGE_TYPE,
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

    /**
     * @param ArgumentIterator|null $arguments
     * @param mixed|null $extractionPayload
     * @param \ArrayAccess<int|string, mixed>|null $extractionContext
     *
     * @return int|null
     */
    public function getImageHeightArgumentValue(
        ?ArgumentIterator $arguments,
        mixed $extractionPayload = null,
        ?\ArrayAccess $extractionContext = null,
    ): ?int {
        $argumentValue = $this->argumentProvider->getArgumentValueWithExtractionExpansion(
            arguments: $arguments,
            argumentKey: self::ARGUMENT_INDEX_IMAGE_HEIGHT,
            defaultValue: null,
            extractionPayload: $extractionPayload,
            extractionContext: $extractionContext,
        );
        if (null !== $argumentValue && !is_int($argumentValue)) {
            throw new InvalidTransformationArgumentsException(
                transformerName: ResizeImage::class,
                errors: [
                    sprintf(
                        'Image height argument (%s) must be integer or null; Received %s',
                        self::ARGUMENT_INDEX_IMAGE_TYPE,
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

    /**
     * @param ArgumentIterator|null $arguments
     * @param mixed|null $extractionPayload
     * @param \ArrayAccess<int|string, mixed>|null $extractionContext
     *
     * @return int|null
     */
    public function getStoreIdArgumentValue(
        ?ArgumentIterator $arguments,
        mixed $extractionPayload = null,
        ?\ArrayAccess $extractionContext = null,
    ): ?int {
        $argumentValue = $this->argumentProvider->getArgumentValueWithExtractionExpansion(
            arguments: $arguments,
            argumentKey: self::ARGUMENT_INDEX_IMAGE_STORE_ID,
            defaultValue: null,
            extractionPayload: $extractionPayload,
            extractionContext: $extractionContext,
        );
        if (null !== $argumentValue && !is_numeric($argumentValue)) {
            throw new InvalidTransformationArgumentsException(
                transformerName: ResizeImage::class,
                errors: [
                    sprintf(
                        'Store ID argument (%s) must be integer or null; Received %s',
                        self::ARGUMENT_INDEX_IMAGE_TYPE,
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
