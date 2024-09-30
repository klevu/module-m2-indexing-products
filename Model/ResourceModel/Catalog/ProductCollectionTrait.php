<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Model\ResourceModel\Catalog;

use Klevu\IndexingProducts\Model\ResourceModel\Catalog\ConfigurableVariantProduct\Collection;
use Magento\Eav\Model\Entity;
use Magento\Framework\Exception\LocalizedException;

trait ProductCollectionTrait
{
    /**
     * @var string|null
     */
    private ?string $linkField = null;

    /**
     * @param Collection $collection
     *
     * @return string
     * @throws LocalizedException
     */
    private function getLinkField(Collection $collection): string
    {
        if (null === $this->linkField) {
            $entity = $collection->getEntity();
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
            ? Collection::TABLE_ALIAS_ASSOCIATED_PRODUCT
            : 'at';
        $return .= '_' . $attributeCode;
        if ($isDefault) {
            $return .= '_default';
        }

        return $return;
    }
}
