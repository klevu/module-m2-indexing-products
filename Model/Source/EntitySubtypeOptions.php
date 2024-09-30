<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Model\Source;

use Magento\Bundle\Model\Product\Type as BundleType;
use Magento\Catalog\Model\Product\Type as ProductType;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable as ConfigurableType;
use Magento\Downloadable\Model\Product\Type as DownloadableType;
use Magento\Framework\Phrase;
use Magento\GroupedProduct\Model\Product\Type\Grouped as GroupedType;

class EntitySubtypeOptions implements EntitySubtypeOptionsInterface
{
    public const SIMPLE = ProductType::TYPE_SIMPLE;
    public const VIRTUAL = ProductType::TYPE_VIRTUAL;
    public const DOWNLOADABLE = DownloadableType::TYPE_DOWNLOADABLE;
    public const GROUPED = GroupedType::TYPE_CODE;
    public const BUNDLE = BundleType::TYPE_CODE;
    public const CONFIGURABLE = ConfigurableType::TYPE_CODE;
    public const CONFIGURABLE_VARIANTS = 'configurable_variants';

    /**
     * @var array<string, string>
     */
    private array $productTypes = [
        self::SIMPLE => 'Simple',
        self::VIRTUAL => 'Virtual',
        self::DOWNLOADABLE => 'Downloadable',
        self::GROUPED => 'Grouped',
        self::BUNDLE => 'Bundle',
        self::CONFIGURABLE => 'Configurable (Parent)',
        self::CONFIGURABLE_VARIANTS => 'Configurable (Variant)',
    ];

    /**
     * @param array<string, string> $customProductTypes
     */
    public function __construct(array $customProductTypes = [])
    {
        array_walk($customProductTypes, [$this, 'addCustomProductType']);
    }

    /**
     * @return array<int, array<string, string|Phrase>>
     */
    public function toOptionArray(): array
    {
        $options = [
            [
                'value' => '',
                'label' => __('-- Remove --'),
            ],
        ];
        foreach ($this->productTypes as $value => $label) {
            $options[] = [
                'value' => $value,
                'label' => __($label),
            ];
        }

        return $options;
    }

    /**
     * @return string[]
     */
    public function getValues(): array
    {
        return array_keys($this->productTypes);
    }

    /**
     * @param string $label
     * @param string $value
     *
     * @return void
     */
    private function addCustomProductType(string $label, string $value): void
    {
        $this->productTypes[$value] = $label;
    }
}
