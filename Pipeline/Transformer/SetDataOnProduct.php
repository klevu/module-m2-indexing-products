<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Pipeline\Transformer;

use Klevu\IndexingProducts\Pipeline\Provider\Argument\Transformer\SetDataOnProductArgumentProvider;
use Klevu\Pipelines\Exception\Transformation\InvalidInputDataException;
use Klevu\Pipelines\Model\ArgumentIterator;
use Klevu\Pipelines\Transformer\TransformerInterface;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Framework\DataObject;

class SetDataOnProduct implements TransformerInterface
{
    /**
     * @var SetDataOnProductArgumentProvider
     */
    private readonly SetDataOnProductArgumentProvider $argumentProvider;

    /**
     * @param SetDataOnProductArgumentProvider $argumentProvider
     */
    public function __construct(
        SetDataOnProductArgumentProvider $argumentProvider,
    ) {
        $this->argumentProvider = $argumentProvider;
    }

    /**
     * @param mixed $data
     * @param ArgumentIterator|null $arguments
     * @param \ArrayAccess<int|string, mixed>|null $context
     *
     * @return ProductInterface
     */
    public function transform(
        mixed $data,
        ?ArgumentIterator $arguments = null,
        // phpcs:ignore SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter
        ?\ArrayAccess $context = null,
    ): ProductInterface {
        if (!($data instanceof ProductInterface) || !($data instanceof DataObject)) {
            throw new InvalidInputDataException(
                transformerName: $this::class,
                expectedType: ProductInterface::class . '&' . DataObject::class,
                arguments: $arguments,
                data: $data,
            );
        }
        $attributeCode = $this->argumentProvider->getAttributeCodeArgumentValue(
            arguments: $arguments,
            extractionPayload: $data,
            extractionContext: $context,
        );
        $value = $this->argumentProvider->getAttributeValueArgumentValue(
            arguments: $arguments,
            extractionPayload: $data,
            extractionContext: $context,
        );
        $data->setData(key: $attributeCode, value: $value);

        return $data;
    }

}
