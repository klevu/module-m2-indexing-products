<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Plugin\Catalog\Model\Product\Attribute;

use Klevu\Indexing\Model\Update\Entity;
use Klevu\IndexingApi\Service\EntityUpdateResponderServiceInterface;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product\Attribute\Management as ProductAttributeManagement;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SearchCriteriaBuilderFactory;

class ManagementPlugin
{
    /**
     * @var EntityUpdateResponderServiceInterface
     */
    private readonly EntityUpdateResponderServiceInterface $responderService;
    /**
     * @var ProductRepositoryInterface
     */
    private readonly ProductRepositoryInterface $productRepository;
    /**
     * @var SearchCriteriaBuilderFactory
     */
    private SearchCriteriaBuilderFactory $searchCriteriaBuilderFactory;

    /**
     * @param EntityUpdateResponderServiceInterface $responderService
     * @param ProductRepositoryInterface $productRepository
     * @param SearchCriteriaBuilderFactory $searchCriteriaBuilderFactory
     */
    public function __construct(
        EntityUpdateResponderServiceInterface $responderService,
        ProductRepositoryInterface $productRepository,
        SearchCriteriaBuilderFactory $searchCriteriaBuilderFactory,
    ) {
        $this->responderService = $responderService;
        $this->productRepository = $productRepository;
        $this->searchCriteriaBuilderFactory = $searchCriteriaBuilderFactory;
    }

    /**
     * @param ProductAttributeManagement $subject
     * @param string|int $result
     * @param int $attributeSetId
     * @param int $attributeGroupId
     * @param string $attributeCode
     * @param int $sortOrder
     *
     * @return string|int
     */
    public function afterAssign(
        // phpcs:ignore SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter
        ProductAttributeManagement $subject,
        mixed $result,
        mixed $attributeSetId,
        // phpcs:ignore SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter
        mixed $attributeGroupId,
        // phpcs:ignore SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter
        mixed $attributeCode,
        // phpcs:ignore SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter
        mixed $sortOrder,
    ): mixed {
        $entityIds = $this->getProductIdsForAttributeSet((int)$attributeSetId);
        $this->responderService->execute(data: [
            Entity::ENTITY_IDS => $entityIds,
        ]);

        return $result;
    }

    /**
     * @param ProductAttributeManagement $subject
     * @param string|int $result
     * @param int $attributeSetId
     * @param string $attributeCode
     *
     * @return mixed
     */
    public function afterUnassign(
        // phpcs:ignore SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter
        ProductAttributeManagement $subject,
        mixed $result,
        mixed $attributeSetId,
        // phpcs:ignore SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter
        mixed $attributeCode,
    ): mixed {
        $entityIds = $this->getProductIdsForAttributeSet((int)$attributeSetId);
        $this->responderService->execute(data: [
            Entity::ENTITY_IDS => $entityIds,
        ]);

        return $result;
    }

    /**
     * @param int $attributeSetId
     *
     * @return int[]
     */
    private function getProductIdsForAttributeSet(int $attributeSetId): array
    {
        /** @var SearchCriteriaBuilder $searchCriteriaBuilder */
        $searchCriteriaBuilder = $this->searchCriteriaBuilderFactory->create();
        $searchCriteriaBuilder->addFilter(
            field: ProductInterface::ATTRIBUTE_SET_ID,
            value: $attributeSetId,
        );
        $searchCriteria = $searchCriteriaBuilder->create();
        $searchResult = $this->productRepository->getList($searchCriteria);

        return array_map(
            callback: static fn (ProductInterface $product): int => ((int)$product->getId()),
            array: $searchResult->getItems(),
        );
    }
}
