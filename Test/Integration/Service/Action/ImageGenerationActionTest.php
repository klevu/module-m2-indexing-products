<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Test\Integration\Service\Action;

use Klevu\IndexingApi\Service\Action\ImageGenerationActionInterface;
use Klevu\IndexingApi\Service\Provider\Image\FrameworkImageProviderInterface;
use Klevu\IndexingApi\Service\Provider\Image\IsDbStorageUsedProviderInterface;
use Klevu\IndexingProducts\Service\Action\ImageGenerationAction;
use Klevu\TestFixtures\Catalog\ProductTrait;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Klevu\TestFixtures\Traits\TestInterfacePreferenceTrait;
use Magento\Catalog\Model\Product;
use Magento\Framework\App\Filesystem\DirectoryList as AppDirectoryList;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Directory\WriteInterface;
use Magento\Framework\Filesystem\DirectoryList;
use Magento\Framework\Filesystem\Io\File as FileIo;
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
     * @var DirectoryList|null
     */
    private ?DirectoryList $directoryList = null;
    /**
     * @var FileIo|null
     */
    private ?FileIo $fileIo = null;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->implementationFqcn = ImageGenerationAction::class;
        $this->interfaceFqcn = ImageGenerationActionInterface::class;
        $this->objectManager = Bootstrap::getObjectManager();
        $this->directoryList = $this->objectManager->get(DirectoryList::class);
        $this->fileIo = $this->objectManager->get(FileIo::class);
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
            ->with(AppDirectoryList::MEDIA)
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
            ->with(AppDirectoryList::MEDIA)
            ->willReturn($mockMediaDirectory);

        $action = $this->instantiateTestObject([
            'frameworkImageProvider' => $mockImageProvider,
            'filesystem' => $mockFilesystem,
        ]);
        $action->execute(imageParams: [], imagePath: $product->getImage());
    }

    public function testExecute_ReturnsString_WhenDbStorageNotUsed_AndFileExists(): void
    {
        $this->createProduct([
            'images' => [
                'image' => 'klevu_test_image_name.jpg',
            ],
        ]);
        $productFixture = $this->productFixturePool->get('test_product');
        /** @var Product $product */
        $product = $productFixture->getProduct();

        $mockIsDbStorageUsedImageProvider = $this->getMockBuilder(IsDbStorageUsedProviderInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockIsDbStorageUsedImageProvider->method('get')
            ->willReturn(false);

        $action = $this->instantiateTestObject([
            'isDbStorageUsedProvider' => $mockIsDbStorageUsedImageProvider,
        ]);
        $return = $action->execute(imageParams: [], imagePath: $product->getImage());

        $this->assertMatchesRegularExpression(
            pattern: '#^catalog/product/cache/[a-f0-9]+/k/l/klevu_test_image_name(_\d+)*\.jpg$#',
            string: $return,
        );
    }

    public function testExecute_ReturnsNull_WhenDbStorageNotUsed_AndFileDoesNotExist(): void
    {
        $this->createProduct([
            'images' => [
                'image' => 'klevu_test_image_name.jpg',
            ],
        ]);
        $productFixture = $this->productFixturePool->get('test_product');
        /** @var Product $product */
        $product = $productFixture->getProduct();
        $product->setData('image', 'klevu_test_image_name--not-exists.jpg');

        $mockIsDbStorageUsedImageProvider = $this->getMockBuilder(IsDbStorageUsedProviderInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockIsDbStorageUsedImageProvider->method('get')
            ->willReturn(false);

        $action = $this->instantiateTestObject([
            'isDbStorageUsedProvider' => $mockIsDbStorageUsedImageProvider,
        ]);
        $return = $action->execute(imageParams: [], imagePath: $product->getImage());

        $this->assertNull(actual: $return);
    }

    public function testExecute_ReturnsString_WhenDbStorageUsed_AndFileExists(): void
    {
        $this->createProduct([
            'images' => [
                'image' => 'klevu_test_image_name.jpg',
            ],
        ]);
        $productFixture = $this->productFixturePool->get('test_product');
        /** @var Product $product */
        $product = $productFixture->getProduct();

        $mockIsDbStorageUsedImageProvider = $this->getMockBuilder(IsDbStorageUsedProviderInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockIsDbStorageUsedImageProvider->method('get')
            ->willReturn(true);

        $action = $this->instantiateTestObject([
            'isDbStorageUsedProvider' => $mockIsDbStorageUsedImageProvider,
        ]);
        $return = $action->execute(imageParams: [], imagePath: $product->getImage());

        $this->assertMatchesRegularExpression(
            pattern: '#^catalog/product/cache/[a-f0-9]+/k/l/klevu_test_image_name(_\d+)*\.jpg$#',
            string: $return,
        );
    }

    public function testExecute_ReturnsNull_WhenDbStorageUsed_AndFileDoesNotExist(): void
    {
        $this->createProduct([
            'images' => [
                'image' => 'klevu_test_image_name.jpg',
            ],
        ]);
        $productFixture = $this->productFixturePool->get('test_product');
        /** @var Product $product */
        $product = $productFixture->getProduct();
        $product->setData('image', 'klevu_test_image_name--not-exists.jpg');

        $mockIsDbStorageUsedImageProvider = $this->getMockBuilder(IsDbStorageUsedProviderInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockIsDbStorageUsedImageProvider->method('get')
            ->willReturn(true);

        $action = $this->instantiateTestObject([
            'isDbStorageUsedProvider' => $mockIsDbStorageUsedImageProvider,
        ]);
        $return = $action->execute(imageParams: [], imagePath: $product->getImage());

        $this->assertNull(actual: $return);
    }

    /**
     * Ref: KS-22988
     */
    public function testExecute_ReturnsNull_WhenDbStorageUsed_AndFileExists_AndFileIsEmpty(): void
    {
        $this->createProduct([
            'images' => [
                'image' => 'klevu_test_image_name.jpg',
            ],
        ]);
        $productFixture = $this->productFixturePool->get('test_product');
        /** @var Product $product */
        $product = $productFixture->getProduct();

        $this->fileIo->write(
            filename: $this->directoryList->getPath('media')
                . '/catalog/product'
                . $product->getImage(),
            src: '',
        );

        $mockIsDbStorageUsedImageProvider = $this->getMockBuilder(IsDbStorageUsedProviderInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockIsDbStorageUsedImageProvider->method('get')
            ->willReturn(true);

        $action = $this->instantiateTestObject([
            'isDbStorageUsedProvider' => $mockIsDbStorageUsedImageProvider,
        ]);
        $return = $action->execute(imageParams: [], imagePath: $product->getImage());

        $this->assertNull(actual: $return);
    }

    /**
     * Ref: KS-22988
     * @group wip
     */
    public function testExecute_ReturnsNull_WhenDbStorageNotUsed_AndFileExists_AndFileIsEmpty(): void
    {
        $this->createProduct([
            'images' => [
                'image' => 'klevu_test_image_symbol.jpg',
            ],
        ]);
        $productFixture = $this->productFixturePool->get('test_product');
        /** @var Product $product */
        $product = $productFixture->getProduct();

        $this->fileIo->write(
            filename: $this->directoryList->getPath('media')
                . '/catalog/product'
                . $product->getImage(),
            src: '',
        );

        $mockIsDbStorageUsedImageProvider = $this->getMockBuilder(IsDbStorageUsedProviderInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockIsDbStorageUsedImageProvider->method('get')
            ->willReturn(false);

        $action = $this->instantiateTestObject([
            'isDbStorageUsedProvider' => $mockIsDbStorageUsedImageProvider,
        ]);
        $return = $action->execute(imageParams: [], imagePath: $product->getImage());

        $this->assertNull(actual: $return);
    }
}
