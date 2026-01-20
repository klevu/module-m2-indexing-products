<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Service\Provider;

use Klevu\IndexingApi\Service\Action\SetIndexingEntitiesToRequireUpdateActionInterface;
use Klevu\IndexingApi\Service\Provider\TargetParentIdsProviderInterface;
use Klevu\IndexingProducts\Exception\ConflictingStockStatusesForTargetIdsException;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Api\Data\StoreInterface;

class TargetIdsToRequireUpdateByStockStatusProvider implements TargetIdsToRequireUpdateByStockStatusProviderInterface
{
    /**
     * @var ProductRepositoryInterface
     */
    private ProductRepositoryInterface $productRepository;
    /**
     * @var TargetParentIdsProviderInterface
     */
    private TargetParentIdsProviderInterface $targetParentIdsProvider;
    /**
     * @var ProductStockStatusProviderInterface
     */
    private readonly ProductStockStatusProviderInterface $productStockStatusProvider;

    /**
     * @param ProductRepositoryInterface $productRepository
     * @param TargetParentIdsProviderInterface $targetParentIdsProvider
     * @param ProductStockStatusProviderInterface $productStockStatusProvider
     */
    public function __construct(
        ProductRepositoryInterface $productRepository,
        TargetParentIdsProviderInterface $targetParentIdsProvider,
        ProductStockStatusProviderInterface $productStockStatusProvider,
    ) {
        $this->productRepository = $productRepository;
        $this->targetParentIdsProvider = $targetParentIdsProvider;
        $this->productStockStatusProvider = $productStockStatusProvider;
    }

    /**
     * @param int $productId
     * @param StoreInterface[] $stores
     *
     * @return array<int, array<string, int>>
     * @throws ConflictingStockStatusesForTargetIdsException
     * @throws NoSuchEntityException
     */
    public function getByProductId(int $productId, array $stores): array
    {
        return $this->get(
            product: $this->productRepository->getById($productId),
            stores: $stores,
        );
    }

    /**
     * @param string $sku
     * @param StoreInterface[] $stores
     *
     * @return array<int, array<string, int>>
     * @throws ConflictingStockStatusesForTargetIdsException
     * @throws NoSuchEntityException
     */
    public function getBySku(string $sku, array $stores): array
    {
        return $this->get(
            product: $this->productRepository->get($sku),
            stores: $stores,
        );
    }

    /**
     * @param ProductInterface $product
     * @param StoreInterface[] $stores
     *
     * @return array<int, array<string, int>>
     * @throws ConflictingStockStatusesForTargetIdsException
     */
    public function get(ProductInterface $product, array $stores): array
    {
        $parentIds = $this->targetParentIdsProvider->get(
            entityType: 'KLEVU_PRODUCT',
            targetId: (int)$product->getId(),
        );

        $targetIdsByStore = [];
        foreach ($stores as $store) {
            $parentProducts = array_map(
                callback: function (int $parentId) use ($store): ?ProductInterface {
                    try {
                        $return = $this->productRepository->getById(
                            productId: $parentId,
                            storeId: (int)$store->getId(),
                        );
                    } catch (NoSuchEntityException) {
                        $return = null;
                    }

                    return $return;
                },
                array: $parentIds,
            );
            $parentProducts = array_filter($parentProducts);

            $targetIdsByStore[$store->getId()] = $this->determineTargetIds(
                product: $product,
                store: $store,
                parentProducts: $parentProducts,
            );
        }

        $return = $this->mergeTargetIdsByStore($targetIdsByStore);

        if ($this->hasConflictingStockStatusesForUpdate($return)) {
            throw new ConflictingStockStatusesForTargetIdsException(
                targetIdsByStockStatus: $return,
            );
        }

        return $return;
    }

    /**
     * @param ProductInterface $product
     * @param StoreInterface $store
     * @param ProductInterface[] $parentProducts
     *
     * @return array<int, array<string, array<int|null>>>
     */
    private function determineTargetIds(
        ProductInterface $product,
        StoreInterface $store,
        array $parentProducts,
    ): array {
        $targetIds = [
            0 => [],
            1 => [],
        ];

        $origStockStatusForStandalone = $this->productStockStatusProvider->get(
            product: $product,
            store: $store,
            parentProduct: null,
        );
        $targetIds[(int)$origStockStatusForStandalone][] = [
            SetIndexingEntitiesToRequireUpdateActionInterface::ENTITY_IDS_KEY_TARGET_ID => (int)$product->getId(),
            SetIndexingEntitiesToRequireUpdateActionInterface::ENTITY_IDS_KEY_TARGET_PARENT_ID => null,
        ];

        foreach ($parentProducts as $parentProduct) {
            $origStockStatusForVariant = $this->productStockStatusProvider->get(
                product: $product,
                store: $store,
                parentProduct: $parentProduct,
            );
            $targetIds[(int)$origStockStatusForVariant][] = [
                SetIndexingEntitiesToRequireUpdateActionInterface::ENTITY_IDS_KEY_TARGET_ID => (int)$product->getId(),
                SetIndexingEntitiesToRequireUpdateActionInterface::ENTITY_IDS_KEY_TARGET_PARENT_ID => (int)$parentProduct->getId(), // phpcs:ignore Generic.Files.LineLength.TooLong
            ];

            $origStockStatusForParent = $this->productStockStatusProvider->get(
                product: $parentProduct,
                store: $store,
                parentProduct: null,
            );
            $targetIds[(int)$origStockStatusForParent][] = [
                SetIndexingEntitiesToRequireUpdateActionInterface::ENTITY_IDS_KEY_TARGET_ID => (int)$parentProduct->getId(), // phpcs:ignore Generic.Files.LineLength.TooLong
                SetIndexingEntitiesToRequireUpdateActionInterface::ENTITY_IDS_KEY_TARGET_PARENT_ID => null,
            ];
        }

        return $targetIds;
    }

    /**
     * @param array<int, array<int, array<string, array<int|null>>>> $targetIdsByStore
     *
     * @return array<int, array<string, array<int|null>>>
     */
    private function mergeTargetIdsByStore(
        array $targetIdsByStore,
    ): array {
        $return = [
            0 => [],
            1 => [],
        ];

        foreach ($targetIdsByStore as $targetIds) {
            foreach ($targetIds as $stockStatusFlag => $targetIdsItems) {
                foreach ($targetIdsItems as $targetIdsItem) {
                    if (in_array($targetIdsItem, $return[$stockStatusFlag], true)) {
                        continue;
                    }

                    $return[$stockStatusFlag][] = $targetIdsItem;
                }
            }
        }

        return $return;
    }

    /**
     * @param array<int, array<string, array<int|null>>> $targetIds
     *
     * @return bool
     */
    private function hasConflictingStockStatusesForUpdate(
        array $targetIds,
    ): bool {
        if (
            empty($targetIds[0])
            || empty($targetIds[1])
        ) {
            return false;
        }

        $targetIdsCompact = [
            0 => [],
            1 => [],
        ];
        foreach ($targetIds as $stockStatusKey => $targetIdItems) {
            $targetIdsCompact[$stockStatusKey] = array_map(
                callback: static fn (array $entityIdItem): string => implode('::', $entityIdItem),
                array: $targetIdItems,
            );
        }

        return !!array_intersect(
            $targetIdsCompact[0],
            $targetIdsCompact[1],
        );
    }
}
