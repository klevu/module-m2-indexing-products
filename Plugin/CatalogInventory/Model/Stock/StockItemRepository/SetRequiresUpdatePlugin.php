<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Plugin\CatalogInventory\Model\Stock\StockItemRepository;

use Klevu\Configuration\Service\Provider\ApiKeysProviderInterface;
use Klevu\IndexingApi\Service\Action\SetIndexingEntitiesToRequireUpdateActionInterface;
use Klevu\IndexingApi\Service\Provider\TargetParentIdsProviderInterface;
use Klevu\IndexingProducts\Service\Determiner\RequiresUpdateCriteria\StockStatus as StockStatusCriteria;
use Klevu\IndexingProducts\Service\Provider\ProductStockStatusProviderInterface;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\CatalogInventory\Api\Data\StockItemInterface;
use Magento\CatalogInventory\Api\StockItemRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

class SetRequiresUpdatePlugin
{
    /**
     * @var LoggerInterface
     */
    private readonly LoggerInterface $logger;
    /**
     * @var StoreManagerInterface
     */
    private readonly StoreManagerInterface $storeManager;
    /**
     * @var ProductRepositoryInterface
     */
    private readonly ProductRepositoryInterface $productRepository;
    /**
     * @var ApiKeysProviderInterface
     */
    private readonly ApiKeysProviderInterface $apiKeysProvider;
    /**
     * @var StockStatusCriteria
     */
    private readonly StockStatusCriteria $stockStatusCriteria;
    /**
     * @var ProductStockStatusProviderInterface
     */
    private readonly ProductStockStatusProviderInterface $productStockStatusProvider;
    /**
     * @var SetIndexingEntitiesToRequireUpdateActionInterface
     */
    private readonly SetIndexingEntitiesToRequireUpdateActionInterface $setIndexingEntitiesToRequireUpdateAction;
    /**
     * @var TargetParentIdsProviderInterface
     */
    private readonly TargetParentIdsProviderInterface $targetParentIdsProvider;

    /**
     * @param LoggerInterface $logger
     * @param StoreManagerInterface $storeManager
     * @param ProductRepositoryInterface $productRepository
     * @param ApiKeysProviderInterface $apiKeysProvider
     * @param StockStatusCriteria $stockStatusCriteria
     * @param ProductStockStatusProviderInterface $productStockStatusProvider
     * @param SetIndexingEntitiesToRequireUpdateActionInterface $setIndexingEntitiesToRequireUpdateAction
     * @param TargetParentIdsProviderInterface $targetParentIdsProvider
     */
    public function __construct(
        LoggerInterface $logger,
        StoreManagerInterface $storeManager,
        ProductRepositoryInterface $productRepository,
        ApiKeysProviderInterface $apiKeysProvider,
        StockStatusCriteria $stockStatusCriteria,
        ProductStockStatusProviderInterface $productStockStatusProvider,
        SetIndexingEntitiesToRequireUpdateActionInterface $setIndexingEntitiesToRequireUpdateAction,
        TargetParentIdsProviderInterface $targetParentIdsProvider,
    ) {
        $this->logger = $logger;
        $this->storeManager = $storeManager;
        $this->productRepository = $productRepository;
        $this->apiKeysProvider = $apiKeysProvider;
        $this->stockStatusCriteria = $stockStatusCriteria;
        $this->productStockStatusProvider = $productStockStatusProvider;
        $this->setIndexingEntitiesToRequireUpdateAction = $setIndexingEntitiesToRequireUpdateAction;
        $this->targetParentIdsProvider = $targetParentIdsProvider;
    }

