<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Test\Integration\Service\Modifier\Catalog\Product\Collection\Column;

use Klevu\IndexingProducts\Service\Modifier\Catalog\Product\Collection\Column\ColumnCreationModifierInterface;
use Klevu\IndexingProducts\Service\Modifier\Catalog\Product\Collection\Column\StatusAttributeJoinColumnCreationModifier;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * @covers StatusAttributeJoinColumnCreationModifier::class
 * @method ColumnCreationModifierInterface instantiateTestObject(?array $arguments = null)
 * @method ColumnCreationModifierInterface instantiateTestObjectFromInterface(?array $arguments = null)
 */
class StatusAttributeJoinColumnCreationModifierTest extends TestCase
{
    use ObjectInstantiationTrait;
    use TestImplementsInterfaceTrait;

    /**
     * @var ObjectManagerInterface|null
     */
    private ?ObjectManagerInterface $objectManager = null; // @phpstan-ignore-line

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->implementationFqcn = StatusAttributeJoinColumnCreationModifier::class;
        $this->interfaceFqcn = ColumnCreationModifierInterface::class;
        $this->objectManager = Bootstrap::getObjectManager();
    }
}
