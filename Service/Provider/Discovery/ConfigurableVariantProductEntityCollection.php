<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Service\Provider\Discovery;

use Klevu\IndexingApi\Service\Provider\Discovery\ProductEntityCollectionInterface;
use Klevu\IndexingProducts\Model\ResourceModel\Catalog\ConfigurableVariantProduct\Collection as ConfigurableProductCollection; // phpcs:ignore Generic.Files.LineLength.TooLong
use Klevu\IndexingProducts\Model\ResourceModel\Catalog\ConfigurableVariantProduct\CollectionFactory as ConfigurableProductCollectionFactory; // phpcs:ignore Generic.Files.LineLength.TooLong
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\ResourceModel\Product\Collection as ProductCollection;
use Magento\Eav\Model\Entity;
use Magento\Framework\DB\Select;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Api\Data\StoreInterface;
use Psr\Log\LoggerInterface;

class ConfigurableVariantProductEntityCollection implements ProductEntityCollectionInterface
{
    /**
     * @var ConfigurableProductCollectionFactory
     */
    private readonly ConfigurableProductCollectionFactory $configurableProductCollectionFactory;
    /**
     * @var LoggerInterface
     */
    private readonly LoggerInterface $logger;

    /**
     * @param ConfigurableProductCollectionFactory $configurableProductCollectionFactory
     * @param LoggerInterface $logger
     */
    public function __construct(
        ConfigurableProductCollectionFactory $configurableProductCollectionFactory,
        LoggerInterface $logger,
    ) {
        $this->configurableProductCollectionFactory = $configurableProductCollectionFactory;
        $this->logger = $logger;
    }

    /**
     * @param StoreInterface|null $store
     * @param int[]|null $entityIds
     * @param int $pageSize
     * @param int $currentEntityId
     *
     * @return ProductCollection
     * @throws LocalizedException
     * @throws \Zend_Db_Select_Exception
     */
    public function get(
        ?StoreInterface $store = null,
        ?array $entityIds = [],
        ?int $pageSize = null,
        int $currentEntityId = 1,
    ): ProductCollection {
        /** @var ConfigurableProductCollection $collection */
        $collection = $this->configurableProductCollectionFactory->create();
        if ($entityIds) {
            $collection->addFieldToFilter(
                Entity::DEFAULT_ENTITY_ID_FIELD,
                ['in' => implode(',', $entityIds)],
            );
        }
        if (null !== $pageSize) {
            $collection->addFieldToFilter(
                Entity::DEFAULT_ENTITY_ID_FIELD,
                ['gteq' => $currentEntityId],
            );
            $collection->setPageSize($pageSize);
        }
        $collection->setOrder(Entity::DEFAULT_ENTITY_ID_FIELD, Select::SQL_ASC);
        $collection->getConfigurableCollection(store: $store);

        $this->logger->debug(
            message: 'Configurable Variant Product Discovery Collection Select: {method} : {select}',
            context: [
                'method' => __METHOD__,
                'select' => $collection->getSelect()->__toString(),
            ],
        );

        return $collection;
    }

    /**
     * @param string|null $entityType
     *
     * @return int
     */
    public function getLastId(
        // phpcs:ignore SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter
        ?string $entityType = null,
    ): int {
        /** @var ConfigurableProductCollection $collection */
        $collection = $this->configurableProductCollectionFactory->create();
        $collection->setPageSize(size: 1);
        $collection->setOrder(Entity::DEFAULT_ENTITY_ID_FIELD, Select::SQL_DESC);
        $select = $collection->getSelect();
        $select->reset(part: Select::COLUMNS);
        $select->columns(Entity::DEFAULT_ENTITY_ID_FIELD);
        /** @var ProductInterface $item */
        $item = $collection->getFirstItem();

        return (int)$item->getId();
    }
}
