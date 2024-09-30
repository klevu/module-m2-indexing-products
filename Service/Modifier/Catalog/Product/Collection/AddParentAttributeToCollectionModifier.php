<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Service\Modifier\Catalog\Product\Collection;

use Klevu\IndexingProducts\Model\ResourceModel\Catalog\ConfigurableVariantProduct\Collection;
use Klevu\IndexingProducts\Model\ResourceModel\Catalog\ProductCollectionTrait;
use Klevu\IndexingProducts\Service\Modifier\Catalog\Product\Collection\Column\ColumnCreationModifierInterface;
use Magento\Catalog\Api\Data\ProductAttributeInterface;
use Magento\Catalog\Api\ProductAttributeRepositoryInterface;
use Magento\Framework\DataObject;
use Magento\Framework\DB\Select;
use Magento\Framework\DB\Sql\ExpressionFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Api\Data\StoreInterface;

class AddParentAttributeToCollectionModifier implements AddParentAttributeToCollectionModifierInterface
{
    use ProductCollectionTrait;

    public const DEFAULT_PARENT_COLUMN_PREFIX = 'parent_';

    /**
     * @var ProductAttributeRepositoryInterface
     */
    private readonly ProductAttributeRepositoryInterface $productAttributeRepository;
    /**
     * @var ExpressionFactory
     */
    private readonly ExpressionFactory $expressionFactory;
    /**
     * @var string
     */
    private readonly string $attributeCode;
    /**
     * @var string
     */
    private readonly string $columnName;
    /**
     * @var ColumnCreationModifierInterface|null
     */
    private ?ColumnCreationModifierInterface $columnCreationModifier;
    /**
     * @var string[]
     */
    private array $entityTableIds = [];

    /**
     * @param ProductAttributeRepositoryInterface $productAttributeRepository
     * @param ExpressionFactory $expressionFactory
     * @param string $attributeCode
     * @param string|null $columnName
     * @param ColumnCreationModifierInterface|null $columnCreationModifier
     */
    public function __construct(
        ProductAttributeRepositoryInterface $productAttributeRepository,
        ExpressionFactory $expressionFactory,
        string $attributeCode,
        ?string $columnName = null,
        ?ColumnCreationModifierInterface $columnCreationModifier = null,
    ) {
        $this->productAttributeRepository = $productAttributeRepository;
        $this->attributeCode = $attributeCode;
        $this->columnName = $columnName ?: self::DEFAULT_PARENT_COLUMN_PREFIX . $attributeCode;
        $this->expressionFactory = $expressionFactory;
        $this->columnCreationModifier = $columnCreationModifier;
    }

    /**
     * @return string
     */
    public function getAttributeCode(): string
    {
        return $this->attributeCode;
    }

    /**
     * @param Collection $collection
     * @param StoreInterface|null $store
     *
     * @return void
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function createParentAttributeColumn(
        Collection $collection,
        ?StoreInterface $store = null,
    ): void {
        $attribute = $this->productAttributeRepository->get(
            attributeCode: $this->getAttributeCode(),
        );
        $this->joinMainProductAttribute(collection: $collection, attribute: $attribute, store: $store);
        $this->joinLinkedProductAttribute(collection: $collection, attribute: $attribute, store: $store);

        $this->columnCreationModifier
            ? $this->columnCreationModifier->execute(
                collection: $collection,
                attribute: $attribute,
                columnName: $this->columnName,
            )
            : $this->createParentColumn(
                collection: $collection,
                attribute: $attribute,
            );
    }

    /**
     * @param DataObject $item
     *
     * @return void
     */
    public function setProductAttributeValue(DataObject $item): void
    {
        $item->setData(
            key: $this->attributeCode,
            value: $item->getData(key: $this->columnName),
        );
        $item->unsetData(key: $this->columnName);
    }

