<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Test\Integration\Service\Action;

use Klevu\IndexingProducts\Model\ResourceModel\Product\Collection as ProductCollection;
use Klevu\IndexingProducts\Service\Action\JoinStockToCollectionAction;
use Klevu\IndexingProducts\Service\Action\JoinStockToCollectionActionInterface;
use Klevu\TestFixtures\Store\StoreFixturesPool;
use Klevu\TestFixtures\Store\StoreTrait;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Klevu\TestFixtures\Traits\TestInterfacePreferenceTrait;
use Klevu\TestFixtures\Website\WebsiteFixturesPool;
use Klevu\TestFixtures\Website\WebsiteTrait;
use Magento\Framework\DB\Select;
use Magento\Framework\HTTP\PhpEnvironment\Request;
use Magento\Framework\ObjectManagerInterface;
use Magento\InventoryApi\Api\Data\StockInterface;
use Magento\InventorySales\Model\StockResolver;
use Magento\InventorySalesApi\Api\Data\SalesChannelInterface;
use Magento\InventorySalesApi\Api\StockResolverInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * @covers JoinStockToCollectionAction::class
 * @method JoinStockToCollectionActionInterface instantiateTestObject(?array $arguments = null)
 * @method JoinStockToCollectionActionInterface instantiateTestObjectFromInterface(?array $arguments = null)
 */
class JoinStockToCollectionActionTest extends TestCase
{
    use ObjectInstantiationTrait;
    use StoreTrait;
    use TestImplementsInterfaceTrait;
    use TestInterfacePreferenceTrait;
    use WebsiteTrait;

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

        $this->implementationFqcn = JoinStockToCollectionAction::class;
        $this->interfaceFqcn = JoinStockToCollectionActionInterface::class;
        $this->objectManager = Bootstrap::getObjectManager();

        $this->websiteFixturesPool = $this->objectManager->get(WebsiteFixturesPool::class);
        $this->storeFixturesPool = $this->objectManager->get(StoreFixturesPool::class);
    }

    /**
     * @return void
     * @throws \Exception
     */
    protected function tearDown(): void
    {
        parent::tearDown();

        $this->storeFixturesPool->rollback();
        $this->websiteFixturesPool->rollback();
    }

    public function testExecute_DoesNotAddCatalogInventoryStockToCollection_WhenFlagSet(): void
    {
        $collection = $this->objectManager->create(ProductCollection::class);
        $collection->setFlag(JoinStockToCollectionAction::STOCK_ADDED_TO_COLLECTION, true);

        $action = $this->instantiateTestObject();
        $action->execute(collection: $collection);

        /** @var Select $select */
        $select = $collection->getSelect();
        $from = $select->getPart(Select::FROM) ?? [];
        $this->assertArrayNotHasKey(key: 'stock_status_index', array: $from);
    }

    public function testExecute_AddsCatalogInventoryStockToCollection_WhenFlagNotSet_DefaultStock(): void
    {
        $collection = $this->objectManager->create(ProductCollection::class);
        $collection->setFlag(JoinStockToCollectionAction::STOCK_ADDED_TO_COLLECTION, false);

        $action = $this->instantiateTestObject();
        $action->execute(collection: $collection);

        /** @var Select $select */
        $select = $collection->getSelect();
        $from = $select->getPart(Select::FROM) ?? [];
        $this->assertArrayHasKey(key: 'stock_status_index', array: $from);
        $stockJoin = $from['stock_status_index'];
        $this->assertArrayHasKey(key: 'joinCondition', array: $stockJoin);
        $this->assertSame(expected: 'e.entity_id = stock_status_index.product_id', actual: $stockJoin['joinCondition']);
        $this->assertArrayHasKey(key: 'joinType', array: $stockJoin);
        $this->assertSame(expected: 'left join', actual: $stockJoin['joinType']);
        $this->assertArrayHasKey(key: 'schema', array: $stockJoin);
        $this->assertnull(actual: $stockJoin['schema']);
        $this->assertArrayHasKey(key: 'tableName', array: $stockJoin);
        $this->assertStringContainsString(needle: 'cataloginventory_stock_status', haystack: $stockJoin['tableName']);
    }

    /**
     * @magentoAppIsolation enabled
     */
    public function testExecute_AddsCatalogInventoryStockToCollection_WhenFlagNotSet_NonDefaultStock(): void
    {
        $this->createWebsite();
        $websiteFixture = $this->websiteFixturesPool->get('test_website');

        $this->createStore(storeData: [
            'website_id' => $websiteFixture->getId(),
        ]);
        $storeFixture = $this->storeFixturesPool->get('test_store');

        $mockRequest = $this->getMockBuilder(Request::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockRequest->expects($this->once())
            ->method('getParam')
            ->with('store')
            ->willReturn($storeFixture->getId());
        $this->objectManager->addSharedInstance(
            instance: $mockRequest,
            className: Request::class,
        );

        $mockStock = $this->getMockBuilder(StockInterface::class)
            ->getMock();
        $mockStock->expects($this->once())
            ->method('getStockId')
            ->willReturn(2);
        $mockStockResolver = $this->getMockBuilder(StockResolverInterface::class)
            ->getMock();
        $mockStockResolver->expects($this->once())
            ->method('execute')
            ->with(SalesChannelInterface::TYPE_WEBSITE, $websiteFixture->getCode())
            ->willReturn($mockStock);

        $this->objectManager->addSharedInstance(
            instance: $mockStockResolver,
            className: StockResolverInterface::class,
        );
        $this->objectManager->addSharedInstance(
            instance: $mockStockResolver,
            className: StockResolver::class,
        );

        $collection = $this->objectManager->create(ProductCollection::class);
        $collection->setFlag(JoinStockToCollectionAction::STOCK_ADDED_TO_COLLECTION, false);

        $action = $this->instantiateTestObject();
        $action->execute(collection: $collection);

        /** @var Select $select */
        $select = $collection->getSelect();
        $from = $select->getPart(Select::FROM) ?? [];
        $this->assertArrayHasKey(key: 'stock_status_index', array: $from);
        $stockJoin = $from['stock_status_index'];
        $this->assertArrayHasKey(key: 'joinCondition', array: $stockJoin);
        $this->assertSame(expected: 'product.sku = stock_status_index.sku', actual: $stockJoin['joinCondition']);
        $this->assertArrayHasKey(key: 'joinType', array: $stockJoin);
        $this->assertSame(expected: 'left join', actual: $stockJoin['joinType']);
        $this->assertArrayHasKey(key: 'schema', array: $stockJoin);
        $this->assertnull(actual: $stockJoin['schema']);
        $this->assertArrayHasKey(key: 'tableName', array: $stockJoin);
        $this->assertStringContainsString(needle: 'inventory_stock_2', haystack: $stockJoin['tableName']);
    }
}
