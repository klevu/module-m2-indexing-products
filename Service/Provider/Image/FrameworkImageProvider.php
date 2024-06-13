<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Service\Provider\Image;

use Klevu\IndexingApi\Service\Provider\Image\FrameworkImageProviderInterface;
use Magento\Catalog\Model\Product\Media\ConfigInterface as MediaConfig;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Directory\WriteInterface;
use Magento\Framework\Image;
use Magento\Framework\Image\Factory as ImageFactory;

class FrameworkImageProvider implements FrameworkImageProviderInterface
{
    /**
     * @var MediaConfig
     */
    private readonly MediaConfig $imageConfig;
    /**
     * @var ImageFactory
     */
    private ImageFactory $imageFactory;
    /**
     * @var WriteInterface
     */
    private readonly WriteInterface $mediaDirectory;
    /**
     * @var string|null
     */
    private readonly ?string $adapterName;

    /**
     * @param MediaConfig $imageConfig
     * @param Filesystem $filesystem
     * @param ImageFactory $imageFactory
     * @param string|null $adapterName
     *
     * @throws FileSystemException
     */
    public function __construct(
        MediaConfig $imageConfig,
        Filesystem $filesystem,
        ImageFactory $imageFactory,
        ?string $adapterName = null,
    ) {
        $this->imageConfig = $imageConfig;
        $this->imageFactory = $imageFactory;
        $this->mediaDirectory = $filesystem->getDirectoryWrite(directoryCode: DirectoryList::MEDIA);
        $this->adapterName = $adapterName;
    }

    /**
     * @param string $imagePath
     * @param mixed[] $imageParams
     *
     * @return Image
     * @throws FileSystemException
     */
    public function get(string $imagePath, array $imageParams): Image
    {
        $image = $this->imageFactory->create(
            fileName: $this->getMediaDirectoryPath($imagePath),
            adapterName: $this->adapterName,
        );
        $image->keepAspectRatio(value: $imageParams['keep_aspect_ratio'] ?? true);
        $image->keepFrame(value: $imageParams['keep_frame'] ?? true);
        $image->keepTransparency(value: $imageParams['keep_transparency'] ?? true);
        $image->constrainOnly(value: $imageParams['constrain_only'] ?? false);
        $image->backgroundColor(value: $imageParams['background'] ?? null);
        $image->quality(value: $imageParams['quality'] ?? 100);

        return $image;
    }

    /**
     * @param string $imagePath
     *
     * @return string
     */
    private function getMediaDirectoryPath(string $imagePath): string
    {
        return $this->mediaDirectory->getAbsolutePath(
            path: $this->imageConfig->getMediaPath(file: $imagePath),
        );
    }
}
