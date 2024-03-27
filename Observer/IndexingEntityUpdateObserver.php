<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Observer;

use Klevu\IndexingApi\Model\Update\EntityInterface as EntityUpdateInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class IndexingEntityUpdateObserver implements ObserverInterface
{
    /**
     * @param Observer $observer
     *
     * @return void
     */
    public function execute(Observer $observer): void
    {
        $event = $observer->getEvent();
        /** @var EntityUpdateInterface $entityUpdate */
        $entityUpdate = $event->getData('entityUpdate');
        $updatedIds = $event->getData('updatedIds');

        if (
            !$updatedIds
            || !($entityUpdate instanceof EntityUpdateInterface)
            || 'KLEVU_PRODUCT' !== $entityUpdate->getEntityType()
        ) {
            return;
        }
        // remove any entities that were not an update (deletion or set to be indexable, which requires a full sync)
        $entityUpdate->setEntityIds(
            entityIds: array_map('intval', $updatedIds),
        );
        // @TODO entityUpdateHandler to map attributes to aspects and save to aspect table
        // inject \Klevu\IndexingProducts\Service\EntityAspectMapperService into entityUpdateHandler
    }
}
