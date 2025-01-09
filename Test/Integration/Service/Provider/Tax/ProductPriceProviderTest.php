<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Test\Integration\Service\Provider\Tax;

use Klevu\IndexingApi\Service\Provider\Tax\ProductTaxProviderInterface;
use Klevu\IndexingProducts\Service\Provider\Tax\ProductTaxProvider;
use Klevu\TestFixtures\Catalog\ProductTrait;
use Klevu\TestFixtures\Customer\CustomerGroupTrait;
use Klevu\TestFixtures\Customer\Group\CustomerGroupFixturePool;
use Klevu\TestFixtures\Tax\TaxClassFixturePool;
use Klevu\TestFixtures\Tax\TaxClassTrait;
use Klevu\TestFixtures\Tax\TaxRateFixturePool;
use Klevu\TestFixtures\Tax\TaxRateTrait;
use Klevu\TestFixtures\Tax\TaxRuleFixturePool;
use Klevu\TestFixtures\Tax\TaxRuleTrait;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Klevu\TestFixtures\Traits\TestInterfacePreferenceTrait;
use Magento\Framework\ObjectManagerInterface;
use Magento\Tax\Model\Config as TaxConfig;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use TddWizard\Fixtures\Catalog\ProductFixturePool;
use TddWizard\Fixtures\Core\ConfigFixture;

/**
 * @covers ProductTaxProvider::class
 * @method ProductTaxProviderInterface instantiateTestObject(?array $arguments = null)
 * @method ProductTaxProviderInterface instantiateTestObjectFromInterface(?array $arguments = null)
 */
class ProductPriceProviderTest extends TestCase
{
    use CustomerGroupTrait;
    use ObjectInstantiationTrait;
    use ProductTrait;
    use TaxClassTrait;
    use TaxRateTrait;
    use TaxRuleTrait;
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

        $this->implementationFqcn = ProductTaxProvider::class;
        $this->interfaceFqcn = ProductTaxProviderInterface::class;
        $this->objectManager = Bootstrap::getObjectManager();

