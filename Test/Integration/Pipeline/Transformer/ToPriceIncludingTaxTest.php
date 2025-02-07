<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Test\Integration\Pipeline\Transformer;

use Klevu\IndexingProducts\Pipeline\Transformer\ToPriceIncludingTax;
use Klevu\Pipelines\Exception\Transformation\InvalidInputDataException;
use Klevu\Pipelines\Exception\Transformation\InvalidTransformationArgumentsException;
use Klevu\Pipelines\Model\Argument;
use Klevu\Pipelines\Model\ArgumentIterator;
use Klevu\Pipelines\Transformer\TransformerInterface;
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
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Framework\DataObject;
use Magento\Framework\ObjectManagerInterface;
use Magento\Tax\Model\Config as TaxConfig;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use TddWizard\Fixtures\Catalog\ProductFixturePool;
use TddWizard\Fixtures\Core\ConfigFixture;

/**
 * @covers ToPriceIncludingTax::class
 * @method TransformerInterface instantiateTestObject(?array $arguments = null)
 * @method TransformerInterface instantiateTestObjectFromInterface(?array $arguments = null)
 */
class ToPriceIncludingTaxTest extends TestCase
{
    use CustomerGroupTrait;
    use ObjectInstantiationTrait;
    use ProductTrait;
    use TaxClassTrait;
    use TaxRateTrait;
    use TaxRuleTrait;
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

        $this->implementationFqcn = ToPriceIncludingTax::class;
        $this->interfaceFqcn = TransformerInterface::class;
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
     * @dataProvider testTransform_ThrowsException_WhenInvalidDataType_dataProvider
     */
    public function testTransform_ThrowsException_WhenInvalidDataType(mixed $invalidData): void
    {
        $this->expectException(InvalidInputDataException::class);
        $this->expectExceptionMessage('Invalid input data for transformation');
        $transformer = $this->instantiateTestObject();
        $transformer->transform(data: $invalidData);
    }

    /**
     * @return mixed[]
     */
    public function testTransform_ThrowsException_WhenInvalidDataType_dataProvider(): array
    {
        return [
            ['string'],
            ['1234a'],
            [true],
            [new DataObject()],
            [[1.234]],
        ];
    }

    /**
     * @dataProvider testTransform_ThrowsException_WhenProductIsInvalid_dataProvider
     */
    public function testTransform_ThrowsException_WhenProductIsInvalid(mixed $invalidProduct): void
    {
        $this->expectException(InvalidTransformationArgumentsException::class);
        $this->expectExceptionMessage('Invalid argument for transformation');

        $argument0 = $this->objectManager->create(
            type: Argument::class,
            arguments: [
                'value' => $invalidProduct,
                'key' => 0,
            ],
        );
        $argument1 = $this->objectManager->create(
            type: Argument::class,
            arguments: [
                'value' => 1,
                'key' => 1,
            ],
        );
        $argumentIterator = $this->objectManager->create(
            type: ArgumentIterator::class,
            arguments: [
                'data' => [
                    $argument0,
                    $argument1,
                ],
            ],
        );

        $transformer = $this->instantiateTestObject();
        $transformer->transform(data: 1.23, arguments: $argumentIterator);
    }

    /**
     * @return mixed[]
     */
    public function testTransform_ThrowsException_WhenProductIsInvalid_dataProvider(): array
    {
        return [
            [1],
            [null],
            ['string'],
            [false],
            [true],
            [new DataObject()],
            [[1.234]],
        ];
    }

    /**
     * @dataProvider testTransform_ThrowsException_WhenCustomerGroupIdIsInvalid_dataProvider
     */
    public function testTransform_ThrowsException_WhenCustomerGroupIdIsInvalid(mixed $invalidCustomerGroup): void
    {
        $this->expectException(InvalidTransformationArgumentsException::class);
        $this->expectExceptionMessage('Invalid argument for transformation');

        $argument0 = $this->objectManager->create(
            type: Argument::class,
            arguments: [
                'value' => $this->objectManager->get(ProductInterface::class),
                'key' => 0,
            ],
        );
        $argument1 = $this->objectManager->create(
            type: Argument::class,
            arguments: [
                'value' => $invalidCustomerGroup,
                'key' => 1,
            ],
        );
        $argumentIterator = $this->objectManager->create(
            type: ArgumentIterator::class,
            arguments: [
                'data' => [
                    $argument0,
                    $argument1,
                ],
            ],
        );

        $transformer = $this->instantiateTestObject();
        $transformer->transform(data: 1.23, arguments: $argumentIterator);
    }

