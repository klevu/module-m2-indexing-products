<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Service\Provider\Discovery;

use Klevu\IndexingApi\Service\Provider\Discovery\ProductEntityCollectionInterface;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\Product\Type;
use Magento\Catalog\Model\ResourceModel\Product\Collection as ProductCollection;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\CatalogInventory\Helper\Stock as StockHelper;
use Magento\Eav\Model\Entity;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\Store;

class ProductEntityCollection implements ProductEntityCollectionInterface
{
    /**
     * @var ProductCollectionFactory
     */
    private readonly ProductCollectionFactory $productCollectionFactory;
    /**
     * Would rather not use this deprecated class,
     * however MSI has a plugin on this method to set the right data
     * and this allows us to be compatible if MSI is removed from the codebase
     *
     * @var StockHelper
     */
    private readonly StockHelper $stockHelper;
    /**
     * @var string
     */
    private readonly string $productType;

    /**
     * @param ProductCollectionFactory $productCollectionFactory
     * @param StockHelper $stockHelper
     * @param string $productType
     */
    public function __construct(
        ProductCollectionFactory $productCollectionFactory,
        StockHelper $stockHelper,
        string $productType = Type::DEFAULT_TYPE,
    ) {
        $this->productCollectionFactory = $productCollectionFactory;
        $this->stockHelper = $stockHelper;
        $this->productType = $productType;
    }

    /**
     * @param StoreInterface|null $store
     * @param int[]|null $entityIds
     *
     * @return ProductCollection
     */
    public function get(?StoreInterface $store = null, ?array $entityIds = []): ProductCollection
    {
        /**
         * Used collection over ProductRepository as it enables us to pass store id to the status and visibility
         * joins without having to setCurrentStore as is required via Repository::GetList.
         * Also enables us to return a generator, Repository::GetList loads the collection before returning it.
         */
        /** @var ProductCollection $collection */
        $collection = $this->productCollectionFactory->create();
        $collection->addAttributeToSelect('*');
        $collection->addAttributeToSelect(ProductInterface::STATUS);

        /** @var Store $store */
        $collection->addStoreFilter($store);
        $collection->addFieldToFilter('type_id', $this->productType);
        if ($entityIds) {
            $collection->addFieldToFilter(
                Entity::DEFAULT_ENTITY_ID_FIELD,
                ['in' => implode(',', array_filter($entityIds))],
            );
        }
        $this->stockHelper->addStockStatusToProducts($collection);

        return $collection;
    }
}
