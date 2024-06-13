<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Pipeline\Transformer;

use Klevu\IndexingApi\Service\Provider\Catalog\AttributeTextProviderInterface;
use Klevu\IndexingProducts\Exception\InvalidAttributeCodeException;
use Klevu\IndexingProducts\Pipeline\Provider\Argument\Transformer\GetAttributeTextArgumentProvider;
use Klevu\Pipelines\Exception\Transformation\InvalidInputDataException;
use Klevu\Pipelines\Exception\TransformationException;
use Klevu\Pipelines\Model\ArgumentIterator;
use Klevu\Pipelines\Transformer\TransformerInterface;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Framework\Exception\LocalizedException;

class GetAttributeText implements TransformerInterface
{
    /**
     * @var GetAttributeTextArgumentProvider
     */
    private readonly GetAttributeTextArgumentProvider $argumentProvider;
    /**
     * @var AttributeTextProviderInterface
     */
    private readonly AttributeTextProviderInterface $attributeTextProvider;

    /**
     * @param GetAttributeTextArgumentProvider $argumentProvider
     * @param AttributeTextProviderInterface $attributeTextProvider
     */
    public function __construct(
        GetAttributeTextArgumentProvider $argumentProvider,
        AttributeTextProviderInterface $attributeTextProvider,
    ) {
        $this->argumentProvider = $argumentProvider;
        $this->attributeTextProvider = $attributeTextProvider;
    }

    /**
     * @param mixed $data
     * @param ArgumentIterator|null $arguments
     * @param \ArrayAccess<int|string, mixed>|null $context
     *
     * @return string[]|string|null
     * @throws TransformationException
     */
    public function transform(
        mixed $data,
        ?ArgumentIterator $arguments = null,
        // phpcs:ignore SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter
        ?\ArrayAccess $context = null,
    ): array|string|null {
        if (!($data instanceof ProductInterface)) {
            throw new InvalidInputDataException(
                transformerName: $this::class,
                expectedType: ProductInterface::class,
                arguments: $arguments,
                data: $data,
            );
        }
        $attributeCodeArgumentValue = $this->argumentProvider->getAttributeCodeArgumentValue(
            arguments: $arguments,
            extractionPayload: $data,
            extractionContext: $context,
        );

        try {
            $return = $this->attributeTextProvider->get(
                product: $data,
                attributeCode: $attributeCodeArgumentValue,
            );
        } catch (InvalidAttributeCodeException | LocalizedException $exception) {
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
