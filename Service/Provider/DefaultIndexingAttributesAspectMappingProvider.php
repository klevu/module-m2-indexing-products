<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Service\Provider;

use Klevu\IndexingApi\Service\Provider\DefaultIndexingAttributesAspectMappingProviderInterface;
use Klevu\IndexingProducts\Model\Attribute\KlevuImageInterface;
use Klevu\IndexingProducts\Model\Attribute\KlevuRatingCountInterface;
use Klevu\IndexingProducts\Model\Attribute\KlevuRatingInterface;
use Klevu\IndexingProducts\Model\Source\Aspect;
use Magento\Catalog\Api\Data\ProductAttributeInterface;
use Magento\Catalog\Api\Data\ProductInterface;

class DefaultIndexingAttributesAspectMappingProvider implements DefaultIndexingAttributesAspectMappingProviderInterface
{
    /**
     * @var array<string, Aspect>
     */
    private array $aspectMapping = [
        'category_ids' => Aspect::CATEGORIES,
        ProductAttributeInterface::CODE_DESCRIPTION => Aspect::ATTRIBUTES,
        KlevuImageInterface::ATTRIBUTE_CODE => Aspect::ATTRIBUTES,
        ProductAttributeInterface::CODE_SEO_FIELD_META_DESCRIPTION => Aspect::ATTRIBUTES,
        ProductAttributeInterface::CODE_SEO_FIELD_META_KEYWORD => Aspect::ATTRIBUTES,
        'minimal_price' => Aspect::PRICE,
        ProductInterface::NAME => Aspect::ATTRIBUTES,
        ProductInterface::PRICE => Aspect::PRICE,
        'price_type' => Aspect::PRICE,
        'price_view' => Aspect::PRICE,
        'quantity_and_stock_status' => Aspect::STOCK,
        KlevuRatingInterface::ATTRIBUTE_CODE => Aspect::ATTRIBUTES,
        KlevuRatingCountInterface::ATTRIBUTE_CODE => Aspect::ATTRIBUTES,
        ProductAttributeInterface::CODE_SHORT_DESCRIPTION => Aspect::ATTRIBUTES,
        ProductInterface::SKU => Aspect::ATTRIBUTES,
        'sku_type' => Aspect::ATTRIBUTES,
        'special_from_date' => Aspect::PRICE,
        'special_price' => Aspect::PRICE,
        'special_to_date' => Aspect::PRICE,
        ProductInterface::STATUS => Aspect::ATTRIBUTES,
        'tax_class_id' => Aspect::PRICE,
        'tier_price' => Aspect::PRICE,
        ProductAttributeInterface::CODE_SEO_FIELD_URL_KEY => Aspect::ATTRIBUTES,
        'url_path' => Aspect::ATTRIBUTES,
        ProductInterface::VISIBILITY => Aspect::VISIBILITY,
    ];

    /**
     * @return array<string, Aspect>
     */
    public function get(): array
    {
        return $this->aspectMapping;
    }
}
