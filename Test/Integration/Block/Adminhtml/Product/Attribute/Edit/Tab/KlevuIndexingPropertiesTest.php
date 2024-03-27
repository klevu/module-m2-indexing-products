<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Block\Adminhtml\Product\Attribute\Edit\Tab;

use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\View\Element\BlockInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Klevu\IndexingProducts\Block\Adminhtml\Product\Attribute\Edit\Tab\KlevuIndexingProperties::class
 * @method KlevuIndexingProperties instantiateTestObject(?array $arguments = null)
 */
class KlevuIndexingPropertiesTest extends TestCase
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

        $this->implementationFqcn = KlevuIndexingProperties::class;
        $this->interfaceFqcn = BlockInterface::class;
        $this->expectPlugins = true;
        $this->objectManager = Bootstrap::getObjectManager();
    }
}
