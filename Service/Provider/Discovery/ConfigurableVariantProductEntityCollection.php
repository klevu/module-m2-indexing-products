<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Service\Provider\Discovery;

use Klevu\IndexingApi\Service\Provider\Discovery\ProductEntityCollectionInterface;
use Klevu\IndexingProducts\Model\ResourceModel\Catalog\ConfigurableProduct\Collection as ConfigurableProductCollection;
// phpcs:ignore Generic.Files.LineLength.TooLong
use Klevu\IndexingProducts\Model\ResourceModel\Catalog\ConfigurableProduct\CollectionFactory as ConfigurableProductCollectionFactory;
use Magento\Catalog\Api\Data\ProductAttributeInterface;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductAttributeRepositoryInterface;
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
     * @var ProductAttributeRepositoryInterface
     */
    private ProductAttributeRepositoryInterface $productAttributeRepository;
    /**
     * @var LoggerInterface
     */
    private readonly LoggerInterface $logger;

    /**
     * @param ConfigurableProductCollectionFactory $configurableProductCollectionFactory
     * @param ProductAttributeRepositoryInterface $productAttributeRepository
     * @param LoggerInterface $logger
     */
    public function __construct(
        ConfigurableProductCollectionFactory $configurableProductCollectionFactory,
        ProductAttributeRepositoryInterface $productAttributeRepository,
        LoggerInterface $logger,
    ) {
        $this->configurableProductCollectionFactory = $configurableProductCollectionFactory;
        $this->productAttributeRepository = $productAttributeRepository;
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
        $collection->getConfigurableCollection(
            store: $store,
        );
        $collection->joinProductParentAttributes(
            attributes: $this->getParentAttributesToJoin(),
            store: $store,
        );
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

    /**
     * @return ProductAttributeInterface[]
     * @throws NoSuchEntityException
     */
    private function getParentAttributesToJoin(): array
    {
        $statusAttribute = $this->productAttributeRepository->get(
            attributeCode: ProductInterface::STATUS,
        );

        return [$statusAttribute];
    }
}
