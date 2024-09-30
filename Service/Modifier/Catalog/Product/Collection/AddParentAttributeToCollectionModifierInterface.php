<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Service\Modifier\Catalog\Product\Collection;

use Klevu\IndexingProducts\Model\ResourceModel\Catalog\ConfigurableVariantProduct\Collection;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Api\Data\StoreInterface;

interface AddParentAttributeToCollectionModifierInterface
{
    /**
     * @return string
     */
    public function getAttributeCode(): string;

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
    ): void;

    /**
     * @param DataObject $item
     *
     * @return void
     */
    public function setProductAttributeValue(DataObject $item): void;
}
