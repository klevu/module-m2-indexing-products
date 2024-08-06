<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Service\Provider;

use Klevu\Configuration\Service\Provider\ScopeConfigProviderInterface;
use Klevu\IndexingApi\Service\Provider\Discovery\ProductEntityCollectionInterface;
use Klevu\IndexingApi\Service\Provider\EntityProviderInterface;
use Magento\Bundle\Model\Product\Type as BundleType;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\Product\Type as ProductType;
use Magento\Catalog\Model\ResourceModel\Product\Collection as ProductCollection;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable as ConfigurableType;
use Magento\Downloadable\Model\Product\Type as DownloadableType;
use Magento\GroupedProduct\Model\Product\Type\Grouped as GroupedType;
use Magento\Store\Api\Data\StoreInterface;
use Psr\Log\LoggerInterface;

class ProductEntityProvider implements EntityProviderInterface
{
    public const ENTITY_SUBTYPE_SIMPLE = ProductType::TYPE_SIMPLE;
    public const ENTITY_SUBTYPE_VIRTUAL = ProductType::TYPE_VIRTUAL;
    public const ENTITY_SUBTYPE_DOWNLOADABLE = DownloadableType::TYPE_DOWNLOADABLE;
    public const ENTITY_SUBTYPE_GROUPED = GroupedType::TYPE_CODE;
    public const ENTITY_SUBTYPE_BUNDLE = BundleType::TYPE_CODE;
    public const ENTITY_SUBTYPE_CONFIGURABLE = ConfigurableType::TYPE_CODE;
    public const ENTITY_SUBTYPE_CONFIGURABLE_VARIANTS = 'configurable_variants';

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
     * @var string
     */
    private readonly string $entitySubtype;

    /**
     * @param ProductEntityCollectionInterface $productEntityCollection
     * @param ScopeConfigProviderInterface $syncEnabledProvider
     * @param LoggerInterface $logger
     * @param string $entitySubtype
     */
    public function __construct(
        ProductEntityCollectionInterface $productEntityCollection,
        ScopeConfigProviderInterface $syncEnabledProvider,
        LoggerInterface $logger,
        string $entitySubtype,
    ) {
        $this->productEntityCollection = $productEntityCollection;
        $this->syncEnabledProvider = $syncEnabledProvider;
        $this->logger = $logger;
        $this->entitySubtype = $entitySubtype;
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
     * @return string
     */
    public function getEntitySubtype(): string
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
