<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Service\Provider\Catalog;

use Klevu\IndexingApi\Service\Provider\Catalog\CategoryIdProviderInterface;
use Klevu\IndexingApi\Service\Provider\Catalog\ParentAnchorCategoryIdProviderInterface;
use Magento\Catalog\Api\Data\ProductInterface;
use Psr\Log\LoggerInterface;

class CategoryIdProvider implements CategoryIdProviderInterface
{
    /**
     * @var LoggerInterface
     */
    private readonly LoggerInterface $logger;
    /**
     * @var ParentAnchorCategoryIdProviderInterface
     */
    private readonly ParentAnchorCategoryIdProviderInterface $parentAnchorCategoryIdProvider;

    /**
     * @param LoggerInterface $logger
     * @param ParentAnchorCategoryIdProviderInterface $parentAnchorCategoryIdProvider
     */
    public function __construct(
        LoggerInterface $logger,
        ParentAnchorCategoryIdProviderInterface $parentAnchorCategoryIdProvider,
    ) {
        $this->logger = $logger;
        $this->parentAnchorCategoryIdProvider = $parentAnchorCategoryIdProvider;
    }

    /**
     * @param ProductInterface $product
     *
     * @return int[]
     */
    public function get(ProductInterface $product): array
    {
        return array_values(
            array: array_filter(
                array: $this->getCategoryIds($product),
            ),
        );
    }

    /**
     * @param ProductInterface $product
     *
     * @return int[]
     */
    private function getCategoryIds(ProductInterface $product): array
    {
        if (!method_exists(object_or_class: $product, method: 'getCategoryIds')) {
            $this->logger->error(
                message: 'Method: {method}, Warning: {message}',
                context: [
                    'method' => __METHOD__,
                    'message' => sprintf(
                        'Product implementation of type %s does not implement getCategoryIds',
                        get_debug_type($product),
                    ),
                ],
            );
            return [];
        }
        $categoryIds = array_map(
            callback: 'intval',
            array: $product->getCategoryIds(),
        );
        $anchorCategoryIds = $this->parentAnchorCategoryIdProvider->get(
            categoryIds: $categoryIds,
        );

        return array_unique(
            array: array_merge($categoryIds, $anchorCategoryIds),
        );
    }
}
