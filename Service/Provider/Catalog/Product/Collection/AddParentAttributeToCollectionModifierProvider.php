<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Service\Provider\Catalog\Product\Collection;

use Klevu\IndexingProducts\Service\Modifier\Catalog\Product\Collection\AddParentAttributeToCollectionModifierInterface;

class AddParentAttributeToCollectionModifierProvider implements AddParentAttributeToCollectionModifierProviderInterface
{
    /**
     * @var AddParentAttributeToCollectionModifierInterface[]
     */
    private array $modifiers = [];

    /**
     * @param AddParentAttributeToCollectionModifierInterface[] $modifiers
     */
    public function __construct(array $modifiers = [])
    {
        array_walk($modifiers, [$this, 'addCollectionModifier']);
    }

    /**
     * @return AddParentAttributeToCollectionModifierInterface[]
     */
    public function get(): array
    {
        return $this->modifiers;
    }

    /**
     * @param AddParentAttributeToCollectionModifierInterface $modifier
     * @param string $modifierName
     *
     * @return void
     */
    private function addCollectionModifier(
        AddParentAttributeToCollectionModifierInterface $modifier,
        string $modifierName,
    ): void {
        $this->modifiers[$modifierName] = $modifier;
    }
}
