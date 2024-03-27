<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Test\Integration\Service\Provider\Image;

use Klevu\IndexingApi\Service\Provider\Image\FrameworkImageProviderInterface;
use Klevu\IndexingProducts\Service\Provider\Image\FrameworkImageProvider;
use Klevu\TestFixtures\Catalog\ProductTrait;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Klevu\TestFixtures\Traits\TestInterfacePreferenceTrait;
use Magento\Catalog\Model\Product;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use TddWizard\Fixtures\Catalog\ProductFixturePool;

/**
 * @covers FrameworkImageProvider
 * @method FrameworkImageProviderInterface instantiateTestObject(?array $arguments = null)
 * @method FrameworkImageProviderInterface instantiateTestObjectFromInterface(?array $arguments = null)
 */
class FrameworkImageProviderTest extends TestCase
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

        $this->implementationFqcn = FrameworkImageProvider::class;
        $this->interfaceFqcn = FrameworkImageProviderInterface::class;
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

    public function testGet_ReturnsImage(): void
    {
        $this->createProduct([
            'images' => [
                'image' => 'klevu_test_image_name.jpg',
            ],
        ]);
        $productFixture = $this->productFixturePool->get('test_product');
        /** @var Product $product */
        $product = $productFixture->getProduct();

        $provider = $this->instantiateTestObject();
        $result = $provider->get(
            imagePath: $product->getImage(),
            imageParams: [],
        );

        $this->assertSame(expected: 2, actual: $result->getImageType(), message: 'getImageType');
        $this->assertSame(expected: 'image/jpeg', actual: $result->getMimeType(), message: 'getMimeType');
    }
}
