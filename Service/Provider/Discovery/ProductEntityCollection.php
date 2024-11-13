<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Service\Provider\Discovery;

use Klevu\IndexingApi\Service\Provider\Discovery\ProductEntityCollectionInterface;
use Klevu\IndexingProducts\Model\ResourceModel\Product\Collection as ProductCollection;
use Klevu\IndexingProducts\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\CatalogInventory\Helper\Stock as StockHelper;
use Magento\Eav\Model\Entity;
use Magento\Framework\DB\Select;
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
     * @var string|null
     */
    private readonly ?string $productType;

    /**
     * @param ProductCollectionFactory $productCollectionFactory
     * @param StockHelper $stockHelper
     * @param string|null $productType
     */
    public function __construct(
        ProductCollectionFactory $productCollectionFactory,
        StockHelper $stockHelper,
        ?string $productType = null,
    ) {
        $this->productCollectionFactory = $productCollectionFactory;
        $this->stockHelper = $stockHelper;
        $this->productType = $productType;
    }

    /**
     * @param StoreInterface|null $store
     * @param int[]|null $entityIds
     * @param int|null $pageSize
     * @param int $currentEntityId
     *
     * @return ProductCollection
     */
    public function get(
        ?StoreInterface $store = null,
        ?array $entityIds = [],
        ?int $pageSize = null,
        int $currentEntityId = 1,
    ): ProductCollection {
        /**
         * Used collection over ProductRepository as it enables us to pass store id to the status and visibility
         * joins without having to setCurrentStore as is required via Repository::GetList.
         * Also enables us to use a generator on the returned collection,
         * Repository::GetList loads the collection before returning it.
         */
        /** @var ProductCollection $collection */
        $collection = $this->productCollectionFactory->create();
        $collection->addAttributeToSelect('*');
        $collection->addAttributeToSelect(ProductInterface::STATUS);

        if ($store) {
            /** @var Store $store */
            $collection->addStoreFilter($store);
        }
        if ($entityIds) {
            $collection->addFieldToFilter(
                Entity::DEFAULT_ENTITY_ID_FIELD,
                ['in' => implode(',', array_filter($entityIds))],
            );
        }
        if (null !== $pageSize) {
            $collection->setPageSize($pageSize);
            $collection->addFieldToFilter(
                Entity::DEFAULT_ENTITY_ID_FIELD,
                ['gteq' => $currentEntityId],
            );
        }

        $collection->setOrder(Entity::DEFAULT_ENTITY_ID_FIELD, Select::SQL_ASC);
        if ($this->productType) {
            $collection->addFieldToFilter(ProductInterface::TYPE_ID, $this->productType);
        }
        /**
         * Would rather not use this deprecated method,
         * however MSI has a plugin on this method to set the right data
         * and this allows us to be compatible if MSI is removed from the codebase
         */
        $this->stockHelper->addStockStatusToProducts($collection);

        return $collection;
    }

    /**
     * @param string|null $entityType
     *
     * @return int
     */
    public function getLastId(?string $entityType = null): int
    {
        /** @var ProductCollection $collection */
        $collection = $this->productCollectionFactory->create();
        if ($entityType) {
            $collection->addAttributeToFilter(ProductInterface::TYPE_ID, ['eq' => $entityType]);
        }
        $collection->setOrder(Entity::DEFAULT_ENTITY_ID_FIELD, Select::SQL_DESC);
        $collection->setPageSize(1);
        $select = $collection->getSelect();
        $select->reset(part: Select::COLUMNS);
        $select->columns(Entity::DEFAULT_ENTITY_ID_FIELD);
        /** @var ProductInterface $item */
        $item = $collection->getFirstItem();

        return (int)$item->getId();
    }
}
