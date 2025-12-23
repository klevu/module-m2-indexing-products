<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Service\Provider;

use Klevu\Configuration\Service\Provider\ApiKeysProviderInterface;
use Klevu\Configuration\Service\Provider\StoresProviderInterface;
use Klevu\IndexingApi\Api\Data\EntitySyncConditionsValuesInterface;
use Klevu\IndexingApi\Api\Data\EntitySyncConditionsValuesInterfaceFactory;
use Klevu\IndexingApi\Api\Data\IndexingEntityInterface;
use Klevu\IndexingApi\Service\Determiner\IsIndexableDeterminerInterface;
use Klevu\IndexingApi\Service\Provider\EntitySyncConditionsValuesProviderInterface;
use Klevu\IndexingApi\Service\Provider\IndexingEntityProviderInterface;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable as ConfigurableType;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Api\Data\StoreInterface;

class EntitySyncConditionsValuesProvider implements EntitySyncConditionsValuesProviderInterface
{
    /**
     * @var EntitySyncConditionsValuesInterfaceFactory
     */
    private readonly EntitySyncConditionsValuesInterfaceFactory $entitySyncConditionsValuesFactory;
    /**
     * @var IndexingEntityProviderInterface
     */
    private readonly IndexingEntityProviderInterface $indexingEntityProvider;
    /**
     * @var ApiKeysProviderInterface
     */
    private readonly ApiKeysProviderInterface $apiKeysProvider;
    /**
     * @var StoresProviderInterface
     */
    private readonly StoresProviderInterface $storesProvider;
    /**
     * @var IsIndexableDeterminerInterface 
     */
    private readonly IsIndexableDeterminerInterface $isIndexableDeterminer;
    /**
     * @var ProductStatusProviderInterface
     */
    private readonly ProductStatusProviderInterface $productStatusProvider;
    /**
     * @var ProductStockStatusProviderInterface
     */
    private readonly ProductStockStatusProviderInterface $productStockStatusProvider;
    /**
     * @var ProductRepositoryInterface
     */
    private readonly ProductRepositoryInterface $productRepository;
    /**
     * @var ConfigurableType
     */
    private readonly ConfigurableType $configurableType;

    /**
     * @param EntitySyncConditionsValuesInterfaceFactory $entitySyncConditionsValuesFactory
     * @param IndexingEntityProviderInterface $indexingEntityProvider
     * @param ApiKeysProviderInterface $apiKeysProvider
     * @param StoresProviderInterface $storesProvider
     * @param IsIndexableDeterminerInterface $isIndexableDeterminer
     * @param ProductStatusProviderInterface $productStatusProvider
     * @param ProductStockStatusProviderInterface $productStockStatusProvider
     * @param ProductRepositoryInterface $productRepository
     * @param ConfigurableType $configurableType
     */
    public function __construct(
        EntitySyncConditionsValuesInterfaceFactory $entitySyncConditionsValuesFactory,
        IndexingEntityProviderInterface $indexingEntityProvider,
        ApiKeysProviderInterface $apiKeysProvider,
        StoresProviderInterface $storesProvider,
        IsIndexableDeterminerInterface $isIndexableDeterminer,
        ProductStatusProviderInterface $productStatusProvider,
        ProductStockStatusProviderInterface $productStockStatusProvider,
        ProductRepositoryInterface $productRepository,
        ConfigurableType $configurableType,
    ) {
        $this->entitySyncConditionsValuesFactory = $entitySyncConditionsValuesFactory;
        $this->indexingEntityProvider = $indexingEntityProvider;
        $this->apiKeysProvider = $apiKeysProvider;
        $this->storesProvider = $storesProvider;
        $this->isIndexableDeterminer = $isIndexableDeterminer;
        $this->productStatusProvider = $productStatusProvider;
        $this->productStockStatusProvider = $productStockStatusProvider;
        $this->productRepository = $productRepository;
        $this->configurableType = $configurableType;
    }