    /**
     * @param Collection $collection
     * @param ProductAttributeInterface $attribute
     * @param StoreInterface|null $store
     *
     * @return void
     * @throws LocalizedException
     */
    private function joinMainProductAttribute(
        Collection $collection,
        ProductAttributeInterface $attribute,
        ?StoreInterface $store = null,
    ): void {
        $connection = $collection->getConnection();
        $linkField = $this->getLinkField(collection: $collection);
        $attributeCode = $attribute->getAttributeCode();
        $attributeId = $attribute->getAttributeId();

        $entityTableIdentifier = $this->getEntityTableIdentifier($attribute);

        $defaultTableAlias = $this->getAttributeTableAlias(attributeCode: $attributeCode, isDefault: true);
        $select = $collection->getSelect();
        $select->joinInner(
            name: [$defaultTableAlias => $collection->getTable(table: $entityTableIdentifier)],
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
            [$tableAlias => $collection->getTable(table: $entityTableIdentifier)],
            implode(
                ' AND ',
                [
                    $tableAlias . '.' . $linkField . ' = e.' . $linkField,
                    $connection->quoteInto(text: $tableAlias . '.attribute_id = ?', value: $attributeId),
                    $connection->quoteInto(
                        text: $tableAlias . '.store_id = ?',
                        value: $store
                            ? $store->getId()
                            : $collection->getStoreId(),
                    ),
                ],
            ),
            [],
        );
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

    /**
     * @param Collection $collection
     * @param ProductAttributeInterface $attribute
     * @param StoreInterface|null $store
     *
     * @return void
     * @throws LocalizedException
     */
    private function joinLinkedProductAttribute(
        Collection $collection,
        ProductAttributeInterface $attribute,
        ?StoreInterface $store = null,
    ): void {
        $connection = $collection->getConnection();
        $linkField = $this->getLinkField(collection: $collection);
        $attributeCode = $attribute->getAttributeCode();
        $attributeId = $attribute->getAttributeId();

        $entityTableIdentifier = $this->getEntityTableIdentifier($attribute);

        $defaultTableAlias = $this->getAttributeTableAlias(
            attributeCode: $attributeCode,
            isLinked: true,
            isDefault: true,
        );
        $select = $collection->getSelect();
        $select->joinInner(
            name: [$defaultTableAlias => $collection->getTable(table: $entityTableIdentifier)],
            cond: implode(
                ' ' . Select::SQL_AND . ' ',
                [
                    // phpcs:ignore Generic.Files.LineLength.TooLong
                    Collection::TABLE_ALIAS_ASSOCIATED_PRODUCT . '.parent_id = ' . $defaultTableAlias . '.' . $linkField,
                    $connection->quoteInto(text: $defaultTableAlias . '.attribute_id = ?', value: $attributeId),
                    $defaultTableAlias . '.store_id = 0',
                ],
            ),
            cols: [],
        );

        $tableAlias = $this->getAttributeTableAlias(attributeCode: $attributeCode, isLinked: true);
        $select->joinLeft(
            name: [$tableAlias => $collection->getTable(table: $entityTableIdentifier)],
            cond: implode(
                ' ' . Select::SQL_AND . ' ',
                [
                    Collection::TABLE_ALIAS_ASSOCIATED_PRODUCT . '.parent_id = ' . $tableAlias . '.' . $linkField,
                    $connection->quoteInto(text: $tableAlias . '.attribute_id = ?', value: $attributeId),
                    $connection->quoteInto(
                        text: $tableAlias . '.store_id = ?',
                        value: $store
                            ? $store->getId()
                            : $collection->getStoreId(),
                    ),
                ],
            ),
            cols: [],
        );
    }

    /**
     * @param Collection $collection
     * @param ProductAttributeInterface $attribute
     *
     * @return void
     */
    private function createParentColumn(
        Collection $collection,
        ProductAttributeInterface $attribute,
    ): void {
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
        $select = $collection->getSelect();
        $select->columns(
            cols: [
                $this->columnName => $this->expressionFactory->create([
                    'expression' => sprintf(
                        'IF(%s.value_id > 0, %s.value, %s.value)',
                        $linkedTableAlias,
                        $linkedTableAlias,
                        $defaultLinkedTableAlias,
                    ),
                ]),
            ],
        );
    }
}
