<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Pipeline\Transformer;

use Klevu\IndexingApi\Service\ImageGeneratorServiceInterface;
use Klevu\IndexingProducts\Pipeline\Provider\Argument\Transformer\ResizeImageArgumentProvider;
use Klevu\Pipelines\Exception\Transformation\InvalidInputDataException;
use Klevu\Pipelines\Model\ArgumentIterator;
use Klevu\Pipelines\Transformer\TransformerInterface;

class ResizeImage implements TransformerInterface
{
    /**
     * @var ImageGeneratorServiceInterface
     */
    private readonly ImageGeneratorServiceInterface $imageGeneratorService;
    /**
     * @var ResizeImageArgumentProvider
     */
    private readonly ResizeImageArgumentProvider $argumentProvider;

    /**
     * @param ImageGeneratorServiceInterface $imageGeneratorService
     * @param ResizeImageArgumentProvider $argumentProvider
     */
    public function __construct(
        ImageGeneratorServiceInterface $imageGeneratorService,
        ResizeImageArgumentProvider $argumentProvider,
    ) {
        $this->imageGeneratorService = $imageGeneratorService;
        $this->argumentProvider = $argumentProvider;
    }

    /**
     * @param mixed $data
     * @param ArgumentIterator|null $arguments
     * @param \ArrayAccess<int|string, mixed>|null $context
     *
     * @return string|null
     */
    public function transform(
        mixed $data,
        ?ArgumentIterator $arguments = null,
        ?\ArrayAccess $context = null,
    ): ?string {
        if (null === $data || 'no_selection' === $data) {
            return null;
        }
        if (!is_string($data)) {
            throw new InvalidInputDataException(
                transformerName: $this::class,
                expectedType: 'string',
                arguments: $arguments,
                data: $data,
            );
        }
        $imageTypeArgumentValue = $this->argumentProvider->getImageTypeArgumentValue(
            arguments: $arguments,
            extractionPayload: $data,
            extractionContext: $context,
        );
        $imageWidthArgumentValue = $this->argumentProvider->getImageWidthArgumentValue(
            arguments: $arguments,
            extractionPayload: $data,
            extractionContext: $context,
        );
        $imageHeightArgumentValue = $this->argumentProvider->getImageHeightArgumentValue(
            arguments: $arguments,
            extractionPayload: $data,
            extractionContext: $context,
        );
        $storeIdArgumentValue = $this->argumentProvider->getStoreIdArgumentValue(
            arguments: $arguments,
            extractionPayload: $data,
            extractionContext: $context,
        );

        return $this->imageGeneratorService->execute(
            imagePath: $data,
            imageType: $imageTypeArgumentValue,
            width: $imageWidthArgumentValue,
            height: $imageHeightArgumentValue,
            storeId: $storeIdArgumentValue,
        );
    }
}
