<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Test\Integration\Service;

use Klevu\Indexing\Exception\InvalidIndexingRecordDataTypeException;
use Klevu\IndexingApi\Service\EntityIndexingRecordCreatorServiceInterface;
use Klevu\IndexingProducts\Service\EntityIndexingRecordCreatorService;
use Klevu\TestFixtures\Catalog\ProductTrait;
use Klevu\TestFixtures\Cms\PageFixturesPool;
use Klevu\TestFixtures\Cms\PageTrait;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Magento\Framework\Api\ExtensibleDataInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use TddWizard\Fixtures\Catalog\ProductFixturePool;

/**
 * @covers EntityIndexingRecordCreatorService::class
 * @method EntityIndexingRecordCreatorServiceInterface instantiateTestObject(?array $arguments = null)
 * @method EntityIndexingRecordCreatorServiceInterface instantiateTestObjectFromInterface(?array $arguments = null)
 */
class EntityIndexingRecordCreatorServiceTest extends TestCase
{
    use ObjectInstantiationTrait;
    use PageTrait;
    use ProductTrait;
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

        $this->implementationFqcn = EntityIndexingRecordCreatorService::class;
        $this->interfaceFqcn = EntityIndexingRecordCreatorServiceInterface::class;
        $this->objectManager = Bootstrap::getObjectManager();
        $this->productFixturePool = $this->objectManager->get(ProductFixturePool::class);
        $this->pageFixturesPool = $this->objectManager->get(PageFixturesPool::class);
    }

    /**
     * @return void
     * @throws \Exception
     */
    protected function tearDown(): void
    {
        parent::tearDown();

        $this->pageFixturesPool->rollback();
        $this->productFixturePool->rollback();
    }

    public function testExecute_ThrowsException_WhenIncorrectEntityTypeProvided(): void
    {
        $this->createPage();
        $pageFixture = $this->pageFixturesPool->get('test_page');
        $page = $pageFixture->getPage();

        $this->expectException(InvalidIndexingRecordDataTypeException::class);
        $this->expectExceptionMessage(
            sprintf(
                '"entity" provided to %s, must be instance of %s',
                EntityIndexingRecordCreatorService::class,
                ExtensibleDataInterface::class,
            ),
        );

        $service = $this->instantiateTestObject();
        $service->execute(
            recordId: 1,
            entity: $page,
        );
    }

    public function testExecute_ThrowsException_WhenIncorrectParentEntityTypeProvided(): void
    {
        $this->createProduct();
        $productFixture = $this->productFixturePool->get('test_product');
        $product = $productFixture->getProduct();

        $this->createPage();
        $pageFixture = $this->pageFixturesPool->get('test_page');
        $page = $pageFixture->getPage();

        $this->expectException(InvalidIndexingRecordDataTypeException::class);
        $this->expectExceptionMessage(
            sprintf(
                '"parent" provided to %s, must be instance of %s or null',
                EntityIndexingRecordCreatorService::class,
                ExtensibleDataInterface::class,
            ),
        );

        $service = $this->instantiateTestObject();
        $service->execute(
            recordId: 1,
            entity: $product,
            parent: $page,
        );
    }

    public function testExecute_ReturnsIndexingRecord_WithEntity(): void
    {
        $this->createProduct();
        $productFixture = $this->productFixturePool->get('test_product');
        $product = $productFixture->getProduct();

        $service = $this->instantiateTestObject();
        $result = $service->execute(
            recordId: 1,
            entity: $productFixture->getProduct(),
        );

        $this->assertSame(
            expected: (int)$product->getId(),
            actual: (int)$result->getEntity()->getId(),
        );
        $this->assertNull(actual: $result->getParent());
    }

    /**
     * @magentoDbIsolation disabled
     */
    public function testExecute_ReturnsIndexingRecord_WithAllData(): void
    {
        $this->createProduct();
        $productFixture1 = $this->productFixturePool->get('test_product');
        $product1 = $productFixture1->getProduct();

        $this->createProduct([
            'key' => 'test_parent_product',
        ]);
        $productFixture2 = $this->productFixturePool->get('test_parent_product');
        $product2 = $productFixture2->getProduct();

        $service = $this->instantiateTestObject();
        $result = $service->execute(
            recordId: 1,
            entity: $product1,
            parent: $product2,
        );

        $this->assertSame(
            expected: (int)$product1->getId(),
            actual: (int)$result->getEntity()->getId(),
        );
        $this->assertSame(
            expected: (int)$product2->getId(),
            actual: (int)$result->getParent()->getId(),
        );
    }
}
