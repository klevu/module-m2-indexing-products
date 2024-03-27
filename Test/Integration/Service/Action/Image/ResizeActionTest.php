<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Test\Integration\Service\Action\Image;

use Klevu\IndexingApi\Service\Action\Image\ResizeActionInterface;
use Klevu\IndexingProducts\Service\Action\Image\ResizeAction;
use Klevu\TestFixtures\Catalog\ProductTrait;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Klevu\TestFixtures\Traits\TestInterfacePreferenceTrait;
use Magento\Framework\Image;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use TddWizard\Fixtures\Catalog\ProductFixturePool;

/**
 * @covers ResizeAction
 * @method ResizeActionInterface instantiateTestObject(?array $arguments = null)
 * @method ResizeActionInterface instantiateTestObjectFromInterface(?array $arguments = null)
 */
class ResizeActionTest extends TestCase
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

        $this->implementationFqcn = ResizeAction::class;
        $this->interfaceFqcn = ResizeActionInterface::class;
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

    public function testExecute_DoesNothing_WhenWidthIsMissingFromParams(): void
    {
        $mockImage = $this->getMockBuilder(Image::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockImage->expects($this->never())
            ->method('resize');

        $action = $this->instantiateTestObject();
        $action->execute($mockImage, []);
    }

    public function testExecute_DoesNothing_WhenWidthIsNull(): void
    {
        $mockImage = $this->getMockBuilder(Image::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockImage->expects($this->never())
            ->method('resize');

        $action = $this->instantiateTestObject();
        $action->execute($mockImage, [
            'image_width' => null,
        ]);
    }

    /**
     * @testWith ["some string"]
     *           [true]
     *           ["1234.e"]
     */
    public function testExecute_logsError_WhenImageWidthNotNumeric(mixed $invalidWidth): void
    {
        $mockImage = $this->getMockBuilder(Image::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockImage->expects($this->never())
            ->method('resize');

        $mockLogger = $this->getMockBuilder(LoggerInterface::class)
            ->getMock();
        $mockLogger->expects($this->once())
            ->method('error')
            ->with(
                'Method: {method}, Error: {message}',
                [
                    'method' => 'Klevu\IndexingProducts\Service\Action\Image\ResizeAction::validateParams',
                    'message' => sprintf(
                        'image_width must be a positive integer, received %s',
                        get_debug_type($invalidWidth),
                    ),
                ],
            );

        $action = $this->instantiateTestObject([
            'logger' => $mockLogger,
        ]);
        $action->execute($mockImage, [
            'image_width' => $invalidWidth,
        ]);
    }
}
