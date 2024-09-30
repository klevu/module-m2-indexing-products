<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Service\Modifier\Catalog\Product\Collection\Column;

use Klevu\IndexingProducts\Model\ResourceModel\Catalog\ConfigurableVariantProduct\Collection;
use Klevu\IndexingProducts\Model\ResourceModel\Catalog\ProductCollectionTrait;
use Magento\Catalog\Api\Data\ProductAttributeInterface;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Framework\DB\Sql\ExpressionFactory;

class StatusAttributeJoinColumnCreationModifier implements ColumnCreationModifierInterface
{
    use ProductCollectionTrait;

    /**
     * @var ExpressionFactory
     */
    private readonly ExpressionFactory $expressionFactory;

    /**
     * @param ExpressionFactory $expressionFactory
     */
    public function __construct(ExpressionFactory $expressionFactory)
    {
        $this->expressionFactory = $expressionFactory;
    }

    /**
     *
     * @param Collection $collection
     * @param ProductAttributeInterface $attribute
     * @param string $columnName
     *
     * @return void
     */
    public function execute(
        Collection $collection,
        ProductAttributeInterface $attribute,
        string $columnName,
    ): void {
        $attributeCode = $attribute->getAttributeCode();
        $defaultAttributeTableAlias = $this->getAttributeTableAlias(
            attributeCode: $attributeCode,
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
        $select = $collection->getSelect();
        $select->columns(
            cols: [
                $columnName => $this->expressionFactory->create([
                    'expression' => implode(
                        separator: " ",
                        array: [
                            "CASE",
                            $ifProductDisabledReturnDisabled,
                            $elseIfParentProductDisabledReturnDisabled,
                            $elseReturnEnabled,
                            "END",
                        ],
                    ),
                ]),
            ],
        );
    }
}
