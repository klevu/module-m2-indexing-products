<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Observer;

use Klevu\Indexing\Model\Update\Entity;
use Klevu\IndexingApi\Service\EntityUpdateResponderServiceInterface;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class ProductDeleteObserver implements ObserverInterface
{
    /**
     * @var EntityUpdateResponderServiceInterface
     */
    private readonly EntityUpdateResponderServiceInterface $responderService;

    /**
     * @param EntityUpdateResponderServiceInterface $responderService
     */
    public function __construct(EntityUpdateResponderServiceInterface $responderService)
    {
        $this->responderService = $responderService;
    }

    /**
     * @param Observer $observer
     *
     * @return void
     */
    public function execute(Observer $observer): void
    {
        $event = $observer->getEvent();
        $product = $event->getData('product');
        if (!($product instanceof ProductInterface)) {
            return;
        }

        $this->responderService->execute([
            Entity::ENTITY_IDS => $this->getEntityIdsForResponderService($product),
        ]);
    }

    /**
     * @param ProductInterface $product
     *
     * @return int[]
     */
    private function getEntityIdsForResponderService(ProductInterface $product): array
    {
        $return = [
            $product->getId(),
        ];

        if ($product->getTypeId() === Configurable::TYPE_CODE) {
            $return = array_merge(
                $return,
                (array)$product->getData('klevu_configurable_children_ids'),
            );
        }

        return array_map(
            callback: 'intval',
            array: array_unique($return),
        );
    }
}
