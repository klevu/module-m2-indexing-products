<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Setup\Patch\Data;

use Klevu\Configuration\Setup\Traits\MigrateLegacyConfigurationSettingsTrait;
use Klevu\IndexingProducts\Constants;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Setup\Patch\DataPatchInterface;

class MigrateLegacyConfigurationSettings implements DataPatchInterface
{
    use MigrateLegacyConfigurationSettingsTrait;

    public const XML_PATH_LEGACY_PRODUCT_SYNC_ENABLED = 'klevu_search/product_sync/enabled';
    public const XML_PATH_LEGACY_PRODUCT_INCLUDE_OOS = 'klevu_search/product_sync/include_oos';
    public const XML_PATH_LEGACY_PRODUCT_IMAGE_WIDTH = 'klevu_search/image_setting/image_width';
    public const XML_PATH_LEGACY_PRODUCT_IMAGE_HEIGHT = 'klevu_search/image_setting/image_height';

    /**
     * @param WriterInterface $configWriter
     * @param ResourceConnection $resourceConnection
     */
    public function __construct(
        WriterInterface $configWriter,
        ResourceConnection $resourceConnection,
    ) {
        $this->configWriter = $configWriter;
        $this->resourceConnection = $resourceConnection;
    }

    /**
     * @return $this
     */
    public function apply(): self
    {
        $this->migrateProductSyncEnabled();
        $this->migrateProductSyncOutOfStock();
        $this->migrateProductImageDimensions();

        return $this;
    }

    /**
     * @return string[]
     */
    public function getAliases(): array
    {
        return [];
    }

    /**
     * @return string[]
     */
    public static function getDependencies(): array
    {
        return [];
    }

    /**
     * @return void
     */
    private function migrateProductSyncEnabled(): void
    {
        $this->renameConfigValue(
            fromPath: static::XML_PATH_LEGACY_PRODUCT_SYNC_ENABLED,
            toPath: Constants::XML_PATH_PRODUCT_SYNC_ENABLED,
        );
    }

    /**
     * @return void
     */
    private function migrateProductSyncOutOfStock(): void
    {
        $this->renameConfigValue(
            fromPath: static::XML_PATH_LEGACY_PRODUCT_INCLUDE_OOS,
            toPath: Constants::XML_PATH_PRODUCT_EXCLUDE_OOS,
            mapValues: [
                '0' => '1',
                '1' => '0',
            ],
        );
    }

    /**
     * @return void
     */
    private function migrateProductImageDimensions(): void
    {
        $this->renameConfigValue(
            fromPath: static::XML_PATH_LEGACY_PRODUCT_IMAGE_WIDTH,
            toPath: Constants::XML_PATH_PRODUCT_IMAGE_WIDTH,
        );
        $this->renameConfigValue(
            fromPath: static::XML_PATH_LEGACY_PRODUCT_IMAGE_HEIGHT,
            toPath: Constants::XML_PATH_PRODUCT_IMAGE_HEIGHT,
        );
    }
}
