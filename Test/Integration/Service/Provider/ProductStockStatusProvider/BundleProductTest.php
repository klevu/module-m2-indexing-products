<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Test\Integration\Service\Provider\ProductStockStatusProvider;

use Klevu\IndexingProducts\Service\Provider\ProductStockStatusProvider;
use Klevu\TestFixtures\Catalog\AttributeTrait;
use Klevu\TestFixtures\Catalog\ProductTrait;
use Klevu\TestFixtures\Store\StoreTrait;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Website\WebsiteTrait;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Klevu\IndexingProducts\Service\Provider\ProductStockStatusProvider::class
 * @method ProductStockStatusProvider instantiateTestObject(?array $arguments = null)
 * @method ProductStockStatusProvider instantiateTestObjectFromInterface(?array $arguments = null)
 * @runTestsInSeparateProcesses
 * @todo Backorders
 * @todo Implement Bundle Product fixture builder
 */
class BundleProductTest extends TestCase
{
    use AttributeTrait;
    use ObjectInstantiationTrait;
    use ProductStockStatusProviderTestTrait;
    use ProductTrait;
    use StoreTrait;
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

        $this->implementationFqcn = ProductStockStatusProvider::class;
        $this->fixtureIdentifier = 'klevu_test_productstockstatus';
        $this->fixtureName = 'Klevu Test: Product Stock Status (Bundle)';

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
    public function testGet_InStock_RequiredChildrenInStock(
        string $stockStatusCalculationMethod,
    ): void {
        $this->markTestSkipped('To Do: Implement Bundle Product Fixture Builder');
    }

    /**
     * @testWith ["stock_item"]
     *           ["stock_registry"]
     *           ["is_available"]
     *           ["is_salable"]
     */
    public function testGet_InStock_RequiredChildrenOutOfStock(
        string $stockStatusCalculationMethod,
    ): void {
        $this->markTestSkipped('To Do: Implement Bundle Product Fixture Builder');
    }

    /**
     * @testWith ["stock_item"]
     *           ["stock_registry"]
     *           ["is_available"]
     *           ["is_salable"]
     */
    public function testGet_OutOfStock_RequiredChildrenInStock(
        string $stockStatusCalculationMethod,
    ): void {
        $this->markTestSkipped('To Do: Implement Bundle Product Fixture Builder');
    }

    /**
     * @testWith ["stock_item"]
     *           ["stock_registry"]
     *           ["is_available"]
     *           ["is_salable"]
     */
    public function testGet_InStock_OptionalChildrenOutOfStock(
        string $stockStatusCalculationMethod,
    ): void {
        $this->markTestSkipped('To Do: Implement Bundle Product Fixture Builder');
    }

    /**
     * @testWith ["stock_item"]
     *           ["stock_registry"]
     *           ["is_available"]
     *           ["is_salable"]
     */
    public function testGet_InStock_RequiredChildrenInStock_NotAssignedWebsite(
        string $stockStatusCalculationMethod,
    ): void {
        $this->markTestSkipped('To Do: Implement Bundle Product Fixture Builder');
    }

    /**
     * @testWith ["stock_item"]
     *           ["stock_registry"]
     *           ["is_available"]
     *           ["is_salable"]
     */
    public function testGet_InStock_RequiredChildrenInStock_ChildrenNotAssignedWebsite(
        string $stockStatusCalculationMethod,
    ): void {
        $this->markTestSkipped('To Do: Implement Bundle Product Fixture Builder');
    }
}
