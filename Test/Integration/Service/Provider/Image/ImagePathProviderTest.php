<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Test\Integration\Service\Provider\Image;

use Klevu\IndexingApi\Service\Provider\Image\PathProviderInterface;
use Klevu\IndexingProducts\Service\Provider\Image\PathProvider;
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
 * @covers PathProvider
 * @method PathProviderInterface instantiateTestObject(?array $arguments = null)
 * @method PathProviderInterface instantiateTestObjectFromInterface(?array $arguments = null)
 */
class ImagePathProviderTest extends TestCase
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

        $this->implementationFqcn = PathProvider::class;
        $this->interfaceFqcn = PathProviderInterface::class;
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

    public function testGet_ReturnsAssetPath(): void
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
            imageParams: [
                'type' => 'image',
                'width' => 123,
                'height' => null,
            ],
            filePath: $product->getImage(),
        );

        $this->assertMatchesRegularExpression(
            pattern: '#catalog/product/cache/.*/k/l/klevu_test_image_name(_.*)?.jpg#',
            string: $result,
        );
    }
}
