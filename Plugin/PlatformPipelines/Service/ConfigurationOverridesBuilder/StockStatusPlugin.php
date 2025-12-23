<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Plugin\PlatformPipelines\Service\ConfigurationOverridesBuilder;

use Klevu\Pipelines\Pipeline\ConfigurationElements;
use Klevu\PlatformPipelines\Api\ConfigurationOverridesBuilderInterface;
use Klevu\PlatformPipelines\Service\ConfigurationOverridesBuilder;
use Magento\Framework\App\Config\ScopeConfigInterface;

class StockStatusPlugin
{
    public const CONFIG_PATH_USE_PROVIDER_FOR_PIPELINES_STOCK_STATUS = 'klevu/indexing/use_provider_for_pipelines_stock_status';
    /**
     * @var ScopeConfigInterface
     */
    private readonly ScopeConfigInterface $scopeConfig;
    /**
     * @var array<string, string>
     */
    private readonly array $entitySubtypeToParentPathMap;

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param array<string, string> $entitySubtypeToParentPathMap
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        array $entitySubtypeToParentPathMap = [],
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->entitySubtypeToParentPathMap = $entitySubtypeToParentPathMap;
    }

    /**
     * @param ConfigurationOverridesBuilder $subject
     *
     * @return null
     */
    public function beforeBuild(
        ConfigurationOverridesBuilder $subject,
    ): mixed {
        $useProviderForStockStatus = $this->scopeConfig->isSetFlag(
            static::CONFIG_PATH_USE_PROVIDER_FOR_PIPELINES_STOCK_STATUS,
            ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
        );
        if (!$useProviderForStockStatus) {
            return null;
        }

        foreach ($this->entitySubtypeToParentPathMap as $entitySubtype => $parentPath) {
            $basePath = $parentPath
                . ConfigurationOverridesBuilderInterface::PATH_PARTS_SEPARATOR
                . 'inStock'
                . ConfigurationOverridesBuilderInterface::PATH_PARTS_SEPARATOR;

            if ('configurable_variants' !== $entitySubtype) {
                $subject->addConfigurationByPath(
                    path: $this->injectStagesElements(
                        stagesPath: $basePath . 'getStock'
                    ),
                    configuration: [
                        ConfigurationElements::ARGS->value => [
                            'extraction' => 'currentProduct::',
                            'transformations' => 'GetProductStockStatus($currentProduct::getStore(), $currentParentProduct::)',
                        ],
                    ],
                );

                continue;
            }

            $subject->addConfigurationByPath(
                path: $this->injectStagesElements(
                    stagesPath: $basePath
                        . 'checkStock'
                        . ConfigurationOverridesBuilderInterface::PATH_PARTS_SEPARATOR
                        . 'getParentStock',
                ),
                configuration: [
                    ConfigurationElements::ARGS->value => [
                        'extraction' => 'currentProduct::',
                        'transformations' => 'GetProductStockStatus($currentProduct::getStore())',
                    ],
                ],
            );

            $subject->addConfigurationByPath(
                path: $this->injectStagesElements(
                    stagesPath: $basePath
                        . 'checkStock'
                        . ConfigurationOverridesBuilderInterface::PATH_PARTS_SEPARATOR
                        . 'getVariantStock',
                ),
                configuration: [
                    ConfigurationElements::ARGS->value => [
                        'extraction' => 'currentParentProduct::',
                        'transformations' => 'GetProductStockStatus($currentParentProduct::getStore())',
                    ],
                ],
            );
        }

        return null;
    }

    /**
     * @param string $stagesPath
     *
     * @return string
     */
    private function injectStagesElements(string $stagesPath): string
    {
        $pathParts = explode(
            separator: ConfigurationOverridesBuilderInterface::PATH_PARTS_SEPARATOR,
            string: $stagesPath,
        );

        $return = [];
        $skipNext = false;
        foreach ($pathParts as $pathPart) {
            if (ConfigurationElements::STAGES->value === $pathPart) {
                $skipNext = true;
                continue;
            }
            if ($skipNext) {
                $skipNext = false;
                continue;
            }

            $skipNext = false;

            $return[] = ConfigurationElements::STAGES->value;
            $return[] = $pathPart;
        }

        return implode(
            separator: ConfigurationOverridesBuilderInterface::PATH_PARTS_SEPARATOR,
            array: $return,
        );
    }
}
