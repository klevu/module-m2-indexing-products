<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Service\Action;

use Klevu\IndexingProducts\Model\ResourceModel\Product\Collection as ProductCollection;
use Magento\CatalogInventory\Model\ResourceModel\Stock\Status as StockStatusResourceModel;
use Magento\CatalogInventory\Model\ResourceModel\Stock\StatusFactory as StockStatusResourceModelFactory;

class JoinStockToCollectionAction implements JoinStockToCollectionActionInterface
{
    public const STOCK_ADDED_TO_COLLECTION = 'has_stock_status_filter';

    /**
     * @var StockStatusResourceModelFactory
     */
    private readonly StockStatusResourceModelFactory $stockStatusResourceModelFactory;
    /**
     * @var StockStatusResourceModel
     */
    private StockStatusResourceModel $stockStatusResource;

    /**
     * @param StockStatusResourceModelFactory $stockStatusResourceModelFactory
     */
    public function __construct(StockStatusResourceModelFactory $stockStatusResourceModelFactory)
    {
        $this->stockStatusResourceModelFactory = $stockStatusResourceModelFactory;
    }

    /**
     * @param ProductCollection $collection
     *
     * @return void
     */
    public function execute(ProductCollection $collection): void
    {
        if (!$collection->getFlag(static::STOCK_ADDED_TO_COLLECTION)) {
            $resourceModel = $this->getStockStatusResource();
            /**
             * MSI has a plugin on this method to set the correct data
             * and this allows us to remain compatible if MSI is removed from the codebase
             */
            $resourceModel->addStockDataToCollection(
                collection: $collection,
                isFilterInStock: false,
            );
            $collection->setFlag(static::STOCK_ADDED_TO_COLLECTION, true);
        }
    }

    /**
     * @return StockStatusResourceModel
     */
    private function getStockStatusResource(): StockStatusResourceModel
    {
        if (empty($this->stockStatusResource)) {
            $this->stockStatusResource = $this->stockStatusResourceModelFactory->create();
        }

        return $this->stockStatusResource;
    }
}
