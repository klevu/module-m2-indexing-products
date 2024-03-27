<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Service\Provider\Image;

use Klevu\IndexingApi\Service\Provider\Image\IsDbStorageUsedProviderInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\MediaStorage\Model\File\Storage;

class IsDbStorageUsedImageProvider implements IsDbStorageUsedProviderInterface
{
    /**
     * @var ScopeConfigInterface
     */
    private readonly ScopeConfigInterface $scopeConfig;

    /**
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(ScopeConfigInterface $scopeConfig)
    {
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * @return bool
     */
    public function get(): bool
    {
        return Storage::STORAGE_MEDIA_DATABASE === $this->getMediaStorageSetting();
    }

    /**
     * @return int
     */
    private function getMediaStorageSetting(): int
    {
        return (int)$this->scopeConfig->getValue(
            Storage::XML_PATH_STORAGE_MEDIA,
            ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
        );
    }
}