    /**
     * @return mixed[]
     */
    public function testTransform_ThrowsException_WhenCustomerGroupIdIsInvalid_dataProvider(): array
    {
        return [
            ['string'],
            [false],
            [true],
            [new DataObject()],
            [[1.234]],
        ];
    }

    public function testTransform_ReturnsNull_WhenPriceIsNull(): void
    {
        $transformer = $this->instantiateTestObject();
        $result = $transformer->transform(data: null);

        $this->assertNull(actual: $result);
    }

    /**
     * @magentoAppIsolation enabled
     * @magentoDbIsolation disabled
     * @dataProvider testTransform_ReturnsPriceIncludingTax_CustomerGroupNotSet_dataProvider
     */
    public function testTransform_ReturnsPriceIncludingTax_CustomerGroupNotSet(
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
        ]);
        $productFixture = $this->productFixturePool->get('test_product');

        $argument0 = $this->objectManager->create(
            type: Argument::class,
            arguments: [
                'value' => $productFixture->getProduct(),
                'key' => 0,
            ],
        );
        $argumentIterator = $this->objectManager->create(
            type: ArgumentIterator::class,
            arguments: [
                'data' => [
                    $argument0,
                ],
            ],
        );

        $transformer = $this->instantiateTestObject([]);
        $result = $transformer->transform(data: $initialValue, arguments: $argumentIterator);

        $this->assertSame(expected: $expectedValue, actual: $result);
    }

    /**
     * @return mixed[][]
     */
    public function testTransform_ReturnsPriceIncludingTax_CustomerGroupNotSet_dataProvider(): array
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
     * @magentoAppIsolation enabled
     * @magentoDbIsolation disabled
     * @dataProvider testTransform_ReturnsPriceIncludingTax_CustomerGroupSet_dataProvider
     */
    public function testTransform_ReturnsPriceIncludingTax_CustomerGroupSet(
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

        $argument0 = $this->objectManager->create(
            type: Argument::class,
            arguments: [
                'value' => $productFixture->getProduct(),
                'key' => 0,
            ],
        );
        $argument1 = $this->objectManager->create(
            type: Argument::class,
            arguments: [
                'value' => $customerGroupFixture->getId(),
                'key' => 1,
            ],
        );
        $argumentIterator = $this->objectManager->create(
            type: ArgumentIterator::class,
            arguments: [
                'data' => [
                    $argument0,
                    $argument1,
                ],
            ],
        );

        $transformer = $this->instantiateTestObject([]);
        $result = $transformer->transform(data: $initialValue, arguments: $argumentIterator);

        $this->assertSame(expected: $expectedValue, actual: $result);
    }

    /**
     * @return mixed[][]
     */
    public function testTransform_ReturnsPriceIncludingTax_CustomerGroupSet_dataProvider(): array
    {
        return [
            [true, false, TaxConfig::DISPLAY_TYPE_EXCLUDING_TAX, 15.0, 100.00, 100.00],
            [true, false, TaxConfig::DISPLAY_TYPE_INCLUDING_TAX, 15.0, 100.00, 115.00],
            [true, false, TaxConfig::DISPLAY_TYPE_BOTH, 15.0, 100.00, 115.00],
            [true, true, TaxConfig::DISPLAY_TYPE_EXCLUDING_TAX, 15.0, 100.00, 86.96],
            [true, true, TaxConfig::DISPLAY_TYPE_INCLUDING_TAX, 15.0, 100.00, 100.00],
            [true, true, TaxConfig::DISPLAY_TYPE_BOTH, 15.0, 100.00, 100.00],
            [false, false, TaxConfig::DISPLAY_TYPE_EXCLUDING_TAX, 15.0, 100.00, 100.00],
            [false, false, TaxConfig::DISPLAY_TYPE_INCLUDING_TAX, 15.0, 100.00, 100.00],
            [false, false, TaxConfig::DISPLAY_TYPE_BOTH, 15.0, 100.00, 100.00],
            [false, true, TaxConfig::DISPLAY_TYPE_EXCLUDING_TAX, 15.0, 100.00, 100.00],
            [false, true, TaxConfig::DISPLAY_TYPE_INCLUDING_TAX, 15.0, 100.00, 100.00],
            [false, true, TaxConfig::DISPLAY_TYPE_BOTH, 15.0, 100.00, 100.00],
        ];
    }
}
