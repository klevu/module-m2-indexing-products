<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Service\Provider\CatalogRule;

use Klevu\IndexingApi\Service\Provider\CatalogRule\CatalogRuleProductIdsProviderInterface;
use Magento\Framework\App\ResourceConnection;

class CatalogRuleProductIdsProvider implements CatalogRuleProductIdsProviderInterface
{
    /**
     * @var ResourceConnection
     */
    private readonly ResourceConnection $resourceConnection;

    /**
     * @param ResourceConnection $resourceConnection
     */
    public function __construct(ResourceConnection $resourceConnection)
    {
        $this->resourceConnection = $resourceConnection;
    }

    /**
     * @return int[]
     */
    public function get(): array
    {
        $connection = $this->resourceConnection->getConnection();
        $select = $connection->select();
        $select->from(
            name: $this->resourceConnection->getTableName(modelEntity: 'catalogrule_product'),
            cols: 'product_id',
        );
        $select->distinct();

        return array_map(
            callback: static fn (mixed $id): int => ((int)$id),
            array: $connection->fetchCol(sql: $select),
        );
    }
}
