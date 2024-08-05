<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Service;

use Klevu\Indexing\Exception\InvalidIndexingRecordDataTypeException;
use Klevu\Indexing\Model\EntityIndexingRecordFactory;
use Klevu\IndexingApi\Model\EntityIndexingRecordInterface;
use Klevu\IndexingApi\Model\Source\Actions;
use Klevu\IndexingApi\Service\EntityIndexingRecordCreatorServiceInterface;
use Magento\Cms\Api\Data\PageInterface;
use Magento\Framework\Api\ExtensibleDataInterface;

class EntityIndexingRecordCreatorService implements EntityIndexingRecordCreatorServiceInterface
{
    /**
     * @var EntityIndexingRecordFactory
     */
    private readonly EntityIndexingRecordFactory $entityIndexingRecordFactory;

    /**
     * @param EntityIndexingRecordFactory $entityIndexingRecordFactory
     */
    public function __construct(EntityIndexingRecordFactory $entityIndexingRecordFactory)
    {
        $this->entityIndexingRecordFactory = $entityIndexingRecordFactory;
    }

    /**
     *
     * @param int $recordId
     * @param Actions $action
     * @param ExtensibleDataInterface|PageInterface $entity
     * @param ExtensibleDataInterface|PageInterface|null $parent
     *
     * @return EntityIndexingRecordInterface
     */
    public function execute(
        int $recordId,
        Actions $action,
        PageInterface | ExtensibleDataInterface $entity,
        PageInterface | ExtensibleDataInterface | null $parent = null,
    ): EntityIndexingRecordInterface {
        if (!($entity instanceof ExtensibleDataInterface)) {
            throw new InvalidIndexingRecordDataTypeException(
                sprintf(
                    '"entity" provided to %s, must be instance of %s',
                    self::class,
                    ExtensibleDataInterface::class,
                ),
            );
        }
        if ($parent instanceof PageInterface) {
            throw new InvalidIndexingRecordDataTypeException(
                sprintf(
                    '"parent" provided to %s, must be instance of %s or null',
                    self::class,
                    ExtensibleDataInterface::class,
                ),
            );
        }

        return $this->entityIndexingRecordFactory->create([
            'recordId' => $recordId,
            'action' => $action,
            'entity' => $entity,
            'parent' => $parent,
        ]);
    }
}
