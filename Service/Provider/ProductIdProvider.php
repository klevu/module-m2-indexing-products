<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Service\Provider;

use Klevu\IndexingApi\Service\Provider\ProductIdProviderInterface;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\ResourceModel\Product as ProductResourceModel;
use Magento\Eav\Model\Entity;
use Magento\Framework\EntityManager\EntityMetadataInterface;
use Magento\Framework\EntityManager\MetadataPool;

class ProductIdProvider implements ProductIdProviderInterface
{
    /**
     * @var ProductResourceModel
     */
    private readonly ProductResourceModel $productResourceModel;
    /**
     * @var EntityMetadataInterface
     */
    private EntityMetadataInterface $productMetadata;

    /**
     * @param ProductResourceModel $productResourceModel
     * @param MetadataPool $metadataPool
     *
     * @throws \Exception
     */
    public function __construct(
        ProductResourceModel $productResourceModel,
        MetadataPool $metadataPool,
    ) {
        $this->productResourceModel = $productResourceModel;
        $this->productMetadata = $metadataPool->getMetadata(ProductInterface::class);
    }

    /**
     * @param string $sku
     *
     * @return int|null
     */
    public function getBySku(string $sku): ?int
    {
        $productsIds = $this->getBySkus([$sku]);

        return ($productsIds[$sku] ?? null)
            ? (int)$productsIds[$sku]
            : null;
    }

    /**
     * @param string[] $skus
     *
     * @return array<string, int>
     */
    public function getBySkus(array $skus): array
    {
        /** @var array<string, mixed> $productIdsBySku */
        $productIdsBySku = $this->productResourceModel->getProductsIdsBySkus($skus);

        return array_map(
            callback: 'intval',
            array: $productIdsBySku,
        );
    }

    /**
     * Method to convert row_id to entity_id in Adobe Commerce.
     * Has no effect in Magento Open Source
     *
     * @param int[] $linkFieldIds
     *
     * @return int[]
     */
    public function getByLinkFields(array $linkFieldIds): array
    {
        if ($this->productMetadata->getLinkField() === Entity::DEFAULT_ENTITY_ID_FIELD) {
            // Magento Open Source, we already have entity_ids
            return $linkFieldIds;
        }
        // Adobe Commerce, convert row_id to entity_id
        $connection = $this->productResourceModel->getConnection();
        $select = $connection->select();
        $select->from(
            name: $this->productResourceModel->getTable('catalog_product_entity'),
            cols: [Entity::DEFAULT_ENTITY_ID_FIELD],
        );
        $select->where('row_id IN (?)', $linkFieldIds);

        return array_map(
            callback: 'intval',
            array: $connection->fetchCol($select),
        );
    }
}
