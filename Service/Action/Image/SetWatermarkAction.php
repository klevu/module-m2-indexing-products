<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Service\Action\Image;

use Klevu\IndexingApi\Service\Action\Image\SetWatermarkActionInterface;
use Magento\Catalog\Model\Product\Media\ConfigInterface as MediaConfig;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Directory\WriteInterface;
use Magento\Framework\Image;

class SetWatermarkAction implements SetWatermarkActionInterface
{
    /**
     * @var MediaConfig
     */
    private readonly MediaConfig $imageConfig;
    /**
     * @var WriteInterface
     */
    private readonly WriteInterface $mediaDirectory;

    /**
     * @param MediaConfig $imageConfig
     * @param Filesystem $filesystem
     *
     * @throws FileSystemException
     */
    public function __construct(
        MediaConfig $imageConfig,
        Filesystem $filesystem,
    ) {
        $this->imageConfig = $imageConfig;
        $this->mediaDirectory = $filesystem->getDirectoryWrite(DirectoryList::MEDIA);
    }

    /**
     * @param Image $image
     * @param mixed[] $imageParams
     *
     * @return void
     * @throws \Exception
     */
    public function execute(Image $image, array $imageParams): void
    {
        if (!($imageParams['watermark_file'] ?? null)) {
            return;
        }
        if (($imageParams['watermark_height'] ?? null) !== null) {
            $image->setWatermarkHeight(height: $imageParams['watermark_height']);
        }
        if (($imageParams['watermark_width'] ?? null) !== null) {
            $image->setWatermarkWidth(width: $imageParams['watermark_width']);
        }
        if (($imageParams['watermark_position'] ?? null) !== null) {
            $image->setWatermarkPosition(position: $imageParams['watermark_position']);
        }
        if (($imageParams['watermark_image_opacity'] ?? null) !== null) {
            $image->setWatermarkImageOpacity(imageOpacity: $imageParams['watermark_image_opacity']);
        }
        $image->watermark(
            watermarkImage: $this->getWatermarkFilePath($imageParams['watermark_file']),
        );
    }

    /**
     * @param string $file
     *
     * @return string
     */
    private function getWatermarkFilePath(string $file): string
    {
        $path = $this->imageConfig->getMediaPath(file: '/watermark/' . $file);

        return $this->mediaDirectory->getAbsolutePath(path: $path);
    }
}
