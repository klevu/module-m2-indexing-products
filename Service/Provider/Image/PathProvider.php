<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Service\Provider\Image;

use Klevu\IndexingApi\Service\Provider\Image\PathProviderInterface;
use Magento\Catalog\Model\View\Asset\Image;
use Magento\Catalog\Model\View\Asset\ImageFactory as AssetImageFactory;

class PathProvider implements PathProviderInterface
{
    /**
     * @var AssetImageFactory
     */
    private readonly AssetImageFactory $assetImageFactory;

    /**
     * @param AssetImageFactory $assetImageFactory
     */
    public function __construct(AssetImageFactory $assetImageFactory)
    {
        $this->assetImageFactory = $assetImageFactory;
    }

    /**
     * @param mixed[] $imageParams
     * @param string $filePath
     *
     * @return string
     */
    public function get(array $imageParams, string $filePath): string
    {
        /** @var Image $imageAsset */
        $imageAsset = $this->assetImageFactory->create([
            'miscParams' => $imageParams,
            'filePath' => $filePath,
        ]);

        return $imageAsset->getPath();
    }
}
