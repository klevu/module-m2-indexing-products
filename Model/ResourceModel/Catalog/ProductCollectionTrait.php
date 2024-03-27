<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Model\ResourceModel\Catalog;

use Magento\Catalog\Api\Data\ProductAttributeInterface;
use Magento\Eav\Model\Entity;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Api\Data\StoreInterface;

trait ProductCollectionTrait
{
    /**
     * @var string
     */
    private string $tableAliasAssociatedProduct = 'associated_product';
    /**
     * @var string|null
     */
    private ?string $linkField = null;
    /**
     * @var string[]
     */
    private array $entityTableIds = [];

    /**
     * @param ProductAttributeInterface $attribute
     * @param StoreInterface|null $store
     *
     * @return void
     * @throws LocalizedException
     */
    private function joinMainProductAttribute(
        ProductAttributeInterface $attribute,
        ?StoreInterface $store = null,
    ): void {
        $connection = $this->getConnection();
        $linkField = $this->getLinkField();
        $attributeCode = $attribute->getAttributeCode();
        $attributeId = $attribute->getAttributeId();

        $entityIdentifier = $this->getEntityTableIdentifier($attribute);

        $defaultTableAlias = $this->getAttributeTableAlias(attributeCode: $attributeCode, isDefault: true);
        $select = $this->getSelect();
        $select->joinInner(
            name: [$defaultTableAlias => $this->getTable(table: $entityIdentifier)],
            cond: implode(
                ' AND ',
                [
                    $defaultTableAlias . '.' . $linkField . ' = e.' . $linkField,
                    $connection->quoteInto(text: $defaultTableAlias . '.attribute_id = ?', value: $attributeId),
                    $defaultTableAlias . '.store_id = 0',
                ],
            ),
            cols: [],
        );

        $tableAlias = $this->getAttributeTableAlias(attributeCode: $attributeCode);
        $select->joinLeft(
            [$tableAlias => $this->getTable(table: $entityIdentifier)],
            implode(
                ' AND ',
                [
                    $tableAlias . '.' . $linkField . ' = e.' . $linkField,
                    $connection->quoteInto(text: $tableAlias . '.attribute_id = ?', value: $attributeId),
                    $connection->quoteInto(
                        text: $tableAlias . '.store_id = ?',
                        value: $store ? $store->getId() : $this->getStoreId(),
                    ),
                ],
            ),
            [],
        );
    }

    /**
     *
     * @return string
     * @throws LocalizedException
     */
    private function getLinkField(): string
    {
        if (null === $this->linkField) {
            $entity = $this->getEntity();
            $this->linkField = $entity->getLinkField();
        }

        return $this->linkField
            ?: Entity::DEFAULT_ENTITY_ID_FIELD;
    }

    /**
     * @param string $attributeCode
     * @param bool $isLinked
     * @param bool $isDefault
     *
     * @return string
     */
    private function getAttributeTableAlias(
        string $attributeCode,
        bool $isLinked = false,
        bool $isDefault = false,
    ): string {
        $return = $isLinked
            ? $this->tableAliasAssociatedProduct
            : 'at';
        $return .= '_' . $attributeCode;
        if ($isDefault) {
            $return .= '_default';
        }

        return $return;
    }

    /**
     * @param ProductAttributeInterface $attribute
     *
     * @return string
     */
    private function getEntityTableIdentifier(ProductAttributeInterface $attribute): string
    {
        $attributeType = $attribute->getBackendType();
        if (!($this->entityTableIds[$attributeType] ?? null)) {
            $entityTypeCode = ProductAttributeInterface::ENTITY_TYPE_CODE;
            $this->entityTableIds[$attributeType] = $entityTypeCode . '_entity_' . $attributeType;
        }

        return $this->entityTableIds[$attributeType];
    }
}
