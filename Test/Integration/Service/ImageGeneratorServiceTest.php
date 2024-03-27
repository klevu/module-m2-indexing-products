<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Test\Integration\Service;

use Klevu\IndexingApi\Service\ImageGeneratorServiceInterface;
use Klevu\IndexingProducts\Service\ImageGeneratorService;
use Klevu\TestFixtures\Catalog\ProductTrait;
use Klevu\TestFixtures\Store\StoreFixturesPool;
use Klevu\TestFixtures\Store\StoreTrait;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Klevu\TestFixtures\Traits\TestInterfacePreferenceTrait;
use Magento\Catalog\Model\Product;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use TddWizard\Fixtures\Catalog\ProductFixturePool;

/**
 * @covers ImageGeneratorService
 * @method ImageGeneratorServiceInterface instantiateTestObject(?array $arguments = null)
 * @method ImageGeneratorServiceInterface instantiateTestObjectFromInterface(?array $arguments = null)
 */
class ImageGeneratorServiceTest extends TestCase
{
    use ObjectInstantiationTrait;
    use ProductTrait;
    use StoreTrait;
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

        $this->implementationFqcn = ImageGeneratorService::class;
        $this->interfaceFqcn = ImageGeneratorServiceInterface::class;
        $this->objectManager = Bootstrap::getObjectManager();
        $this->storeFixturesPool = $this->objectManager->get(StoreFixturesPool::class);
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
        $this->storeFixturesPool->rollback();
    }

    /**
     * @magentoDbIsolation disabled
     */
    public function testExecute_ReturnsResizedImageRelativePath(): void
    {
        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');

        $this->createProduct([
            'images' => [
                'image' => 'klevu_test_image_name.jpg',
            ],
        ]);
        $productFixture = $this->productFixturePool->get('test_product');
        /** @var Product $product */
        $product = $productFixture->getProduct();

        $service = $this->instantiateTestObject();
        $result1 = $service->execute(
            imagePath: $product->getImage(),
            imageType: 'image',
            width: 456,
            height: null,
            storeId: (int)$storeFixture->getId(),
        );

        $this->assertMatchesRegularExpression(
            pattern: '#^catalog/product/cache/.*/k/l/klevu_test_image_name(_.*)?.jpg#',
            string: $result1,
        );

        $result2 = $service->execute(
            imagePath: $product->getImage(),
            imageType: 'image',
            width: 456,
            height: 123,
            storeId: (int)$storeFixture->getId(),
        );

        $this->assertMatchesRegularExpression(
            pattern: '#^catalog/product/cache/.*/k/l/klevu_test_image_name(_.*)?.jpg#',
            string: $result2,
        );

        $this->assertNotSame($result1, $result2);
    }
}
