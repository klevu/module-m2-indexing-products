<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Test\Integration\Service\Provider;

use Klevu\IndexingProducts\Service\Provider\MinPriceProductProvider;
use Klevu\IndexingProducts\Service\Provider\MinPriceProductProviderInterface;
use Klevu\TestFixtures\Catalog\ProductTrait;
use Klevu\TestFixtures\Store\StoreFixturesPool;
use Klevu\TestFixtures\Store\StoreTrait;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Klevu\TestFixtures\Traits\TestInterfacePreferenceTrait;
use Magento\Framework\ObjectManagerInterface;
use Magento\GroupedProduct\Model\Product\Type\Grouped;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use TddWizard\Fixtures\Catalog\ProductFixturePool;

/**
 * @covers MinPriceProductProvider
 * @method MinPriceProductProviderInterface instantiateTestObject(?array $arguments = null)
 * @method MinPriceProductProviderInterface instantiateTestObjectFromInterface(?array $arguments = null)
 */
class MinPriceProductProviderTest extends TestCase
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

        $this->implementationFqcn = MinPriceProductProvider::class;
        $this->interfaceFqcn = MinPriceProductProviderInterface::class;
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

    public function testGet_ReturnsOriginalProduct_WhenTypeNotGrouped(): void
    {
        $this->createProduct([
            'price' => 99.99,
        ]);
        $productFixture = $this->productFixturePool->get('test_product');
        $product = $productFixture->getProduct();

        $provider = $this->instantiateTestObject();
        $result = $provider->get($productFixture->getProduct());

        $this->assertSame(expected: (int)$product->getId(), actual: (int)$result->getId());
        $this->assertSame(expected: $product->getPrice(), actual: $result->getPrice());
        // @phpstan-ignore-next-line
        $this->assertSame(expected: $product->getFinalPrice(), actual: $result->getFinalPrice());
    }

    /**
     * @magentoDbIsolation disabled
     */
    public function testGet_ReturnsLowestPriceSimple_ForGroupedProduct(): void
    {
        $this->createProduct([
            'key' => 'test_product_simple_1',
            'sku' => 'KLEVU-SIMPLE-SKU-001',
            'price' => 69.99,
            'data' => [
                'special_price' => 44.99,
                'special_price_from' => '1970-01-01',
                'special_price_to' => '2099-12-31',
            ],
        ]);
        $simpleProductFixture1 = $this->productFixturePool->get('test_product_simple_1');
        $simpleProduct1 = $simpleProductFixture1->getProduct();

        $this->createProduct([
            'key' => 'test_product_simple_2',
            'sku' => 'KLEVU-SIMPLE-SKU-002',
            'price' => 79.99,
            'data' => [
                'special_price' => 49.99,
                'special_price_from' => '1970-01-01',
                'special_price_to' => '2099-12-31',
            ],
        ]);
        $simpleProductFixture2 = $this->productFixturePool->get('test_product_simple_2');

        $this->createProduct([
            'type_id' => Grouped::TYPE_CODE,
            'sku' => 'KLEVU-GROUPED-SKU-001',
            'price' => 99.99,
            'linked_products' => [
                $simpleProductFixture1,
                $simpleProductFixture2,
            ],
            'data' => [
                'special_price' => 54.99,
                'special_price_from' => '1970-01-01',
                'special_price_to' => '2099-12-31',
            ],
        ]);
        $productFixture = $this->productFixturePool->get('test_product');

        $provider = $this->instantiateTestObject();
        $result = $provider->get($productFixture->getProduct());

        $this->assertSame(expected: (int)$simpleProduct1->getId(), actual: (int)$result->getId());

        $this->assertSame(expected: $simpleProduct1->getPrice(), actual: $result->getPrice());
        // @phpstan-ignore-next-line
        $this->assertSame(expected: $simpleProduct1->getFinalPrice(), actual: $result->getFinalPrice());
    }
}
