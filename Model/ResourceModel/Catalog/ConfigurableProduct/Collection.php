<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Model\ResourceModel\Catalog\ConfigurableProduct;

use Klevu\IndexingProducts\Model\ResourceModel\Catalog\ProductCollectionTrait;
use Magento\Catalog\Api\Data\ProductAttributeInterface;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\ResourceModel\Product\Collection as ProductCollection;
use Magento\Eav\Model\Entity;
use Magento\Framework\DataObject;
use Magento\Framework\DB\Select;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\Store;

// @TODO should we inject the original collection instead?
class Collection extends ProductCollection
{
    use ProductCollectionTrait;

    private const FIELD_PLACEHOLDER_COMBINED_STATUS = 'combined_status';
    private const FIELD_PLACEHOLDER_PARENT_VISIBILITY = 'parent_visibility';
    private const FIELD_PLACEHOLDER_PARENT_ID = 'parent_id';

    /**
     * @var string[]
     */
    private array $attributes;

    /**
     * @param StoreInterface|null $store
     *
     * @return Collection
     * @throws \Zend_Db_Select_Exception
     */
    public function getConfigurableCollection(?StoreInterface $store = null): Collection
    {
        $this->addAttributeToSelect('*');
        /** @var Store $store */
        $this->addStoreFilter($store);
        $this->joinAssociatedProducts();

        return $this;
    }

    /**
     * @param ProductAttributeInterface[] $attributes
     * @param StoreInterface|null $store
     *
     * @return void
     * @throws LocalizedException
     * @throws \Zend_Db_Select_Exception
     */
    public function joinProductParentAttributes(
        array $attributes,
        ?StoreInterface $store = null,
    ): void {
        array_walk(
            $attributes,
            fn (ProductAttributeInterface $attribute) => (
            $this->attributes[] = $attribute->getAttributeCode()
            )
        );

        foreach ($attributes as $attribute) {
            $this->joinMainProductAttribute($attribute, $store);
            $this->joinLinkedProductAttribute($attribute, $store);

            // @TODO extract these and inject via di
            switch ($attribute->getAttributeCode()) {
                case ProductInterface::STATUS:
                    $this->createCombinedStatusAttributeColumn($attribute);
                    break;
                case ProductInterface::VISIBILITY:
                    $this->createPatentAttributeColumn(
                        attribute: $attribute,
                        columnName: self::FIELD_PLACEHOLDER_PARENT_VISIBILITY,
                    );
                    break;
                default:
                    break;
            }
        }
    }

    /**
     * @param DataObject $item
     *
     * @return DataObject
     */
    protected function beforeAddLoadedItem(DataObject $item): DataObject
    {
        $this->setProductId($item);
        $this->setProductStatus($item);
        $this->setProductVisibility($item);

        return $item;
    }

    /**
     * @param DataObject $item
     *
     * @return void
     */
    private function setProductId(DataObject $item): void
    {
        $value = $item->getData(Entity::DEFAULT_ENTITY_ID_FIELD)
            . '-' . $item->getData(self::FIELD_PLACEHOLDER_PARENT_ID);
        $item->setData(
            key: Entity::DEFAULT_ENTITY_ID_FIELD,
            value: $value,
        );
    }

    /**
     * @param DataObject $item
     *
     * @return void
     */
    private function setProductStatus(DataObject $item): void
    {
        if (!in_array(ProductInterface::STATUS, $this->attributes, true)) {
            return;
        }
        $item->setData(
            key: ProductInterface::STATUS,
            value: $item->getData(key: self::FIELD_PLACEHOLDER_COMBINED_STATUS),
        );
        $item->unsetData(key: self::FIELD_PLACEHOLDER_COMBINED_STATUS);
    }

    /**
     * @param DataObject $item
     *
     * @return void
     */
    private function setProductVisibility(DataObject $item): void
    {
        if (!in_array(ProductInterface::VISIBILITY, $this->attributes, true)) {
            return;
        }
        $item->setData(
            key: ProductInterface::VISIBILITY,
            value: $item->getData(key: self::FIELD_PLACEHOLDER_PARENT_VISIBILITY),
        );
        $item->unsetData(key: self::FIELD_PLACEHOLDER_PARENT_VISIBILITY);
    }

    /**
     * @return void
     * @throws \Zend_Db_Select_Exception
     */
    private function joinAssociatedProducts(): void
    {
        $select = $this->getSelect();
        $from = $select->getPart(part: Select::FROM);
        if (array_key_exists(key: $this->tableAliasAssociatedProduct, array: $from)) {
            return;
        }
        $select->joinInner(
            name: [$this->tableAliasAssociatedProduct => $this->getTable(table: 'catalog_product_super_link')],
            cond: implode(
                ' ' . Select::SQL_AND . ' ',
                [
                    // note in adobe commerce parent_id is linked to row_id, but product_id is linked to entity_id
                    $this->tableAliasAssociatedProduct . '.product_id = e.' . Entity::DEFAULT_ENTITY_ID_FIELD,
                ],
            ),
            cols: [],
        );
        $select->joinInner(
            name: ['parent_entity' => $this->getTable('catalog_product_entity')],
            cond: implode(
                ' ' . Select::SQL_AND . ' ',
                [
                    // phpcs:ignore Generic.Files.LineLength.TooLong
                    'parent_entity.' . $this->getLinkField() . ' = ' . $this->tableAliasAssociatedProduct . '.parent_id',
                ],
            ),
            cols: [self::FIELD_PLACEHOLDER_PARENT_ID => Entity::DEFAULT_ENTITY_ID_FIELD],
        );
    }

