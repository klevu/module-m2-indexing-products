<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Test\Integration\Service\Action;

use Klevu\IndexingApi\Service\Action\ImageGenerationActionInterface;
use Klevu\IndexingApi\Service\Provider\Image\FrameworkImageProviderInterface;
use Klevu\IndexingProducts\Service\Action\ImageGenerationAction;
use Klevu\TestFixtures\Catalog\ProductTrait;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Klevu\TestFixtures\Traits\TestInterfacePreferenceTrait;
use Magento\Catalog\Model\Product;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Directory\WriteInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use TddWizard\Fixtures\Catalog\ProductFixturePool;

/**
 * @covers ImageGenerationAction
 * @method ImageGenerationActionInterface instantiateTestObject(?array $arguments = null)
 * @method ImageGenerationActionInterface instantiateTestObjectFromInterface(?array $arguments = null)
 */
class ImageGenerationActionTest extends TestCase
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

        $this->implementationFqcn = ImageGenerationAction::class;
        $this->interfaceFqcn = ImageGenerationActionInterface::class;
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

    public function testExecute_RegeneratesImage_WhenMissing(): void
    {
        $this->createProduct([
            'images' => [
                'image' => 'klevu_test_image_name.jpg',
            ],
        ]);
        $productFixture = $this->productFixturePool->get('test_product');
        /** @var Product $product */
        $product = $productFixture->getProduct();

        $mockImageProvider = $this->getMockBuilder(FrameworkImageProviderInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockImageProvider->expects($this->once())
            ->method('get');

        $mockMediaDirectory = $this->getMockBuilder(WriteInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockMediaDirectory->expects($this->once())
            ->method('isFile')
            ->willReturn(true);
        $mockMediaDirectory->expects($this->once())
            ->method('isExist')
            ->willReturn(false);
        $mockMediaDirectory->expects($this->once())
            ->method('getRelativePath')
            ->willReturn('catalog/product/cache/1234567890abcde/k/l/klevu_test_image_name.jpg');

        $mockFilesystem = $this->getMockBuilder(Filesystem::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockFilesystem->expects($this->once())
            ->method('getDirectoryWrite')
            ->with(DirectoryList::MEDIA)
            ->willReturn($mockMediaDirectory);

        $action = $this->instantiateTestObject([
            'frameworkImageProvider' => $mockImageProvider,
            'filesystem' => $mockFilesystem,
        ]);
        $action->execute(imageParams: [], imagePath: $product->getImage());
    }

    public function testExecute_SkipsImageRegeneration_WhenImageExists(): void
    {
        $this->createProduct([
            'images' => [
                'image' => 'klevu_test_image_name.jpg',
            ],
        ]);
        $productFixture = $this->productFixturePool->get('test_product');
        /** @var Product $product */
        $product = $productFixture->getProduct();

        $mockImageProvider = $this->getMockBuilder(FrameworkImageProviderInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockImageProvider->expects($this->never())
            ->method('get');

        $mockMediaDirectory = $this->getMockBuilder(WriteInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockMediaDirectory->expects($this->once())
            ->method('isFile')
            ->willReturn(true);
        $mockMediaDirectory->expects($this->once())
            ->method('isExist')
            ->willReturn(true);
        $mockMediaDirectory->expects($this->once())
            ->method('getRelativePath')
            ->willReturn('catalog/product/cache/1234567890abcde/k/l/klevu_test_image_name.jpg');

        $mockFilesystem = $this->getMockBuilder(Filesystem::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockFilesystem->expects($this->once())
            ->method('getDirectoryWrite')
            ->with(DirectoryList::MEDIA)
            ->willReturn($mockMediaDirectory);

        $action = $this->instantiateTestObject([
            'frameworkImageProvider' => $mockImageProvider,
            'filesystem' => $mockFilesystem,
        ]);
        $action->execute(imageParams: [], imagePath: $product->getImage());
    }
}
