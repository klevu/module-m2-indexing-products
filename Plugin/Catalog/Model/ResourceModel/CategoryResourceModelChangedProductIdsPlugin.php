<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Plugin\Catalog\Model\ResourceModel;

use Klevu\Indexing\Model\Update\Entity;
use Klevu\IndexingApi\Service\EntityUpdateResponderServiceInterface;
use Magento\Catalog\Api\Data\CategoryInterface;
use Magento\Catalog\Model\ResourceModel\Category as CategoryResourceModel;
use Magento\Framework\Model\AbstractModel;

class CategoryResourceModelChangedProductIdsPlugin
{
    /**
     * @var EntityUpdateResponderServiceInterface
     */
    private readonly EntityUpdateResponderServiceInterface $responderService;

    /**
     * @param EntityUpdateResponderServiceInterface $responderService
     */
    public function __construct(
        EntityUpdateResponderServiceInterface $responderService,
    ) {
        $this->responderService = $responderService;
    }

    /**
     * @param CategoryResourceModel $categoryResourceSubject
     * @param CategoryResourceModel $result
     * @param AbstractModel $category
     *
     * @return CategoryResourceModel
     */
    public function afterSave(
        CategoryResourceModel $categoryResourceSubject,
        CategoryResourceModel $result,
        AbstractModel $category,
    ): CategoryResourceModel {
        if (!$category instanceof CategoryInterface) {
            return $result;
        }

        $categoryResourceSubject->addCommitCallback(function () use ($category): void {
            if (false === $category->getDataUsingMethod('is_changed_product_list')) {
                return;
            }
            $productIds = array_filter(
                array_unique(
                    ($category->getDataUsingMethod('changed_product_ids') ?? []),
                ),
            );
            if ($productIds) {
                $this->responderService->execute([
                    Entity::ENTITY_IDS => $productIds,
                    Entity::STORE_IDS => $this->getStoreIds($category),
                ]);
            }
        });

        return $result;
    }

    /**
     *
     * @param CategoryResourceModel $categoryResourceSubject
     * @param CategoryResourceModel $result
     * @param AbstractModel $category
     *
     * @return CategoryResourceModel
     */
    public function afterDelete(
        CategoryResourceModel $categoryResourceSubject,
        CategoryResourceModel $result,
        AbstractModel $category,
    ): CategoryResourceModel {
        if (!$category instanceof CategoryInterface) {
            return $result;
        }

        $categoryResourceSubject->addCommitCallback(function () use ($category): void {
            $this->responderService->execute([
                Entity::ENTITY_IDS => array_keys($category->getProductsPosition()),
                Entity::STORE_IDS => $this->getStoreIds($category),
            ]);
        });

        return $result;
    }

    /**
     * Get store IDs for the given category
     *
     * @param CategoryInterface $category
     *
     * @return int[]
     */
    private function getStoreIds(CategoryInterface $category): array
    {
        if (!method_exists($category, 'getStoreIds')) {
            return [];
        }
        return array_map(
            'intval',
            $category->getStoreIds() ?? [],
        );
    }
}
