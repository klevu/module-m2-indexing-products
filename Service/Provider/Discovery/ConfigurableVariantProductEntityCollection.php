<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Service\Provider\Discovery;

use Klevu\IndexingApi\Service\Provider\Discovery\ProductEntityCollectionInterface;
use Klevu\IndexingProducts\Model\ResourceModel\Catalog\ConfigurableVariantProduct\Collection as ConfigurableProductCollection; // phpcs:ignore Generic.Files.LineLength.TooLong
use Klevu\IndexingProducts\Model\ResourceModel\Catalog\ConfigurableVariantProduct\CollectionFactory as ConfigurableProductCollectionFactory; // phpcs:ignore Generic.Files.LineLength.TooLong
use Magento\Catalog\Model\ResourceModel\Product\Collection as MagentoProductCollection;
use Magento\Eav\Model\Entity;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\DB\Select;
use Magento\Framework\DB\Sql\ExpressionFactory;
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
     * @var ExpressionFactory
     */
    private readonly ExpressionFactory $expressionFactory;

    /**
     * @param ConfigurableProductCollectionFactory $configurableProductCollectionFactory
     * @param LoggerInterface $logger
     * @param ExpressionFactory|null $expressionFactory
     */
    public function __construct(
        ConfigurableProductCollectionFactory $configurableProductCollectionFactory,
        LoggerInterface $logger,
        ?ExpressionFactory $expressionFactory = null,
    ) {
        $this->configurableProductCollectionFactory = $configurableProductCollectionFactory;
        $this->logger = $logger;
        $objectManager = ObjectManager::getInstance();
        $this->expressionFactory = $expressionFactory ?: $objectManager->get(ExpressionFactory::class);
    }

    /**
     * @param StoreInterface|null $store
     * @param int[]|null $entityIds
     * @param int|null $pageSize
     * @param int $currentEntityId
     *
     * @return MagentoProductCollection
     * @throws LocalizedException
     * @throws \Zend_Db_Select_Exception
     */
    public function get(
        ?StoreInterface $store = null,
        ?array $entityIds = [],
        ?int $pageSize = null,
        int $currentEntityId = 1,
    ): MagentoProductCollection {
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
            $collection->setPageSize(size: $pageSize);
        }
        $collection->setOrder(attribute: Entity::DEFAULT_ENTITY_ID_FIELD, dir: Select::SQL_ASC);
        $collection->getConfigurableCollection(store: $store);

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
        $collection->joinAssociatedProducts();
        $collection->setPageSize(size: 1);
        $collection->setOrder(attribute: Entity::DEFAULT_ENTITY_ID_FIELD, dir: Select::SQL_DESC);
        $select = $collection->getSelect();
        $select->reset(part: Select::COLUMNS);
        $select->columns(
            cols: $this->expressionFactory->create([
                'expression' => sprintf(
                    'MAX(%s.%s)',
                    MagentoProductCollection::MAIN_TABLE_ALIAS,
                    Entity::DEFAULT_ENTITY_ID_FIELD,
                ),
            ]),
        );

        $this->logger->debug(
            message: 'Configurable Variant getLastId Select: {method} : {select}',
            context: [
                'method' => __METHOD__,
                'line' => __LINE__,
                'select' => $collection->getSelect()->__toString(),
            ],
        );
        $connection = $collection->getConnection();
        $return = (int)$connection->fetchOne(sql: $select->__toString());
        $collection->clear();
        unset($collection);

        return $return;
    }
}