    /**
     * @param string $targetEntityType
     * @param int $targetEntityId
     *
     * @return EntitySyncConditionsValuesInterface[]
     */
    public function get(
        string $targetEntityType, // phpcs:ignore SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter
        int $targetEntityId,
    ): array {
        $return = [];

        $apiKeys = $this->apiKeysProvider->get(storeIds: []);
        $indexingEntities = $this->indexingEntityProvider->get(
            entityType: 'KLEVU_PRODUCT',
            apiKeys: $apiKeys,
            entityIds: [$targetEntityId],
        );
        $configurableParentIds = $this->configurableType->getParentIdsByChild(
            childId: (int)$targetEntityId,
        );
        
        foreach ($apiKeys as $apiKey) {
            $storesForApiKey = $this->storesProvider->get(
                apiKey: $apiKey,
            );
            
            foreach ($storesForApiKey as $store) {
                try {
                    $productInStore = $this->productRepository->getById(
                        productId: (int)$targetEntityId,
                        editMode: false,
                        storeId: (int)$store->getId(),
                        forceReload: true,
                    );
                } catch (NoSuchEntityException) {
                    $productInStore = null;
                }
                $discoveredIndexingEntities = array_filter(
                    array: $indexingEntities,
                    callback: static fn (IndexingEntityInterface $indexingEntity) => (
                        $indexingEntity->getApiKey() === $apiKey
                        && $indexingEntity->getTargetId() === (int)$targetEntityId
                        && !$indexingEntity->getTargetParentId()
                    ),
                );

                $return[] = $this->createItem(
                    apiKey: $apiKey,
                    store: $store,
                    productInStore: $productInStore,
                    parentProductInStore: null,
                    indexingEntity: $discoveredIndexingEntities
                        ? current($discoveredIndexingEntities)
                        : null,
                );

                foreach ($configurableParentIds as $configurableParentId) {
                    try {
                        $parentProductProductInStore = $this->productRepository->getById(
                            productId: (int)$configurableParentId,
                            editMode: false,
                            storeId: (int)$store->getId(),
                            forceReload: true,
                        );
                    } catch (NoSuchEntityException) {
                        $parentProductProductInStore = null;
                    }
                    $discoveredIndexingEntities = array_filter(
                        array: $indexingEntities,
                        callback: static fn (IndexingEntityInterface $indexingEntity) => (
                            $indexingEntity->getApiKey() === $apiKey
                            && $indexingEntity->getTargetId() === (int)$targetEntityId
                            && $indexingEntity->getTargetParentId() === (int)$configurableParentId
                        ),
                    );

                    $return[] = $this->createItem(
                        apiKey: $apiKey,
                        store: $store,
                        productInStore: $productInStore,
                        parentProductInStore: $parentProductProductInStore,
                        indexingEntity: $discoveredIndexingEntities
                            ? current($discoveredIndexingEntities)
                            : null,
                    );
                }
            }
        }

        return $return;
    }

    /**
     * @param string $apiKey
     * @param StoreInterface $store
     * @param ProductInterface|null $productInStore
     * @param ProductInterface|null $parentProductInStore
     * @param IndexingEntityInterface|null $indexingEntity
     *
     * @return EntitySyncConditionsValuesInterface
     */
    private function createItem(
        string $apiKey,
        StoreInterface $store,
        ?ProductInterface $productInStore,
        ?ProductInterface $parentProductInStore,
        ?IndexingEntityInterface $indexingEntity,
    ): EntitySyncConditionsValuesInterface {
        $item = $this->entitySyncConditionsValuesFactory->create();

        $item->setApiKey($apiKey);
        $item->setStore($store);
        $item->setTargetEntityType('KLEVU_PRODUCT');
        if ($productInStore) {
            $item->setTargetEntity($productInStore);
        }
        if ($parentProductInStore) {
            $item->setTargetParentEntity($parentProductInStore);
        }
        if ($indexingEntity) {
            $item->setIndexingEntity($indexingEntity);
        }

        $item->setIsIndexable(
            isIndexable: $productInStore && $this->isIndexableDeterminer->execute(
                entity: $productInStore,
                store: $store,
                entitySubtype: $indexingEntity?->getTargetEntitySubtype() ?? '',
            ),
        );
        $item->addSyncConditionsValue(
            key: 'is_enabled',
            value: $productInStore && $this->productStatusProvider->get(
                product: $productInStore,
                store: $store,
                parentProduct: $parentProductInStore,
            ),
        );
        $item->addSyncConditionsValue(
            key: 'is_in_stock',
            value: $productInStore && $this->productStockStatusProvider->get(
                product: $productInStore,
                store: $store,
                parentProduct: $parentProductInStore,
            ),
        );

        return $item;
    }
}
