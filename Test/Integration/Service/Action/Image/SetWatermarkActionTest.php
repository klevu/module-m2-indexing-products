<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Test\Integration\Service\Action\Image;

use Klevu\IndexingApi\Service\Action\Image\SetWatermarkActionInterface;
use Klevu\IndexingProducts\Service\Action\Image\SetWatermarkAction;
use Klevu\TestFixtures\Catalog\ProductTrait;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Klevu\TestFixtures\Traits\TestInterfacePreferenceTrait;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use TddWizard\Fixtures\Catalog\ProductFixturePool;

/**
 * @covers SetWatermarkAction
 * @method SetWatermarkActionInterface instantiateTestObject(?array $arguments = null)
 * @method SetWatermarkActionInterface instantiateTestObjectFromInterface(?array $arguments = null)
 */
class SetWatermarkActionTest extends TestCase
{
    use ObjectInstantiationTrait;
    use ProductTrait;
    use TestImplementsInterfaceTrait;
    use TestInterfacePreferenceTrait;

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

        $this->implementationFqcn = SetWatermarkAction::class;
        $this->interfaceFqcn = SetWatermarkActionInterface::class;
        $this->objectManager = Bootstrap::getObjectManager();
        $this->productFixturePool = $this->objectManager->get(ProductFixturePool::class);
    }

    /**
     * @return void
     * @throws \Exception
     */
    protected function tearDown(): void
    {
        parent::tearDown();

        $this->productFixturePool->rollback();
    }
}
