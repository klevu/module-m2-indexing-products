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
use TddWizard\Fixtures\Core\ConfigFixture;

/**
 * @covers \Klevu\IndexingProducts\Service\Provider\ProductStockStatusProvider::class
 * @method ProductStockStatusProvider instantiateTestObject(?array $arguments = null)
 * @method ProductStockStatusProvider instantiateTestObjectFromInterface(?array $arguments = null)
 * @runTestsInSeparateProcesses
 * @todo Backorders
 */
class ConfigurableProductTest extends TestCase
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
        $this->fixtureName = 'Klevu Test: Product Stock Status (Configurable)';

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
    public function testGet_InStock_ChildrenInStock(
        string $stockStatusCalculationMethod,
    ): void {
        ConfigFixture::setGlobal(
            path: ProductStockStatusProvider::XML_PATH_STOCK_STATUS_CALCULATION_METHOD,
            value: $stockStatusCalculationMethod,
        );

        $websiteAndStoreFixtures = $this->createWebsiteAndStoreFixtures();

        $this->createAttribute(
            attributeData: [
                'key' => $this->fixtureIdentifier,
                'code' => $this->fixtureIdentifier,
                'attribute_type' => 'configurable',
                'options' => [
                    '1' => 'Option 1',
                    '2' => 'Option 2',
                ],
            ],
        );
        $configurableAttributeFixture = $this->attributeFixturePool->get(
            key: $this->fixtureIdentifier,
        );
        $configurableAttribute = $configurableAttributeFixture->getAttribute();

        $variantProductFixture1 = $this->createSimpleProductFixture(
            appendIdentifier: 'v1',
            websiteIds: [
                (int)$websiteAndStoreFixtures['websiteFixture1']->getId(),
            ],
            quantity: 100,
            stockStatus: true,
            data: [
                $configurableAttribute->getAttributeCode() => '1',
            ],
        );
        $variantProductFixture2 = $this->createSimpleProductFixture(
            appendIdentifier: 'v2',
            websiteIds: [
                (int)$websiteAndStoreFixtures['websiteFixture1']->getId(),
            ],
            quantity: 100,
            stockStatus: true,
            data: [
                $configurableAttribute->getAttributeCode() => '2',
            ],
        );
        $configurableProductFixture = $this->createConfigurableProductFixture(
            appendIdentifier: 'c1',
            websiteIds: [
                (int)$websiteAndStoreFixtures['websiteFixture1']->getId(),
            ],
            configurableAttributes: [
                $configurableAttribute->getAttributeCode() => $configurableAttribute,
            ],
            configurableVariants: [
                $variantProductFixture1->getProduct(),
                $variantProductFixture2->getProduct(),
            ],
            stockStatus: true,
        );

        $productStockStatusProvider = $this->instantiateTestObject();

        $this->assertTrue(
            condition: $productStockStatusProvider->get(
                product: $variantProductFixture1->getProduct(),
                store: $websiteAndStoreFixtures['storeFixture1']->get(),
                parentProduct: null,
            ),
            message: 'Simple 1; Store 1',
        );
        $this->assertFalse(
            condition: $productStockStatusProvider->get(
                product: $variantProductFixture1->getProduct(),
                store: $websiteAndStoreFixtures['storeFixture2']->get(),
                parentProduct: null,
            ),
            message: 'Simple 1; Store 2',
        );
        $this->assertTrue(
            condition: $productStockStatusProvider->get(
                product: $variantProductFixture2->getProduct(),
                store: $websiteAndStoreFixtures['storeFixture1']->get(),
                parentProduct: null,
            ),
            message: 'Simple 2; Store 1',
        );
        $this->assertFalse(
            condition: $productStockStatusProvider->get(
                product: $variantProductFixture2->getProduct(),
                store: $websiteAndStoreFixtures['storeFixture2']->get(),
                parentProduct: null,
            ),
            message: 'Simple 2; Store 2',
        );
        $this->assertTrue(
            condition: $productStockStatusProvider->get(
                product: $configurableProductFixture->getProduct(),
                store: $websiteAndStoreFixtures['storeFixture1']->get(),
                parentProduct: null,
            ),
            message: 'Configurable; Store 1',
        );
        $this->assertFalse(
            condition: $productStockStatusProvider->get(
                product: $configurableProductFixture->getProduct(),
                store: $websiteAndStoreFixtures['storeFixture2']->get(),
                parentProduct: null,
            ),
            message: 'Configurable; Store 2',
        );
    }

    /**
     * @testWith ["stock_item"]
     *           ["stock_registry"]
     *           ["is_available"]
     *           ["is_salable"]
     */
    public function testGet_InStock_ChildrenOutOfStock(
        string $stockStatusCalculationMethod,
    ): void {
        ConfigFixture::setGlobal(
            path: ProductStockStatusProvider::XML_PATH_STOCK_STATUS_CALCULATION_METHOD,
            value: $stockStatusCalculationMethod,
        );

        $websiteAndStoreFixtures = $this->createWebsiteAndStoreFixtures();

        $this->createAttribute(
            attributeData: [
                'key' => $this->fixtureIdentifier,
                'code' => $this->fixtureIdentifier,
                'attribute_type' => 'configurable',
                'options' => [
                    '1' => 'Option 1',
                    '2' => 'Option 2',
                ],
            ],
        );
        $configurableAttributeFixture = $this->attributeFixturePool->get(
            key: $this->fixtureIdentifier,
        );
        $configurableAttribute = $configurableAttributeFixture->getAttribute();

        $variantProductFixture1 = $this->createSimpleProductFixture(
            appendIdentifier: 'v1',
            websiteIds: [
                (int)$websiteAndStoreFixtures['websiteFixture1']->getId(),
            ],
            quantity: 0,
            stockStatus: true,
            data: [
                $configurableAttribute->getAttributeCode() => '1',
            ],
        );
        $variantProductFixture2 = $this->createSimpleProductFixture(
            appendIdentifier: 'v2',
            websiteIds: [
                (int)$websiteAndStoreFixtures['websiteFixture1']->getId(),
            ],
            quantity: 0,
            stockStatus: false,
            data: [
                $configurableAttribute->getAttributeCode() => '2',
            ],
        );
        $configurableProductFixture = $this->createConfigurableProductFixture(
            appendIdentifier: 'c1',
            websiteIds: [
                (int)$websiteAndStoreFixtures['websiteFixture1']->getId(),
            ],
            configurableAttributes: [
                $configurableAttribute->getAttributeCode() => $configurableAttribute,
            ],
            configurableVariants: [
                $variantProductFixture1->getProduct(),
                $variantProductFixture2->getProduct(),
            ],
            stockStatus: true,
        );

        $productStockStatusProvider = $this->instantiateTestObject();

        $this->assertFalse(
            condition: $productStockStatusProvider->get(
                product: $variantProductFixture1->getProduct(),
                store: $websiteAndStoreFixtures['storeFixture1']->get(),
                parentProduct: null,
            ),
            message: 'Variant 1; Store 1',
        );
        $this->assertFalse(
            condition: $productStockStatusProvider->get(
                product: $variantProductFixture1->getProduct(),
                store: $websiteAndStoreFixtures['storeFixture2']->get(),
                parentProduct: null,
            ),
            message: 'Variant 1; Store 2',
        );
        $this->assertFalse(
            condition: $productStockStatusProvider->get(
                product: $variantProductFixture2->getProduct(),
                store: $websiteAndStoreFixtures['storeFixture1']->get(),
                parentProduct: null,
            ),
            message: 'Variant 2; Store 1',
        );
        $this->assertFalse(
            condition: $productStockStatusProvider->get(
                product: $variantProductFixture2->getProduct(),
                store: $websiteAndStoreFixtures['storeFixture2']->get(),
                parentProduct: null,
            ),
            message: 'Variant 2; Store 2',
        );
        $this->assertFalse(
            condition: $productStockStatusProvider->get(
                product: $configurableProductFixture->getProduct(),
                store: $websiteAndStoreFixtures['storeFixture1']->get(),
                parentProduct: null,
            ),
            message: 'Configurable; Store 1',
        );
        $this->assertFalse(
            condition: $productStockStatusProvider->get(
                product: $configurableProductFixture->getProduct(),
                store: $websiteAndStoreFixtures['storeFixture2']->get(),
                parentProduct: null,
            ),
            message: 'Configurable; Store 2',
        );
    }

    /**
     * @testWith ["stock_item"]
     *           ["stock_registry"]
     *           ["is_available"]
     *           ["is_salable"]
     */
    public function testGet_OutOfStock_ChildrenInStock(
        string $stockStatusCalculationMethod,
    ): void {
        ConfigFixture::setGlobal(
            path: ProductStockStatusProvider::XML_PATH_STOCK_STATUS_CALCULATION_METHOD,
            value: $stockStatusCalculationMethod,
        );

        $websiteAndStoreFixtures = $this->createWebsiteAndStoreFixtures();

        $this->createAttribute(
            attributeData: [
                'key' => $this->fixtureIdentifier,
                'code' => $this->fixtureIdentifier,
                'attribute_type' => 'configurable',
                'options' => [
                    '1' => 'Option 1',
                    '2' => 'Option 2',
                ],
            ],
        );
        $configurableAttributeFixture = $this->attributeFixturePool->get(
            key: $this->fixtureIdentifier,
        );
        $configurableAttribute = $configurableAttributeFixture->getAttribute();

        $variantProductFixture1 = $this->createSimpleProductFixture(
            appendIdentifier: 'v1',
            websiteIds: [
                (int)$websiteAndStoreFixtures['websiteFixture1']->getId(),
            ],
            quantity: 100,
            stockStatus: true,
            data: [
                $configurableAttribute->getAttributeCode() => '1',
            ],
        );
        $variantProductFixture2 = $this->createSimpleProductFixture(
            appendIdentifier: 'v2',
            websiteIds: [
                (int)$websiteAndStoreFixtures['websiteFixture1']->getId(),
            ],
            quantity: 100,
            stockStatus: true,
            data: [
                $configurableAttribute->getAttributeCode() => '2',
            ],
        );
        $configurableProductFixture = $this->createConfigurableProductFixture(
            appendIdentifier: 'c1',
            websiteIds: [
                (int)$websiteAndStoreFixtures['websiteFixture1']->getId(),
            ],
            configurableAttributes: [
                $configurableAttribute->getAttributeCode() => $configurableAttribute,
            ],
            configurableVariants: [
                $variantProductFixture1->getProduct(),
                $variantProductFixture2->getProduct(),
            ],
            stockStatus: false,
        );

        $productStockStatusProvider = $this->instantiateTestObject();

        $this->assertTrue(
            condition: $productStockStatusProvider->get(
                product: $variantProductFixture1->getProduct(),
                store: $websiteAndStoreFixtures['storeFixture1']->get(),
                parentProduct: null,
            ),
            message: 'Simple 1; Store 1',
        );
        $this->assertFalse(
            condition: $productStockStatusProvider->get(
                product: $variantProductFixture1->getProduct(),
                store: $websiteAndStoreFixtures['storeFixture2']->get(),
                parentProduct: null,
            ),
            message: 'Simple 1; Store 2',
        );
        $this->assertTrue(
            condition: $productStockStatusProvider->get(
                product: $variantProductFixture2->getProduct(),
                store: $websiteAndStoreFixtures['storeFixture1']->get(),
                parentProduct: null,
            ),
            message: 'Simple 2; Store 1',
        );
        $this->assertFalse(
            condition: $productStockStatusProvider->get(
                product: $variantProductFixture2->getProduct(),
                store: $websiteAndStoreFixtures['storeFixture2']->get(),
                parentProduct: null,
            ),
            message: 'Simple 2; Store 2',
        );
        $this->assertFalse(
            condition: $productStockStatusProvider->get(
                product: $configurableProductFixture->getProduct(),
                store: $websiteAndStoreFixtures['storeFixture1']->get(),
                parentProduct: null,
            ),
            message: 'Configurable; Store 1',
        );
        $this->assertFalse(
            condition: $productStockStatusProvider->get(
                product: $configurableProductFixture->getProduct(),
                store: $websiteAndStoreFixtures['storeFixture2']->get(),
                parentProduct: null,
            ),
            message: 'Configurable; Store 2',
        );
    }

    /**
     * @testWith ["stock_item"]
     *           ["stock_registry"]
     *           ["is_available"]
     *           ["is_salable"]
     */
    public function testGet_InStock_ChildrenInStock_NotAssignedWebsite(
        string $stockStatusCalculationMethod,
    ): void {
        ConfigFixture::setGlobal(
            path: ProductStockStatusProvider::XML_PATH_STOCK_STATUS_CALCULATION_METHOD,
            value: $stockStatusCalculationMethod,
        );

        $websiteAndStoreFixtures = $this->createWebsiteAndStoreFixtures();

        $this->createAttribute(
            attributeData: [
                'key' => $this->fixtureIdentifier,
                'code' => $this->fixtureIdentifier,
                'attribute_type' => 'configurable',
                'options' => [
                    '1' => 'Option 1',
                    '2' => 'Option 2',
                ],
            ],
        );
        $configurableAttributeFixture = $this->attributeFixturePool->get(
            key: $this->fixtureIdentifier,
        );
        $configurableAttribute = $configurableAttributeFixture->getAttribute();

        $variantProductFixture1 = $this->createSimpleProductFixture(
            appendIdentifier: 'v1',
            websiteIds: [
                (int)$websiteAndStoreFixtures['websiteFixture1']->getId(),
            ],
            quantity: 100,
            stockStatus: true,
            data: [
                $configurableAttribute->getAttributeCode() => '1',
            ],
        );
        $variantProductFixture2 = $this->createSimpleProductFixture(
            appendIdentifier: 'v2',
            websiteIds: [
                (int)$websiteAndStoreFixtures['websiteFixture1']->getId(),
            ],
            quantity: 100,
            stockStatus: true,
            data: [
                $configurableAttribute->getAttributeCode() => '2',
            ],
        );
        $configurableProductFixture = $this->createConfigurableProductFixture(
            appendIdentifier: 'c1',
            websiteIds: [
                (int)$websiteAndStoreFixtures['websiteFixture2']->getId(),
            ],
            configurableAttributes: [
                $configurableAttribute->getAttributeCode() => $configurableAttribute,
            ],
            configurableVariants: [
                $variantProductFixture1->getProduct(),
                $variantProductFixture2->getProduct(),
            ],
            stockStatus: true,
        );

        $productStockStatusProvider = $this->instantiateTestObject();

        $this->assertTrue(
            condition: $productStockStatusProvider->get(
                product: $variantProductFixture1->getProduct(),
                store: $websiteAndStoreFixtures['storeFixture1']->get(),
                parentProduct: null,
            ),
            message: 'Simple 1; Store 1',
        );
        $this->assertFalse(
            condition: $productStockStatusProvider->get(
                product: $variantProductFixture1->getProduct(),
                store: $websiteAndStoreFixtures['storeFixture2']->get(),
                parentProduct: null,
            ),
            message: 'Simple 1; Store 2',
        );
        $this->assertTrue(
            condition: $productStockStatusProvider->get(
                product: $variantProductFixture2->getProduct(),
                store: $websiteAndStoreFixtures['storeFixture1']->get(),
                parentProduct: null,
            ),
            message: 'Simple 2; Store 1',
        );
        $this->assertFalse(
            condition: $productStockStatusProvider->get(
                product: $variantProductFixture2->getProduct(),
                store: $websiteAndStoreFixtures['storeFixture2']->get(),
                parentProduct: null,
            ),
            message: 'Simple 2; Store 2',
        );
        $this->assertFalse(
            condition: $productStockStatusProvider->get(
                product: $configurableProductFixture->getProduct(),
                store: $websiteAndStoreFixtures['storeFixture1']->get(),
                parentProduct: null,
            ),
            message: 'Configurable; Store 1',
        );
        $this->assertFalse(
            condition: $productStockStatusProvider->get(
                product: $configurableProductFixture->getProduct(),
                store: $websiteAndStoreFixtures['storeFixture2']->get(),
                parentProduct: null,
            ),
            message: 'Configurable; Store 2',
        );
    }

    /**
     * @testWith ["stock_item"]
     *           ["stock_registry"]
     *           ["is_available"]
     *           ["is_salable"]
     */
    public function testGet_InStock_ChildrenInStock_ChildrenNotAssignedWebsite(
        string $stockStatusCalculationMethod,
    ): void {
        ConfigFixture::setGlobal(
            path: ProductStockStatusProvider::XML_PATH_STOCK_STATUS_CALCULATION_METHOD,
            value: $stockStatusCalculationMethod,
        );

        $websiteAndStoreFixtures = $this->createWebsiteAndStoreFixtures();

        $this->createAttribute(
            attributeData: [
                'key' => $this->fixtureIdentifier,
                'code' => $this->fixtureIdentifier,
                'attribute_type' => 'configurable',
                'options' => [
                    '1' => 'Option 1',
                    '2' => 'Option 2',
                ],
            ],
        );
        $configurableAttributeFixture = $this->attributeFixturePool->get(
            key: $this->fixtureIdentifier,
        );
        $configurableAttribute = $configurableAttributeFixture->getAttribute();

        $variantProductFixture1 = $this->createSimpleProductFixture(
            appendIdentifier: 'v1',
            websiteIds: [
                (int)$websiteAndStoreFixtures['websiteFixture2']->getId(),
            ],
            quantity: 100,
            stockStatus: true,
            data: [
                $configurableAttribute->getAttributeCode() => '1',
            ],
        );
        $variantProductFixture2 = $this->createSimpleProductFixture(
            appendIdentifier: 'v2',
            websiteIds: [
                (int)$websiteAndStoreFixtures['websiteFixture2']->getId(),
            ],
            quantity: 100,
            stockStatus: true,
            data: [
                $configurableAttribute->getAttributeCode() => '2',
            ],
        );
        $configurableProductFixture = $this->createConfigurableProductFixture(
            appendIdentifier: 'c1',
            websiteIds: [
                (int)$websiteAndStoreFixtures['websiteFixture1']->getId(),
            ],
            configurableAttributes: [
                $configurableAttribute->getAttributeCode() => $configurableAttribute,
            ],
            configurableVariants: [
                $variantProductFixture1->getProduct(),
                $variantProductFixture2->getProduct(),
            ],
            stockStatus: true,
        );

        $productStockStatusProvider = $this->instantiateTestObject();

        $this->assertFalse(
            condition: $productStockStatusProvider->get(
                product: $variantProductFixture1->getProduct(),
                store: $websiteAndStoreFixtures['storeFixture1']->get(),
                parentProduct: null,
            ),
            message: 'Simple 1; Store 1',
        );
        $this->assertTrue(
            condition: $productStockStatusProvider->get(
                product: $variantProductFixture1->getProduct(),
                store: $websiteAndStoreFixtures['storeFixture2']->get(),
                parentProduct: null,
            ),
            message: 'Simple 1; Store 2',
        );
        $this->assertFalse(
            condition: $productStockStatusProvider->get(
                product: $variantProductFixture2->getProduct(),
                store: $websiteAndStoreFixtures['storeFixture1']->get(),
                parentProduct: null,
            ),
            message: 'Simple 2; Store 1',
        );
        $this->assertTrue(
            condition: $productStockStatusProvider->get(
                product: $variantProductFixture2->getProduct(),
                store: $websiteAndStoreFixtures['storeFixture2']->get(),
                parentProduct: null,
            ),
            message: 'Simple 2; Store 2',
        );
        $this->assertFalse(
            condition: $productStockStatusProvider->get(
                product: $configurableProductFixture->getProduct(),
                store: $websiteAndStoreFixtures['storeFixture1']->get(),
                parentProduct: null,
            ),
            message: 'Configurable; Store 1',
        );
        $this->assertFalse(
            condition: $productStockStatusProvider->get(
                product: $configurableProductFixture->getProduct(),
                store: $websiteAndStoreFixtures['storeFixture2']->get(),
                parentProduct: null,
            ),
            message: 'Configurable; Store 2',
        );
    }
}
