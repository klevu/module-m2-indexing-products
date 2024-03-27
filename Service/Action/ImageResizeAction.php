<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Service\Action;

use Klevu\IndexingApi\Service\Action\Image\ResizeActionInterface;
use Klevu\IndexingApi\Service\Action\Image\SetWatermarkActionInterface;
use Klevu\IndexingApi\Service\Action\ImageResizeActionInterface;
use Klevu\IndexingApi\Service\Provider\Image\FrameworkImageProviderInterface;
use Klevu\IndexingApi\Service\Provider\Image\IsDbStorageUsedProviderInterface;
use Klevu\IndexingApi\Service\Provider\Image\PathProviderInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Directory\WriteInterface;
use Magento\MediaStorage\Helper\File\Storage\Database as FileStorageDatabase;
use Psr\Log\LoggerInterface;

class ImageResizeAction implements ImageResizeActionInterface
{
    /**
     * @var PathProviderInterface
     */
    private readonly PathProviderInterface $imagePathProvider;
    /**
     * @var SetWatermarkActionInterface
     */
    private readonly SetWatermarkActionInterface $setWatermarkAction;
    /**
     * @var ResizeActionInterface
     */
    private readonly ResizeActionInterface $resizeAction;
    /**
     * @var FrameworkImageProviderInterface
     */
    private readonly FrameworkImageProviderInterface $frameworkImageProvider;
    /**
     * @var bool
     */
    private readonly bool $isDbStorageUsed;
    /**
     * @var WriteInterface
     */
    private readonly WriteInterface $mediaDirectory;
    /**
     * @var FileStorageDatabase
     */
    private readonly FileStorageDatabase $fileStorageDatabase;
    /**
     * @var LoggerInterface
     */
    private readonly LoggerInterface $logger;

    /**
     * @param PathProviderInterface $imagePathProvider
     * @param SetWatermarkActionInterface $setWatermarkAction
     * @param ResizeActionInterface $resizeAction
     * @param FrameworkImageProviderInterface $frameworkImageProvider
     * @param IsDbStorageUsedProviderInterface $isDbStorageUsedProvider
     * @param Filesystem $filesystem
     * @param FileStorageDatabase $fileStorageDatabase
     * @param LoggerInterface $logger
     *
     * @throws FileSystemException
     */
    public function __construct(
        PathProviderInterface $imagePathProvider,
        SetWatermarkActionInterface $setWatermarkAction,
        ResizeActionInterface $resizeAction,
        FrameworkImageProviderInterface $frameworkImageProvider,
        IsDbStorageUsedProviderInterface $isDbStorageUsedProvider,
        Filesystem $filesystem,
        FileStorageDatabase $fileStorageDatabase,
        LoggerInterface $logger,
    ) {
        $this->imagePathProvider = $imagePathProvider;
        $this->setWatermarkAction = $setWatermarkAction;
        $this->resizeAction = $resizeAction;
        $this->frameworkImageProvider = $frameworkImageProvider;
        $this->isDbStorageUsed = $isDbStorageUsedProvider->get();
        $this->mediaDirectory = $filesystem->getDirectoryWrite(DirectoryList::MEDIA);
        $this->fileStorageDatabase = $fileStorageDatabase;
        $this->logger = $logger;
    }

    /**
     * @param mixed[] $imageParams
     * @param string $imagePath
     *
     * @return string
     */
    public function execute(array $imageParams, string $imagePath): string
    {
        $absolutePath = $this->imagePathProvider->get(
            imageParams: $imageParams,
            filePath: $imagePath,
        );
        $relativePath = $this->mediaDirectory->getRelativePath(
            path: $absolutePath,
        );
        if ($this->isResizeRequired(relativePath: $relativePath, absolutePath: $absolutePath)) {
            try {
                $this->generateImage(
                    imagePath: $imagePath,
                    imageParams: $imageParams,
                    absolutePath: $absolutePath,
                    relativePath: $relativePath,
                );
            } catch (FileSystemException $exception) {
                $this->logger->error(
                    message: 'Method: {method}, Error: {message}',
                    context: [
                        'method' => __METHOD__,
                        'message' => $exception->getMessage(),
                    ],
                );
                return '';
            }
        }

        return $relativePath;
    }

    /**
     * @param string $relativePath
     * @param mixed $absolutePath
     *
     * @return bool
     */
    private function isResizeRequired(
        string $relativePath,
        mixed $absolutePath,
    ): bool {
        if ($this->isDbStorageUsed) {
            $return = $this->fileStorageDatabase->fileExists(filename: $relativePath);
        } else {
            $return = $this->mediaDirectory->isFile(path: $absolutePath)
                && $this->mediaDirectory->isExist(path: $absolutePath);
        }

        return !$return;
    }

    /**
     * @param string $imagePath
     * @param mixed[] $imageParams
     * @param string $absolutePath
     * @param string $relativePath
     *
     * @return void
     * @throws FileSystemException
     */
    private function generateImage(
        string $imagePath,
        array $imageParams,
        string $absolutePath,
        string $relativePath,
    ): void {
        $image = $this->frameworkImageProvider->get(
            imagePath: $imagePath,
            imageParams: $imageParams,
        );
        $this->resizeAction->execute(image: $image, imageParams: $imageParams);
        $this->setWatermarkAction->execute(image: $image, imageParams: $imageParams);
        $image->save(destination: $absolutePath);
        if ($this->isDbStorageUsed) {
            $this->fileStorageDatabase->saveFile(filename: $relativePath);
        }
    }
}