    /**
     * @param StockItemRepositoryInterface $subject
     * @param StockItemInterface $stockItem
     *
     * @return StockItemInterface[]
     */
    public function beforeSave(
        StockItemRepositoryInterface $subject, // phpcs:ignore SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter, Generic.Files.LineLength.TooLong
        StockItemInterface $stockItem,
    ): array {
        try {
            $storeId = (int)$stockItem->getStoreId();
            $store = $this->storeManager->getStore(
                storeId: $storeId,
            );
            $storeIds = $storeId
                ? [$storeId]
                : [];
            $apiKeys = $this->apiKeysProvider->get($storeIds);
            if (!$apiKeys) {
                return [$stockItem];
            }

            $product = $this->productRepository->getById(
                productId: $stockItem->getProductId(),
                storeId: $stockItem->getStoreId(),
            );
            $parentIds = $this->targetParentIdsProvider->get(
                entityType: 'KLEVU_PRODUCT',
                targetId: (int)$product->getId(),
            );
            $parentProducts = array_map(
                callback: fn (int $parentId): ProductInterface => $this->productRepository->getById(
                    productId: $parentId,
                    storeId: $storeId,
                ),
                array: $parentIds,
            );

            foreach ($apiKeys as $apiKey) {
                $entityIdsForUpdate = $this->determineEntityIdsToUpdate(
                    product: $product,
                    store: $store,
                    parentProducts: $parentProducts,
                );

                foreach ($entityIdsForUpdate as $stockStatus => $entityIds) {
                    if (!$entityIds) {
                        continue;
                    }

                    $this->setIndexingEntitiesToRequireUpdateAction->execute(
                        entityType: 'KLEVU_PRODUCT',
                        apiKey: $apiKey,
                        targetIds: $entityIds,
                        origValues: [
                            $this->stockStatusCriteria->getCriteriaIdentifier() => (bool)$stockStatus,
                        ],
                    );
                }
            }
        } catch (NoSuchEntityException) {
            // New product; we don't need to log this
        } catch (\Exception $exception) {
            $this->logger->error(
                message: 'Failed to set indexing entities to require update on stock item save',
                context: [
                    'method' => __METHOD__,
                    'exception' => $exception::class,
                    'error' => $exception->getMessage(),
                    'stockItemId' => $stockItem->getItemId(),
                    'productId' => $stockItem->getProductId(),
                    'storeId' => isset($store)
                        ? $store->getId()
                        : null,
                    'apiKeys' => $apiKeys ?? null,
                    'parentIds' => $parentIds ?? null,
                    'entityIdsForUpdate' => $entityIdsForUpdate ?? null,
                ],
            );
        }

        return [$stockItem];
    }

    /**
     * @param ProductInterface $product
     * @param StoreInterface $store
     * @param ProductInterface[] $parentProducts
     *
     * @return array<int, array<string, array<int|null>>>
     */
    private function determineEntityIdsToUpdate(
        ProductInterface $product,
        StoreInterface $store,
        array $parentProducts,
    ): array {
        $entityIdsForUpdate = [
            0 => [],
            1 => [],
        ];

        $origStockStatusForStandalone = $this->productStockStatusProvider->get(
            product: $product,
            store: $store,
            parentProduct: null,
        );
        $entityIdsForUpdate[(int)$origStockStatusForStandalone][] = [
            SetIndexingEntitiesToRequireUpdateActionInterface::ENTITY_IDS_KEY_TARGET_ID => (int)$product->getId(),
            SetIndexingEntitiesToRequireUpdateActionInterface::ENTITY_IDS_KEY_TARGET_PARENT_ID => null,
        ];

        foreach ($parentProducts as $parentProduct) {
            $origStockStatusForVariant = $this->productStockStatusProvider->get(
                product: $product,
                store: $store,
                parentProduct: $parentProduct,
            );
            $entityIdsForUpdate[(int)$origStockStatusForVariant][] = [
                SetIndexingEntitiesToRequireUpdateActionInterface::ENTITY_IDS_KEY_TARGET_ID => (int)$product->getId(),
                SetIndexingEntitiesToRequireUpdateActionInterface::ENTITY_IDS_KEY_TARGET_PARENT_ID => (int)$parentProduct->getId(), // phpcs:ignore Generic.Files.LineLength.TooLong
            ];

            $origStockStatusForParent = $this->productStockStatusProvider->get(
                product: $parentProduct,
                store: $store,
                parentProduct: null,
            );
            $entityIdsForUpdate[(int)$origStockStatusForParent][] = [
                SetIndexingEntitiesToRequireUpdateActionInterface::ENTITY_IDS_KEY_TARGET_ID => (int)$parentProduct->getId(), // phpcs:ignore Generic.Files.LineLength.TooLong
                SetIndexingEntitiesToRequireUpdateActionInterface::ENTITY_IDS_KEY_TARGET_PARENT_ID => null,
            ];
        }

        return $entityIdsForUpdate;
    }
}