        $this->taxClassFixturePool = $this->objectManager->get(TaxClassFixturePool::class);
        $this->taxRateFixturePool = $this->objectManager->get(TaxRateFixturePool::class);
        $this->taxRuleFixturePool = $this->objectManager->get(TaxRuleFixturePool::class);
        $this->customerGroupFixturePool = $this->objectManager->get(CustomerGroupFixturePool::class);
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
        $this->customerGroupFixturePool->rollback();
        $this->taxRuleFixturePool->rollback();
        $this->taxRateFixturePool->rollback();
        $this->taxClassFixturePool->rollback();
    }

    /**
     * @magentoDbIsolation disabled
     * @dataProvider testGet_ReturnsPriceIncludingTax_CustomerGroupNotSet_dataProvider
     */
    public function testGet_ReturnsPriceIncludingTax_CustomerGroupNotSet(
        bool $productIsTaxable,
        bool $catalogIncludeTax,
        int $displayType,
        float $taxRate,
        float $initialValue,
        float $expectedValue,
    ): void {
        ConfigFixture::setGlobal(
            path: TaxConfig::CONFIG_XML_PATH_PRICE_INCLUDES_TAX,
            value: (int)$catalogIncludeTax,
        );
        ConfigFixture::setGlobal(
            path: TaxConfig::CONFIG_XML_PATH_PRICE_DISPLAY_TYPE,
            value: $displayType,
        );
        ConfigFixture::setGlobal(
            path: TaxConfig::CONFIG_XML_PATH_BASED_ON,
            value: 'shipping',
        );
        ConfigFixture::setGlobal(
            path: TaxConfig::CONFIG_XML_PATH_DEFAULT_COUNTRY,
            value: 'GB',
        );
        ConfigFixture::setGlobal(
            path: TaxConfig::CONFIG_XML_PATH_DEFAULT_REGION,
            value: '*',
        );
        ConfigFixture::setGlobal(
            path: TaxConfig::CONFIG_XML_PATH_DEFAULT_POSTCODE,
            value: '*',
        );

        $this->createTaxClass(taxClassData: [
            'class_name' => 'Product Tax Class',
            'class_type' => 'PRODUCT',
            'key' => 'product_class_tax',
        ]);
        $productTaxClass = $this->taxClassFixturePool->get(key: 'product_class_tax');

        $this->createTaxRate(taxRateData: [
            'rate' => $taxRate,
            'tax_country_id' => 'GB',
            'tax_postcode' => '*',
        ]);
        $taxRateFixture = $this->taxRateFixturePool->get('test_tax_rate');
        $this->createTaxRule(taxRuleData: [
            'tax_rate_ids' => [$taxRateFixture->getId()],
            'product_tax_class_ids' => [$productTaxClass->getId()],
        ]);

        $this->createProduct(productData:[
            'tax_class_id' => $productIsTaxable ? $productTaxClass->getId() : 0,
            'price' => $initialValue,
        ]);
        $productFixture = $this->productFixturePool->get('test_product');

        $transformer = $this->instantiateTestObject([]);
        $result = $transformer->get(
            product: $productFixture->getProduct(),
            price: $initialValue,
            customerGroupId: null,
        );

        $this->assertSame(expected: $expectedValue, actual: $result);
    }

    /**
     * @return mixed[][]
     */
    public function testGet_ReturnsPriceIncludingTax_CustomerGroupNotSet_dataProvider(): array
    {
        return [
            [true, false, TaxConfig::DISPLAY_TYPE_EXCLUDING_TAX, 20.00, 100.00, 100.00],
            [true, false, TaxConfig::DISPLAY_TYPE_INCLUDING_TAX, 20.00, 100.00, 120.00],
            [true, false, TaxConfig::DISPLAY_TYPE_BOTH, 20.00, 100.00, 120.00],
            [true, true, TaxConfig::DISPLAY_TYPE_EXCLUDING_TAX, 20.00, 100.00, 83.33],
            [true, true, TaxConfig::DISPLAY_TYPE_INCLUDING_TAX, 20.00, 100.00, 100.00],
            [true, true, TaxConfig::DISPLAY_TYPE_BOTH, 20.00, 100.00, 100.00],
            [false, false, TaxConfig::DISPLAY_TYPE_EXCLUDING_TAX, 20.00, 100.00, 100.00],
            [false, false, TaxConfig::DISPLAY_TYPE_INCLUDING_TAX, 20.00, 100.00, 100.00],
            [false, false, TaxConfig::DISPLAY_TYPE_BOTH, 20.00, 100.00, 100.00],
            [false, true, TaxConfig::DISPLAY_TYPE_EXCLUDING_TAX, 20.00, 100.00, 100.00],
            [false, true, TaxConfig::DISPLAY_TYPE_INCLUDING_TAX, 20.00, 100.00, 100.00],
            [false, true, TaxConfig::DISPLAY_TYPE_BOTH, 20.00, 100.00, 100.00],
        ];
    }

    /**
     * @magentoDbIsolation disabled
     * @dataProvider testGet_ReturnsPriceIncludingTax_CustomerGroupSet_dataProvider
     */
    public function testGet_ReturnsPriceIncludingTax_CustomerGroupSet(
        bool $productIsTaxable,
        bool $catalogIncludeTax,
        int $displayType,
        float $taxRate,
        float $initialValue,
        float $expectedValue,
    ): void {
        ConfigFixture::setGlobal(
            path: TaxConfig::CONFIG_XML_PATH_PRICE_INCLUDES_TAX,
            value: (int)$catalogIncludeTax,
        );
        ConfigFixture::setGlobal(
            path: TaxConfig::CONFIG_XML_PATH_PRICE_DISPLAY_TYPE,
            value: $displayType,
        );
        ConfigFixture::setGlobal(
            path: TaxConfig::CONFIG_XML_PATH_BASED_ON,
            value: 'shipping',
        );
        ConfigFixture::setGlobal(
            path: TaxConfig::CONFIG_XML_PATH_DEFAULT_COUNTRY,
            value: 'GB',
        );
        ConfigFixture::setGlobal(
            path: TaxConfig::CONFIG_XML_PATH_DEFAULT_REGION,
            value: '*',
        );
        ConfigFixture::setGlobal(
            path: TaxConfig::CONFIG_XML_PATH_DEFAULT_POSTCODE,
            value: '*',
        );

        $this->createTaxClass(taxClassData: [
            'class_name' => 'Product Tax Class',
            'class_type' => 'PRODUCT',
            'key' => 'product_class_tax',
        ]);
        $productTaxClass = $this->taxClassFixturePool->get(key: 'product_class_tax');

        $this->createTaxRate(taxRateData: [
            'code' => 'klevu_test_tax_rate_1',
            'rate' => 20.00,
            'tax_country_id' => 'GB',
            'tax_postcode' => '*',
            'key' => 'test_tax_rate_1',
        ]);
        $taxRateFixture1 = $this->taxRateFixturePool->get('test_tax_rate_1');
        $this->createTaxRule(taxRuleData: [
            'code' => 'global_tax_rule',
            'tax_rate_ids' => [$taxRateFixture1->getId()],
            'product_tax_class_ids' => [$productTaxClass->getId()],
            'key' => 'global_tax_rule',
        ]);

        $this->createTaxClass(taxClassData: [
            'class_name' => 'Customer Tax Class',
            'class_type' => 'CUSTOMER',
            'key' => 'customer_class_tax',
        ]);
        $customerTaxClass = $this->taxClassFixturePool->get(key: 'customer_class_tax');
        $this->createCustomerGroup(customerGroupData: [
            'tax_class_id' => $customerTaxClass->getId(),
        ]);
        $customerGroupFixture = $this->customerGroupFixturePool->get(key: 'test_customer_group');

        $this->createTaxRate(taxRateData: [
            'code' => 'klevu_test_tax_rate_2',
            'rate' => $taxRate,
            'tax_country_id' => 'GB',
            'tax_postcode' => '*',
            'key' => 'test_tax_rate_2',
        ]);
        $taxRateFixture2 = $this->taxRateFixturePool->get('test_tax_rate_2');
        $this->createTaxRule(taxRuleData: [
            'code' => 'customer_group_tax_rule',
            'tax_rate_ids' => [$taxRateFixture2->getId()],
            'product_tax_class_ids' => [$productTaxClass->getId()],
            'customer_tax_class_ids' => [$customerTaxClass->getId()],
            'key' => 'customer_group_tax_rule',
        ]);

        $this->createProduct(productData:[
            'tax_class_id' => $productIsTaxable ? $productTaxClass->getId() : 0,
            'price' => $initialValue,
        ]);
        $productFixture = $this->productFixturePool->get('test_product');

        $transformer = $this->instantiateTestObject([]);
        $result = $transformer->get(
            product: $productFixture->getProduct(),
            price: $initialValue,
            customerGroupId: $customerGroupFixture->getId(),
        );

        $this->assertSame(expected: $expectedValue, actual: $result);
    }

    /**
     * @return mixed[][]
     */
    public function testGet_ReturnsPriceIncludingTax_CustomerGroupSet_dataProvider(): array
    {
        return [
            [true, false, TaxConfig::DISPLAY_TYPE_EXCLUDING_TAX, 17.50, 100.00, 100.00],
            [true, false, TaxConfig::DISPLAY_TYPE_INCLUDING_TAX, 17.50, 100.00, 117.50],
            [true, false, TaxConfig::DISPLAY_TYPE_BOTH, 17.50, 100.00, 117.50],
            [true, true, TaxConfig::DISPLAY_TYPE_EXCLUDING_TAX, 17.50, 100.00, 85.11],
            [true, true, TaxConfig::DISPLAY_TYPE_INCLUDING_TAX, 17.50, 100.00, 100.00],
            [true, true, TaxConfig::DISPLAY_TYPE_BOTH, 17.50, 100.00, 100.00],
            [false, false, TaxConfig::DISPLAY_TYPE_EXCLUDING_TAX, 17.50, 100.00, 100.00],
            [false, false, TaxConfig::DISPLAY_TYPE_INCLUDING_TAX, 17.50, 100.00, 100.00],
            [false, false, TaxConfig::DISPLAY_TYPE_BOTH, 17.50, 100.00, 100.00],
            [false, true, TaxConfig::DISPLAY_TYPE_EXCLUDING_TAX, 17.50, 100.00, 100.00],
            [false, true, TaxConfig::DISPLAY_TYPE_INCLUDING_TAX, 17.50, 100.00, 100.00],
            [false, true, TaxConfig::DISPLAY_TYPE_BOTH, 17.50, 100.00, 100.00],
        ];
    }
}
