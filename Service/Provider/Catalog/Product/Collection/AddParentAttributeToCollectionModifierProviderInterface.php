<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Service\Provider\Catalog\Product\Collection;

use Klevu\IndexingProducts\Service\Modifier\Catalog\Product\Collection\AddParentAttributeToCollectionModifierInterface;

interface AddParentAttributeToCollectionModifierProviderInterface
{
    /**
     * @return AddParentAttributeToCollectionModifierInterface[]
     */
    public function get(): array;
}
