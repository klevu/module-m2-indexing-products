<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Service;

use Klevu\Indexing\Model\Update\Entity;
use Klevu\IndexingApi\Model\Update\EntityInterface;
use Klevu\IndexingApi\Model\Update\EntityInterfaceFactory as EntityUpdateInterfaceFactory;
use Klevu\IndexingApi\Service\EntityUpdateResponderServiceInterface;
use Klevu\IndexingProducts\Service\Provider\ProductEntityProvider;
use Magento\Bundle\Model\Product\Type as Bundle;
use Magento\Catalog\Model\Product\Type;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Downloadable\Model\Product\Type as Downloadable;
use Magento\Framework\Event\ManagerInterface;
use Magento\GroupedProduct\Model\Product\Type\Grouped;
use Psr\Log\LoggerInterface;

class EntityUpdateResponderService implements EntityUpdateResponderServiceInterface
{
    /**
     * @var ManagerInterface
     */
    private readonly ManagerInterface $eventManager;
    /**
     * @var EntityUpdateInterfaceFactory
     */
    private readonly EntityUpdateInterfaceFactory $entityUpdateFactory;
    /**
     * @var LoggerInterface
     */
    private readonly LoggerInterface $logger;

    /**
     * @param ManagerInterface $eventManager
     * @param EntityUpdateInterfaceFactory $entityUpdateFactory
     * @param LoggerInterface $logger
     */
    public function __construct(
        ManagerInterface $eventManager,
        EntityUpdateInterfaceFactory $entityUpdateFactory,
        LoggerInterface $logger,
    ) {
        $this->eventManager = $eventManager;
        $this->entityUpdateFactory = $entityUpdateFactory;
        $this->logger = $logger;
    }

    /**
     * @param mixed[] $data
     *
     * @return void
     */
    public function execute(array $data): void
    {
        if (empty($data[Entity::ENTITY_IDS]) && empty($data[Entity::ENTITY_SUBTYPES])) {
            $this->logger->debug(
                message: 'Method: {method}, Debug: {message}',
                context: [
                    'method' => __METHOD__,
                    'message' => 'No entity Ids or sub types provided for KLEVU_PRODUCT entity update.',
                ]);

            return;
        }

        try {
            $entityUpdate = $this->createEntityUpdate($data);
        } catch (\InvalidArgumentException $exception) {
            $this->logger->error(
                message: 'Method: {method}, Error: {message}',
                context: [
                    'method' => __METHOD__,
                    'message' => $exception->getMessage(),
                ],
            );

            return;
        }

        $this->eventManager->dispatch(
            'klevu_indexing_entity_update',
            [
                'entityUpdate' => $entityUpdate,
            ],
        );
    }

    /**
     * @param mixed[] $data
     *
     * @return EntityInterface
     * @throws \InvalidArgumentException
     */
    private function createEntityUpdate(array $data): EntityInterface
    {
        return $this->entityUpdateFactory->create([
            'data' => [
                Entity::ENTITY_TYPE => 'KLEVU_PRODUCT',
                Entity::ENTITY_IDS => $data[Entity::ENTITY_IDS] ?? [],
                Entity::STORE_IDS => $data[Entity::STORE_IDS] ?? [],
                Entity::CUSTOMER_GROUP_IDS => $data[Entity::CUSTOMER_GROUP_IDS] ?? [],
                Entity::ATTRIBUTES => $data[static::CHANGED_ATTRIBUTES] ?? [],
                Entity::ENTITY_SUBTYPES => $this->getSubtypes($data[Entity::ENTITY_SUBTYPES] ?? []),
            ],
        ]);
    }

    /**
     * @param string|string[] $subtypes
     *
     * @return string[]
     */
    private function getSubtypes(string|array $subtypes): array
    {
        $return = [];
        if (!is_array($subtypes)) {
            $subtypes = explode(',', $subtypes);
        }
        foreach ($subtypes as $subtype) {
            $return[] = match ($subtype) {
                Type::TYPE_SIMPLE => [
                    ProductEntityProvider::ENTITY_SUBTYPE_SIMPLE,
                ],
                Type::TYPE_VIRTUAL => [
                    ProductEntityProvider::ENTITY_SUBTYPE_VIRTUAL,
                ],
                Downloadable::TYPE_DOWNLOADABLE => [
                    ProductEntityProvider::ENTITY_SUBTYPE_DOWNLOADABLE,
                ],
                Bundle::TYPE_CODE => [
                    ProductEntityProvider::ENTITY_SUBTYPE_BUNDLE,
                ],
                Grouped::TYPE_CODE => [
                    ProductEntityProvider::ENTITY_SUBTYPE_GROUPED,
                ],
                Configurable::TYPE_CODE => [
                    ProductEntityProvider::ENTITY_SUBTYPE_CONFIGURABLE,
                ],
                ProductEntityProvider::ENTITY_SUBTYPE_CONFIGURABLE_VARIANTS => [
                    ProductEntityProvider::ENTITY_SUBTYPE_CONFIGURABLE_VARIANTS,
                ],
                default => [$subtype],
            };
        }

        return array_unique(
            array_merge(...$return),
        );
    }
}
