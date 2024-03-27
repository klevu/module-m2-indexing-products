<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Service\Provider\Catalog;

use Klevu\IndexingApi\Service\Provider\Catalog\ParentAnchorCategoryIdProviderInterface;
use Magento\Catalog\Api\Data\CategoryInterface;
use Magento\Catalog\Model\ResourceModel\Category\Collection as CategoryCollection;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;

class ParentAnchorCategoryIdProvider implements ParentAnchorCategoryIdProviderInterface
{
    /**
     * @var CategoryCollectionFactory
     */
    private readonly CategoryCollectionFactory $categoryCollectionFactory;

    /**
     * @param CategoryCollectionFactory $categoryCollectionFactory
     */
    public function __construct(
        CategoryCollectionFactory $categoryCollectionFactory,
    ) {
        $this->categoryCollectionFactory = $categoryCollectionFactory;
    }

    /**
     * @param int[] $categoryIds
     *
     * @return int[]
     */
    public function get(array $categoryIds): array
    {
        if (empty($categoryIds)) {
            return [];
        }

        return $this->getParentAnchorCategoryIds(
            categoryIds: $this->getParentCategoryIds($categoryIds),
        );
    }

    /**
     * @param int[] $categoryIds
     *
     * @return int[]
     */
    private function getParentCategoryIds(array $categoryIds): array
    {
        $parentCategories = $this->getCategories(categoryIds: $categoryIds, isAnchorFilter: null);

        return array_map(
            callback: static fn (CategoryInterface $category): int => (int)$category->getParentId(),
            array: $parentCategories,
        );
    }

    /**
     * @param int[] $categoryIds
     *
     * @return int[]
     */
    private function getParentAnchorCategoryIds(array $categoryIds): array
    {
        $return = [];
        $categories = $this->getCategories(categoryIds: $categoryIds, isAnchorFilter: true);
        $anchorCategoryIds = array_map(
            callback: static fn (CategoryInterface $category): int => (int)$category->getId(),
            array: $categories,
        );
        $return[] = $anchorCategoryIds;
        $parentCategoryIds = array_map(
            callback: static fn (CategoryInterface $category): int => (int)$category->getParentId(),
            array: $categories,
        );
        if ($parentCategoryIds) {
            $return[] = $this->getParentAnchorCategoryIds(
                categoryIds: $parentCategoryIds,
            );
        }

        return array_merge(...$return);
    }

    /**
     * @param int[] $categoryIds
     * @param bool|null $isAnchorFilter
     *
     * @return CategoryInterface[]
     */
    private function getCategories(array $categoryIds, ?bool $isAnchorFilter = null): array
    {
        /** @var CategoryCollection $categoryCollection */
        $categoryCollection = $this->categoryCollectionFactory->create();
        $categoryCollection->addFieldToSelect(field: 'parent_id');
        $categoryCollection->addFieldToFilter('entity_id', ['in' => $categoryIds]);
        if (null !== $isAnchorFilter) {
            $categoryCollection->addFieldToFilter(
                'is_anchor',
                ['eq' => (string)(int)$isAnchorFilter],
            );
        }
        /** @var CategoryInterface[] $return */
        $return = $categoryCollection->getItems();

        return $return;
    }
}
