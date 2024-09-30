<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Plugin\ConfigurableProduct\Model;

use Klevu\Indexing\Model\Update\Entity;
use Klevu\IndexingApi\Service\EntityUpdateResponderServiceInterface;
use Klevu\IndexingProducts\Model\ResourceModel\Product\Collection as ProductCollection;
use Klevu\IndexingProducts\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\ConfigurableProduct\Model\LinkManagement;
use Magento\Eav\Model\Entity as EavEntity;

class LinkManagementPlugin
{
    /**
     * @var EntityUpdateResponderServiceInterface
     */
    private readonly EntityUpdateResponderServiceInterface $responderService;
    /**
     * @var ProductCollectionFactory
     */
    private readonly ProductCollectionFactory $productCollectionFactory;

    /**
     * @param EntityUpdateResponderServiceInterface $responderService
     * @param ProductCollectionFactory $productCollectionFactory
     */
    public function __construct(
        EntityUpdateResponderServiceInterface $responderService,
        ProductCollectionFactory $productCollectionFactory,
    ) {
        $this->responderService = $responderService;
        $this->productCollectionFactory = $productCollectionFactory;
    }

    /**
     * @param LinkManagement $subject
     * @param bool $result
     * @param string $sku
     * @param string $childSku
     *
     * @return bool
     */
    public function afterAddChild(
        // phpcs:ignore SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter
        LinkManagement $subject,
        bool $result,
        // phpcs:ignore SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter
        string $sku,
        string $childSku,
    ): bool {
        $this->responderService->execute([
            Entity::ENTITY_IDS => $this->getEntityIds($childSku),
        ]);

        return $result;
    }

    /**
     * @param LinkManagement $subject
     * @param bool $result
     * @param string $sku
     * @param string $childSku
     *
     * @return bool
     */
    public function afterRemoveChild(
        // phpcs:ignore SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter
        LinkManagement $subject,
        bool $result,
        // phpcs:ignore SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter
        string $sku,
        string $childSku,
    ): bool {
        $this->responderService->execute([
            Entity::ENTITY_IDS => $this->getEntityIds($childSku),
        ]);

        return $result;
    }

    /**
     * @param string $childSku
     *
     * @return int[]
     */
    private function getEntityIds(string $childSku): array
    {
        /** @var ProductCollection $productCollection */
        $productCollection = $this->productCollectionFactory->create();
        $productCollection->addAttributeToFilter(
            attribute: ProductInterface::SKU,
            condition: ['eq' => $childSku],
        );
        $productCollection->addAttributeToSelect(attribute: EavEntity::DEFAULT_ENTITY_ID_FIELD);
        /** @var ProductInterface[] $products */
        $products = $productCollection->getItems();

        return array_map(
            callback: static fn (ProductInterface $item): int => (int)$item->getId(),
            array: $products,
        );
    }
}
