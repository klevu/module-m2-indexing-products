<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Service;

use Klevu\IndexingApi\Service\Action\ImageGenerationActionInterface;
use Klevu\IndexingApi\Service\ImageGeneratorServiceInterface;
use Magento\Catalog\Model\Product\Image\ParamsBuilder;
use Magento\Catalog\Model\Product\Image\ParamsBuilderFactory;
use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Framework\Exception\LocalizedException;

class ImageGeneratorService implements ImageGeneratorServiceInterface
{
    /**
     * @var ParamsBuilderFactory
     */
    private readonly ParamsBuilderFactory $paramsBuilderFactory;
    /**
     * @var ImageGenerationActionInterface
     */
    private readonly ImageGenerationActionInterface $imageResizeAction;
    /**
     * @var State
     */
    private State $state;
    /**
     * @var mixed[]
     */
    private readonly array $imageParams;

    /**
     * @param ParamsBuilderFactory $paramsBuilderFactory
     * @param ImageGenerationActionInterface $imageResizeAction
     * @param State $state
     * @param mixed[] $imageParams
     */
    public function __construct(
        ParamsBuilderFactory $paramsBuilderFactory,
        ImageGenerationActionInterface $imageResizeAction,
        State $state,
        array $imageParams = [],
    ) {
        $this->paramsBuilderFactory = $paramsBuilderFactory;
        $this->imageResizeAction = $imageResizeAction;
        $this->state = $state;
        $this->imageParams = $imageParams;
    }

    /**
     * @param string $imagePath
     * @param string $imageType
     * @param int|null $width
     * @param int|null $height
     * @param int|null $storeId
     *
     * @return string
     */
    public function execute(
        string $imagePath,
        string $imageType,
        ?int $width = null,
        ?int $height = null,
        ?int $storeId = null,
    ): string {
        $this->setAreaCode();
        $imageParams = $this->generateImageParams(
            imageType: $imageType,
            width: $width,
            height: $height,
            storeId: $storeId,
        );

        return $this->imageResizeAction->execute(
            imageParams: $imageParams,
            imagePath: $imagePath,
        );
    }

    /**
     * @param string $imageType
     * @param int|null $width
     * @param int|null $height
     * @param int|null $storeId
     *
     * @return mixed[]
     */
    private function generateImageParams(
        string $imageType,
        ?int $width,
        ?int $height,
        ?int $storeId,
    ): array {
        $imageData = array_merge(
            $this->imageParams,
            [
                'type' => $imageType,
                'width' => $width,
                'height' => $height,
            ],
        );
        /** @var ParamsBuilder $paramsBuilder */
        $paramsBuilder = $this->paramsBuilderFactory->create();

        return $paramsBuilder->build(
            imageArguments: $imageData,
            scopeId: $storeId,
        );
    }

    /**
     * @return void
     * @throws LocalizedException
     */
    private function setAreaCode(): void
    {
        // @TODO use emulation
        try {
            $this->state->getAreaCode();
        } catch (LocalizedException) {
            $this->state->setAreaCode(code: Area::AREA_FRONTEND);
        }
    }
}
