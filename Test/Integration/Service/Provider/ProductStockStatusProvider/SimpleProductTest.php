<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Test\Integration\Service\Provider\ProductStockStatusProvider;

use Klevu\IndexingProducts\Service\Provider\ProductStockStatusProvider;
use Klevu\TestFixtures\Catalog\ProductTrait;
use Klevu\TestFixtures\Store\StoreTrait;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Website\WebsiteTrait;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use TddWizard\Fixtures\Core\ConfigFixture;

/**
 * @covers \Klevu\IndexingProducts\Service\Provider\ProductStockStatusProvider::class
 * @method ProductStockStatusProvider instantiateTestObject(?array $arguments = null)
 * @method ProductStockStatusProvider instantiateTestObjectFromInterface(?array $arguments = null)
 * @runTestsInSeparateProcesses
 * @todo Backorders
 */
class SimpleProductTest extends TestCase
{
    use ObjectInstantiationTrait;
    use ProductTrait;
    use ProductStockStatusProviderTestTrait;
    use StoreTrait;
    use WebsiteTrait;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->implementationFqcn = ProductStockStatusProvider::class;
        $this->fixtureIdentifier = 'klevu_test_productstockstatus';
        $this->fixtureName = 'Klevu Test: Product Stock Status (Simple)';

        $this->objectManager = Bootstrap::getObjectManager();

        $this->setUpProperties();
    }

    /**
     * @return void
     * @throws \Exception
     */
    protected function tearDown(): void
    {
        parent::tearDown();

        $this->deleteFixtures();
    }

    public function testFqcnResolvesToExpectedImplementation(): void
    {
        // Intentionally empty override as test runs elsewhere and trigger error
        //  when @runInSeparateProcesses is active
    }

    /**
     * @testWith ["stock_item"]
     *           ["stock_registry"]
     *           ["is_available"]
     *           ["is_salable"]
     */
    public function testGet_InStock(
        string $stockStatusCalculationMethod,
    ): void {
        ConfigFixture::setGlobal(
            path: ProductStockStatusProvider::XML_PATH_STOCK_STATUS_CALCULATION_METHOD,
            value: $stockStatusCalculationMethod,
        );

        $websiteAndStoreFixtures = $this->createWebsiteAndStoreFixtures();
        $productFixtureSimple = $this->createSimpleProductFixture(
            appendIdentifier: 'simple',
            websiteIds: [
                (int)$websiteAndStoreFixtures['websiteFixture1']->getId(),
            ],
            quantity: 100,
            stockStatus: true,
        );

        $productStockStatusProvider = $this->instantiateTestObject();

        $result = $productStockStatusProvider->get(
            product: $productFixtureSimple->getProduct(),
            store: $websiteAndStoreFixtures['storeFixture1']->get(),
            parentProduct: null,
        );

        $this->assertTrue($result);
    }

    /**
     * @testWith ["stock_item"]
     *           ["stock_registry"]
     *           ["is_available"]
     *           ["is_salable"]
     */
    public function testGet_OutOfStock(
        string $stockStatusCalculationMethod,
    ): void {
        ConfigFixture::setGlobal(
            path: ProductStockStatusProvider::XML_PATH_STOCK_STATUS_CALCULATION_METHOD,
            value: $stockStatusCalculationMethod,
        );

        $websiteAndStoreFixtures = $this->createWebsiteAndStoreFixtures();
        $productFixtureSimple = $this->createSimpleProductFixture(
            appendIdentifier: 'simple',
            websiteIds: [
                (int)$websiteAndStoreFixtures['websiteFixture1']->getId(),
            ],
            quantity: 0,
            stockStatus: false,
        );

        $productStockStatusProvider = $this->instantiateTestObject();

        $result = $productStockStatusProvider->get(
            product: $productFixtureSimple->getProduct(),
            store: $websiteAndStoreFixtures['storeFixture1']->get(),
            parentProduct: null,
        );

        $this->assertFalse($result);
    }

    /**
     * @testWith ["stock_item"]
     *           ["stock_registry"]
     *           ["is_available"]
     *           ["is_salable"]
     */
    public function testGet_InStock_NotAssignedWebsite(
        string $stockStatusCalculationMethod,
    ): void {
        ConfigFixture::setGlobal(
            path: ProductStockStatusProvider::XML_PATH_STOCK_STATUS_CALCULATION_METHOD,
            value: $stockStatusCalculationMethod,
        );

        $websiteAndStoreFixtures = $this->createWebsiteAndStoreFixtures();
        $productFixtureSimple = $this->createSimpleProductFixture(
            appendIdentifier: 'simple',
            websiteIds: [
                (int)$websiteAndStoreFixtures['websiteFixture2']->getId(),
            ],
            quantity: 100,
            stockStatus: true,
        );

        $productStockStatusProvider = $this->instantiateTestObject();

        $result = $productStockStatusProvider->get(
            product: $productFixtureSimple->getProduct(),
            store: $websiteAndStoreFixtures['storeFixture1']->get(),
            parentProduct: null,
        );

        $this->assertFalse($result);
    }
}
