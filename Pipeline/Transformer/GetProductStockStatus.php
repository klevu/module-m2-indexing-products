<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Pipeline\Transformer;

use Klevu\IndexingProducts\Pipeline\Provider\Argument\Transformer\GetProductStockStatusArgumentProvider;
use Klevu\IndexingProducts\Service\Provider\ProductStockStatusProviderInterface;
use Klevu\Pipelines\Exception\Transformation\InvalidInputDataException;
use Klevu\Pipelines\Exception\TransformationException;
use Klevu\Pipelines\Model\ArgumentIterator;
use Klevu\Pipelines\Transformer\TransformerInterface;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Framework\Exception\LocalizedException;

class GetProductStockStatus implements TransformerInterface
{
    /**
     * @var GetProductStockStatusArgumentProvider
     */
    private readonly GetProductStockStatusArgumentProvider $argumentProvider;
    /**
     * @var ProductStockStatusProviderInterface
     */
    private readonly ProductStockStatusProviderInterface $productStockStatusProvider;

    /**
     * @param GetProductStockStatusArgumentProvider $argumentProvider
     * @param ProductStockStatusProviderInterface $productStockStatusProvider
     */
    public function __construct(
        GetProductStockStatusArgumentProvider $argumentProvider,
        ProductStockStatusProviderInterface $productStockStatusProvider,
    ) {
        $this->argumentProvider = $argumentProvider;
        $this->productStockStatusProvider = $productStockStatusProvider;
    }

    /**
     * @param mixed $data
     * @param ArgumentIterator|null $arguments
     * @param \ArrayAccess|null $context
     *
     * @return bool
     */
    public function transform(
        mixed $data,
        ?ArgumentIterator $arguments = null,
        // phpcs:ignore SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter
        ?\ArrayAccess $context = null,
    ): bool {
        if (!($data instanceof ProductInterface)) {
            throw new InvalidInputDataException(
                transformerName: $this::class,
                expectedType: ProductInterface::class,
                arguments: $arguments,
                data: $data,
            );
        }

        $storeArgumentValue = $this->argumentProvider->getStoreArgumentValue(
            arguments: $arguments,
            extractionPayload: $data,
            extractionContext: $context,
        );
        $parentProductArgumentValue = $this->argumentProvider->getParentProductArgumentValue(
            arguments: $arguments,
            extractionPayload: $data,
            extractionContext: $context,
        );

        try {
            $return = $this->productStockStatusProvider->get(
                product: $data,
                store: $storeArgumentValue,
                parentProduct: $parentProductArgumentValue,
            );
        } catch (LocalizedException $exception) {
            throw new TransformationException(
                transformerName: $this::class,
                errors: [
                    $exception->getMessage(),
                ],
                arguments: $arguments,
                data: $data,
                message: $exception->getMessage(),
                previous: $exception,
            );
        }

        return $return;
    }
}