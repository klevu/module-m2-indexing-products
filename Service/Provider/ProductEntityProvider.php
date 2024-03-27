<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Service\Provider;

use Klevu\Configuration\Service\Provider\ScopeConfigProviderInterface;
use Klevu\IndexingApi\Service\Provider\Discovery\ProductEntityCollectionInterface;
use Klevu\IndexingApi\Service\Provider\EntityProviderInterface;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\ResourceModel\Product\Collection as ProductCollection;
use Magento\Store\Api\Data\StoreInterface;
use Psr\Log\LoggerInterface;

class ProductEntityProvider implements EntityProviderInterface
{
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
     * @param ProductEntityCollectionInterface $productEntityCollection
     * @param ScopeConfigProviderInterface $syncEnabledProvider
     * @param LoggerInterface $logger
     */
    public function __construct(
        ProductEntityCollectionInterface $productEntityCollection,
        ScopeConfigProviderInterface $syncEnabledProvider,
        LoggerInterface $logger,
    ) {
        $this->productEntityCollection = $productEntityCollection;
        $this->syncEnabledProvider = $syncEnabledProvider;
        $this->logger = $logger;
    }

    /**
     * @param StoreInterface|null $store
     * @param int[]|null $entityIds
     *
     * @return \Generator|null
     */
    public function get(?StoreInterface $store = null, ?array $entityIds = []): ?\Generator
    {
        if (!$this->syncEnabledProvider->get()) {
            return null;
        }
        $collection = $this->productEntityCollection->get(store: $store, entityIds: $entityIds);
        $this->logQuery($collection);

        /** @var ProductInterface $product */
        foreach ($collection as $product) {
            yield $product;
        }
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
