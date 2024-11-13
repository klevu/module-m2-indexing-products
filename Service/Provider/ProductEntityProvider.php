<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Service\Provider;

use Klevu\Configuration\Service\Provider\ScopeConfigProviderInterface;
use Klevu\IndexingApi\Service\Provider\Discovery\ProductEntityCollectionInterface;
use Klevu\IndexingApi\Service\Provider\EntityProviderInterface;
use Klevu\IndexingProducts\Model\Source\EntitySubtypeOptions;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\ResourceModel\Product\Collection as ProductCollection;
use Magento\Store\Api\Data\StoreInterface;
use Psr\Log\LoggerInterface;

class ProductEntityProvider implements EntityProviderInterface
{
    public const ENTITY_SUBTYPE_SIMPLE = EntitySubtypeOptions::SIMPLE;
    public const ENTITY_SUBTYPE_VIRTUAL = EntitySubtypeOptions::VIRTUAL;
    public const ENTITY_SUBTYPE_DOWNLOADABLE = EntitySubtypeOptions::DOWNLOADABLE;
    public const ENTITY_SUBTYPE_GROUPED = EntitySubtypeOptions::GROUPED;
    public const ENTITY_SUBTYPE_BUNDLE = EntitySubtypeOptions::BUNDLE;
    public const ENTITY_SUBTYPE_CONFIGURABLE = EntitySubtypeOptions::CONFIGURABLE;
    public const ENTITY_SUBTYPE_CONFIGURABLE_VARIANTS = EntitySubtypeOptions::CONFIGURABLE_VARIANTS;

    /**
     * @var ProductEntityCollectionInterface
     */
    private readonly ProductEntityCollectionInterface $productEntityCollection;
    /**
     * @var ScopeConfigProviderInterface
     */
    private readonly ScopeConfigProviderInterface $syncEnabledProvider;
    /**
     * @var LoggerInterface
     */
    private readonly LoggerInterface $logger;
    /**
     * @var string|null
     */
    private readonly ?string $entitySubtype;
    /**
     * @var int|null
     */
    private readonly ?int $batchSize;

    /**
     * @param ProductEntityCollectionInterface $productEntityCollection
     * @param ScopeConfigProviderInterface $syncEnabledProvider
     * @param LoggerInterface $logger
     * @param string|null $entitySubtype
     * @param int|null $batchSize
     */
    public function __construct(
        ProductEntityCollectionInterface $productEntityCollection,
        ScopeConfigProviderInterface $syncEnabledProvider,
        LoggerInterface $logger,
        ?string $entitySubtype = null,
        ?int $batchSize = null,
    ) {
        $this->productEntityCollection = $productEntityCollection;
        $this->syncEnabledProvider = $syncEnabledProvider;
        $this->logger = $logger;
        $this->entitySubtype = $entitySubtype;
        $this->batchSize = $batchSize;
    }

    /**
     * @param StoreInterface|null $store
     * @param int[]|null $entityIds
     *
     * @return \Generator<ProductInterface[]>|null
     */
    public function get(?StoreInterface $store = null, ?array $entityIds = []): ?\Generator
    {
        if (!$this->syncEnabledProvider->get()) {
            return null;
        }
        sort($entityIds);
        $currentEntityId = 0;
        $lastEntityId = $entityIds
            ? $entityIds[array_keys($entityIds)[count($entityIds) - 1]]
            : $this->productEntityCollection->getLastId(entityType: $this->entitySubtype);

        if (!$lastEntityId) {
            return;
        }
        while ($currentEntityId < $lastEntityId) {
            $collection = $this->productEntityCollection->get(
                store: $store,
                entityIds: $entityIds,
                pageSize: $this->batchSize,
                currentEntityId: $currentEntityId + 1,
            );
            $this->logQuery($collection);
            if (!$collection->getSize()) {
                break;
            }
            /** @var ProductInterface[] $products */
            $products = $collection->getItems();
            yield $products;
            $lastProduct = array_pop($products);
            $currentEntityId = (int)$lastProduct?->getId();
            if (null === $this->batchSize || !$currentEntityId) {
                break;
            }
        }
    }

    /**
     * @return string|null
     */
    public function getEntitySubtype(): ?string
    {
        return $this->entitySubtype;
    }

    /**
     * @param ProductCollection $collection
     *
     * @return void
     */
    private function logQuery(ProductCollection $collection): void
    {
        $this->logger->debug(
            message: 'Method: {method}, Debug: {message}',
            context: [
                'method' => __METHOD__,
                'message' =>
                    sprintf(
                        'Product Entity Provider Query: %s',
                        $collection->getSelect()->__toString(),
                    ),
            ],
        );
    }
}
