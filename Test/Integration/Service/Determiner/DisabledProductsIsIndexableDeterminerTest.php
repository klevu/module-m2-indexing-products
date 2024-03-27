<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Test\Integration\Service\Determiner;

use Klevu\Configuration\Service\Provider\ScopeProviderInterface;
use Klevu\IndexingApi\Service\Determiner\IsIndexableDeterminerInterface;
use Klevu\IndexingProducts\Service\Determiner\DisabledProductsIsIndexableDeterminer;
use Klevu\TestFixtures\Catalog\ProductTrait;
use Klevu\TestFixtures\Store\StoreFixturesPool;
use Klevu\TestFixtures\Store\StoreTrait;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use TddWizard\Fixtures\Catalog\ProductFixturePool;

/**
 * @covers \Klevu\IndexingProducts\Service\Determiner\DisabledProductsIsIndexableDeterminer::class
 * @method IsIndexableDeterminerInterface instantiateTestObject(?array $arguments = null)
 * @method IsIndexableDeterminerInterface instantiateTestObjectFromInterface(?array $arguments = null)
 */
class DisabledProductsIsIndexableDeterminerTest extends TestCase
{
    use ObjectInstantiationTrait;
    use ProductTrait;
    use StoreTrait;
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

        $this->implementationFqcn = DisabledProductsIsIndexableDeterminer::class;
        $this->interfaceFqcn = IsIndexableDeterminerInterface::class;
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
     * @magentoConfigFixture default/klevu/indexing/exclude_disabled_products 0
     */
    public function testExecute_ReturnsTrue_WhenConfigDisabled(): void
    {
        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');
        $this->createProduct();
        $productFixture = $this->productFixturePool->get('test_product');

        $determiner = $this->instantiateTestObject();
        $this->assertTrue(
            condition: $determiner->execute(
                entity: $productFixture->getProduct(),
                store: $storeFixture->get(),
            ),
            message: 'is indexable',
        );
    }

    /**
     * @magentoDbIsolation disabled
     * @magentoConfigFixture default/klevu/indexing/exclude_disabled_products 1
     */
    public function testExecute_ReturnsTrue_WhenConfigEnabled_EntityEnabled(): void
    {
        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');
        $this->createProduct();
        $productFixture = $this->productFixturePool->get('test_product');

        $determiner = $this->instantiateTestObject();
        $this->assertTrue(
            condition: $determiner->execute(
                entity: $productFixture->getProduct(),
                store: $storeFixture->get(),
            ),
            message: 'is indexable',
        );
    }

    /**
     * @magentoDbIsolation disabled
     * @magentoConfigFixture default/klevu/indexing/exclude_disabled_products 1
     */
    public function testExecute_ReturnsFalse_WhenConfigEnabled_EntityDisabled(): void
    {
        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');
        $scopeProvider = $this->objectManager->get(ScopeProviderInterface::class);
        $scopeProvider->setCurrentScope($storeFixture->get());

        $this->createProduct(
            productData: [
                'status' => Status::STATUS_DISABLED,
                'stores' => [
                    $storeFixture->getId() => [
                        'status' => Status::STATUS_DISABLED,
                    ],
                ],
            ],
        );
        $productFixture = $this->productFixturePool->get('test_product');

        $mockLogger = $this->getMockBuilder(LoggerInterface::class)
            ->getMock();
        $mockLogger->expects($this->once())
            ->method('debug')
            ->with(
                'Store ID: {storeId} Product ID: {productId} not indexable due to Status: {status} in {method}',
                [
                    'storeId' => (string)$storeFixture->getId(),
                    'productId' => (string)$productFixture->getId(),
                    'status' => $productFixture->getProduct()->getStatus(),
                    // phpcs:ignore Generic.Files.LineLength.TooLong
                    'method' => 'Klevu\IndexingProducts\Service\Determiner\DisabledProductsIsIndexableDeterminer::isIndexable',
                ],
            );

        $determiner = $this->instantiateTestObject([
            'logger' => $mockLogger,
        ]);
        $this->assertFalse(
            condition: $determiner->execute(
                entity: $productFixture->getProduct(),
                store: $storeFixture->get(),
            ),
            message: 'is indexable',
        );
    }

    public function testExecute_ThrowsInvalidArgumentException(): void
    {
        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');

        $invalidEntity = $this->objectManager->create(Category::class);
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            sprintf(
                'Invalid argument provided for "$entity". Expected %s, received %s.',
                ProductInterface::class,
                get_debug_type($invalidEntity),
            ),
        );

        $service = $this->instantiateTestObject();
        $service->execute($invalidEntity, $storeFixture->get());
    }
}
