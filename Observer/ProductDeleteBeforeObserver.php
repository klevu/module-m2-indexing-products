<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Observer;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Framework\DataObject;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class ProductDeleteBeforeObserver implements ObserverInterface
{
    /**
     * @param Observer $observer
     *
     * @return void
     */
    public function execute(Observer $observer): void
    {
        $event = $observer->getEvent();
        $product = $event->getData('entity');
        if (
            !($product instanceof ProductInterface)
            || !($product instanceof DataObject)
        ) {
            return;
        }

        if ($product->getTypeId() !== Configurable::TYPE_CODE) {
            return;
        }

        $typeInstance = $product->getTypeInstance();
        if (!($typeInstance instanceof Configurable)) {
            return;
        }

        $childIds = $typeInstance->getChildrenIds(
            parentId: $product->getId(),
        );
        $product->setData(
            key: 'klevu_configurable_children_ids',
            value: array_filter(
                $childIds[0] ?: [],
            ),
        );
    }
}
