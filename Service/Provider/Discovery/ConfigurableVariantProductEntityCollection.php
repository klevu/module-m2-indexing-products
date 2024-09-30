<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Service\Provider\Discovery;

use Klevu\IndexingApi\Service\Provider\Discovery\ProductEntityCollectionInterface;
use Klevu\IndexingProducts\Model\ResourceModel\Catalog\ConfigurableVariantProduct\Collection as ConfigurableProductCollection; // phpcs:ignore Generic.Files.LineLength.TooLong
use Klevu\IndexingProducts\Model\ResourceModel\Catalog\ConfigurableVariantProduct\CollectionFactory as ConfigurableProductCollectionFactory; // phpcs:ignore Generic.Files.LineLength.TooLong
use Magento\Catalog\Model\ResourceModel\Product\Collection as ProductCollection;
use Magento\Eav\Model\Entity;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
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
     *
     * @return ProductCollection
     * @throws LocalizedException
     * @throws NoSuchEntityException
     * @throws \Zend_Db_Select_Exception
     */
    public function get(?StoreInterface $store = null, ?array $entityIds = []): ProductCollection
    {
        /** @var ConfigurableProductCollection $collection */
        $collection = $this->configurableProductCollectionFactory->create();
        $collection->getConfigurableCollection(store: $store);
        if ($entityIds) {
            $collection->addFieldToFilter(
                Entity::DEFAULT_ENTITY_ID_FIELD,
                ['in' => implode(',', $entityIds)],
            );
        }

        $this->logger->debug(
            message: 'Configurable Variant Product Discovery Collection Select: {method} : {select}',
            context: [
                'method' => __METHOD__,
                'select' => $collection->getSelect()->__toString(),
            ],
        );

        return $collection;
    }
}