    /**
     * @param ProductAttributeInterface $attribute
     * @param StoreInterface|null $store
     *
     * @return void
     * @throws LocalizedException
     */
    private function joinLinkedProductAttribute(
        ProductAttributeInterface $attribute,
        ?StoreInterface $store = null,
    ): void {
        $connection = $this->getConnection();
        $linkField = $this->getLinkField();
        $attributeCode = $attribute->getAttributeCode();
        $attributeId = $attribute->getAttributeId();

        $entityIdentifier = $this->getEntityTableIdentifier($attribute);

        $defaultTableAlias = $this->getAttributeTableAlias(
            attributeCode: $attributeCode,
            isLinked: true,
            isDefault: true,
        );
        $select = $this->getSelect();
        $select->joinInner(
            name: [$defaultTableAlias => $this->getTable(table: $entityIdentifier)],
            cond: implode(
                ' ' . Select::SQL_AND . ' ',
                [
                    $this->tableAliasAssociatedProduct . '.parent_id = ' . $defaultTableAlias . '.' . $linkField,
                    $connection->quoteInto(text: $defaultTableAlias . '.attribute_id = ?', value: $attributeId),
                    $defaultTableAlias . '.store_id = 0',
                ],
            ),
            cols: [],
        );

        $tableAlias = $this->getAttributeTableAlias(attributeCode: $attributeCode, isLinked: true);
        $select->joinLeft(
            name: [$tableAlias => $this->getTable(table: $entityIdentifier)],
            cond: implode(
                ' ' . Select::SQL_AND . ' ',
                [
                    $this->tableAliasAssociatedProduct . '.parent_id = ' . $tableAlias . '.' . $linkField,
                    $connection->quoteInto(text: $tableAlias . '.attribute_id = ?', value: $attributeId),
                    $connection->quoteInto(
                        text: $tableAlias . '.store_id = ?',
                        value: $store
                            ? $store->getId()
                            : $this->getStoreId(),
                    ),
                ],
            ),
            cols: [],
        );
    }

    /**
     * @param ProductAttributeInterface $attribute
     *
     * @return void
     */
    private function createCombinedStatusAttributeColumn(
        ProductAttributeInterface $attribute,
    ): void {
        $attributeCode = $attribute->getAttributeCode();
        $defaultAttributeTableAlias = $this->getAttributeTableAlias(
            attributeCode: $attribute->getAttributeCode(),
            isDefault: true,
        );
        $attributeTableAlias = $this->getAttributeTableAlias(
            attributeCode: $attributeCode,
        );
        $defaultLinkedTableAlias = $this->getAttributeTableAlias(
            attributeCode: $attributeCode,
            isLinked: true,
            isDefault: true,
        );
        $linkedTableAlias = $this->getAttributeTableAlias(
            attributeCode: $attributeCode,
            isLinked: true,
        );

        $ifProductDisabledReturnDisabled = sprintf(
            'WHEN IF(%s.value_id > 0, %s.value, %s.value) = %s THEN %s',
            $attributeTableAlias,
            $attributeTableAlias,
            $defaultAttributeTableAlias,
            Status::STATUS_DISABLED,
            Status::STATUS_DISABLED,
        );
        $elseIfParentProductDisabledReturnDisabled = sprintf(
            'WHEN IF(%s.value_id > 0, %s.value, %s.value) = %s THEN %s',
            $linkedTableAlias,
            $linkedTableAlias,
            $defaultLinkedTableAlias,
            Status::STATUS_DISABLED,
            Status::STATUS_DISABLED,
        );
        $elseReturnEnabled = sprintf(
            'ELSE %s',
            Status::STATUS_ENABLED,
        );
        $this->getSelect()->columns(
            cols: [
                self::FIELD_PLACEHOLDER_COMBINED_STATUS => new \Zend_Db_Expr(
                    implode(
                        separator: " ",
                        array: [
                            "CASE",
                            $ifProductDisabledReturnDisabled,
                            $elseIfParentProductDisabledReturnDisabled,
                            $elseReturnEnabled,
                            "END",
                        ],
                    ),
                ),
            ],
        );
    }

    /**
     * @param ProductAttributeInterface $attribute
     * @param string $columnName
     *
     * @return void
     */
    private function createPatentAttributeColumn(ProductAttributeInterface $attribute, string $columnName): void
    {
        $attributeCode = $attribute->getAttributeCode();

        $defaultLinkedTableAlias = $this->getAttributeTableAlias(
            attributeCode: $attributeCode,
            isLinked: true,
            isDefault: true,
        );
        $linkedTableAlias = $this->getAttributeTableAlias(
            attributeCode: $attributeCode,
            isLinked: true,
        );
        $this->getSelect()->columns(
            cols: [
                $columnName => new \Zend_Db_Expr(
                    sprintf(
                        'IF(%s.value_id > 0, %s.value, %s.value)',
                        $linkedTableAlias,
                        $linkedTableAlias,
                        $defaultLinkedTableAlias,
                    ),
                ),
            ],
        );
    }
}
