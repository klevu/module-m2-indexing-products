<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Pipeline\Provider\Argument\Transformer;

use Klevu\IndexingProducts\Pipeline\Transformer\GetProductStockStatus;
use Klevu\Pipelines\Exception\Transformation\InvalidTransformationArgumentsException;
use Klevu\Pipelines\Model\ArgumentIterator;
use Klevu\Pipelines\Provider\ArgumentProviderInterface;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Store\Api\Data\StoreInterface;

class GetProductStockStatusArgumentProvider
{
    public const ARGUMENT_INDEX_STORE = 0;
    public const ARGUMENT_INDEX_PARENT_PRODUCT = 1;

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
     * @param \ArrayAccess|null $extractionContext
     *
     * @return StoreInterface|null
     */
    public function getStoreArgumentValue(
        ?ArgumentIterator $arguments,
        mixed $extractionPayload = null,
        ?\ArrayAccess $extractionContext = null,
    ): ?StoreInterface {
        $argumentValue = $this->argumentProvider->getArgumentValueWithExtractionExpansion(
            arguments: $arguments,
            argumentKey: self::ARGUMENT_INDEX_STORE,
            defaultValue: null,
            extractionPayload: $extractionPayload,
            extractionContext: $extractionContext,
        );

        if ($argumentValue !== null && !($argumentValue instanceof StoreInterface)) {
            throw new InvalidTransformationArgumentsException(
                transformerName: GetProductStockStatus::class,
                errors: [
                    sprintf(
                        'Store argument (%s) must be instance of StoreInterface or null; Received %s',
                        self::ARGUMENT_INDEX_STORE,
                        is_scalar($argumentValue)
                            ? $argumentValue
                            : gettype($argumentValue),
                    ),
                ],
            );
        }

        return $argumentValue;
    }

    /**
     * @param ArgumentIterator|null $arguments
     * @param mixed|null $extractionPayload
     * @param \ArrayAccess|null $extractionContext
     *
     * @return ProductInterface|null
     */
    public function getParentProductArgumentValue(
        ?ArgumentIterator $arguments,
        mixed $extractionPayload = null,
        ?\ArrayAccess $extractionContext = null,
    ): ?ProductInterface {
        $argumentValue = $this->argumentProvider->getArgumentValueWithExtractionExpansion(
            arguments: $arguments,
            argumentKey: self::ARGUMENT_INDEX_PARENT_PRODUCT,
            defaultValue: null,
            extractionPayload: $extractionPayload,
            extractionContext: $extractionContext,
        );

        if ($argumentValue !== null && !($argumentValue instanceof ProductInterface)) {
            throw new InvalidTransformationArgumentsException(
                transformerName: GetProductStockStatus::class,
                errors: [
                    sprintf(
                        'Parent Product argument (%s) must be instance of ProductInterface or null; Received %s',
                        self::ARGUMENT_INDEX_PARENT_PRODUCT,
                        is_scalar($argumentValue)
                            ? $argumentValue
                            : gettype($argumentValue),
                    ),
                ],
            );
        }

        return $argumentValue;
    }
}