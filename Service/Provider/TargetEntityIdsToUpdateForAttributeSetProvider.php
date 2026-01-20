<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Service\Provider;

use Klevu\IndexingApi\Service\Provider\TargetEntityIdsToUpdateForAttributeSetProviderInterface;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SortOrderBuilder;

class TargetEntityIdsToUpdateForAttributeSetProvider implements TargetEntityIdsToUpdateForAttributeSetProviderInterface
{
    /**
     * @var SortOrderBuilder
     */
    private readonly SortOrderBuilder $sortOrderBuilder;
    /**
     * @var SearchCriteriaBuilder
     */
    private readonly SearchCriteriaBuilder $searchCriteriaBuilder;
    /**
     * @var ProductRepositoryInterface
     */
    private readonly ProductRepositoryInterface $productRepository;
    /**
     * @var int
     */
    private readonly int $pageSize;

    /**
     * @param SortOrderBuilder $sortOrderBuilder
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param ProductRepositoryInterface $productRepository
     * @param int $pageSize
     */
    public function __construct(
        SortOrderBuilder $sortOrderBuilder,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        ProductRepositoryInterface $productRepository,
        int $pageSize = 250,
    ) {
        $this->sortOrderBuilder = $sortOrderBuilder;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->productRepository = $productRepository;
        $this->pageSize = $pageSize;
    }

    /**
     * @param int $attributeSetId
     *
     * @return \Generator<array<int[]>>
     */
    public function get(
        int $attributeSetId,
    ): \Generator {
        $currentPage = 1;
        $totalPages = null;

        do {
            $this->searchCriteriaBuilder->addFilter(
                field: ProductInterface::ATTRIBUTE_SET_ID,
                value: $attributeSetId,
                conditionType: 'eq',
            );

            $this->sortOrderBuilder->setField(
                field: 'entity_id',
            );
            $this->sortOrderBuilder->setAscendingDirection();
            $this->searchCriteriaBuilder->addSortOrder(
                sortOrder: $this->sortOrderBuilder->create(),
            );

            $this->searchCriteriaBuilder->setCurrentPage($currentPage);
            $this->searchCriteriaBuilder->setPageSize($this->pageSize);

            $productsResult = $this->productRepository->getList(
                searchCriteria: $this->searchCriteriaBuilder->create(),
            );

            if (null === $totalPages) {
                $totalPages = ceil($productsResult->getTotalCount() / $this->pageSize);
            }

            $products = $productsResult->getItems();
            $currentPage++;

            yield array_map(
                callback: static fn (ProductInterface $product): int => (int)$product->getId(),
                array: $products,
            );
        } while (null !== $totalPages && $currentPage <= $totalPages);
    }
}
