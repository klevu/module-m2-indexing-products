<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Test\Integration\Service\Mapper;

use Klevu\Indexing\Service\Mapper\AttributeTypeMapperService;
use Klevu\IndexingApi\Service\Mapper\AttributeTypeMapperServiceInterface;
use Klevu\IndexingProducts\Service\Mapper\AttributeTypeMapperService as AttributeTypeMapperServiceVirtualType;
use Klevu\PhpSDK\Model\Indexing\DataType;
use Klevu\TestFixtures\Catalog\Attribute\AttributeFixturePool;
use Klevu\TestFixtures\Catalog\AttributeTrait;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

class AttributeTypeMapperServiceTest extends TestCase
{
    use AttributeTrait;
    use ObjectInstantiationTrait;
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

        $this->implementationFqcn = AttributeTypeMapperServiceVirtualType::class;
        $this->interfaceFqcn = AttributeTypeMapperServiceInterface::class;
        $this->implementationForVirtualType = AttributeTypeMapperService::class;
        $this->objectManager = Bootstrap::getObjectManager();
        $this->attributeFixturePool = $this->objectManager->get(AttributeFixturePool::class);
    }

    /**
     * @return void
     * @throws \Exception
     */
    protected function tearDown(): void
    {
        parent::tearDown();

        $this->attributeFixturePool->rollback();
    }

    public function testExecute_ReturnsString_ForTextAttribute(): void
    {
        $this->createAttribute([
            'key' => 'klevu_test_text_attribute',
            'code' => 'klevu_test_text_attribute',
            'attribute_type' => 'text',
        ]);
        $attributeFixture = $this->attributeFixturePool->get('klevu_test_text_attribute');
        $magentoAttribute = $attributeFixture->getAttribute();

        $service = $this->instantiateTestObject();
        $dataType = $service->execute($magentoAttribute);

        $this->assertSame(
            expected: DataType::STRING,
            actual: $dataType,
            message: sprintf('Expected %s, Received %s', DataType::STRING->value, $dataType->value),
        );
    }

    public function testExecute_ReturnsString_ForTextareaAttribute(): void
    {
        $this->createAttribute([
            'key' => 'klevu_test_textarea_attribute',
            'code' => 'klevu_test_textarea_attribute',
            'attribute_type' => 'textarea',
        ]);
        $attributeFixture = $this->attributeFixturePool->get('klevu_test_textarea_attribute');
        $magentoAttribute = $attributeFixture->getAttribute();

        $service = $this->instantiateTestObject();
        $dataType = $service->execute($magentoAttribute);

        $this->assertSame(
            expected: DataType::STRING,
            actual: $dataType,
            message: sprintf('Expected %s, Received %s', DataType::STRING->value, $dataType->value),
        );
    }

    public function testExecute_ReturnsBoolean_ForBooleanAttribute(): void
    {
        $this->createAttribute([
            'key' => 'klevu_test_boolean_attribute',
            'code' => 'klevu_test_boolean_attribute',
            'attribute_type' => 'boolean',
        ]);
        $attributeFixture = $this->attributeFixturePool->get('klevu_test_boolean_attribute');
        $magentoAttribute = $attributeFixture->getAttribute();

        $service = $this->instantiateTestObject();
        $dataType = $service->execute($magentoAttribute);

        $this->assertSame(
            expected: DataType::STRING,
            actual: $dataType,
            message: sprintf('Expected %s, Received %s', DataType::STRING->value, $dataType->value),
        );
    }

    public function testExecute_ReturnsNumber_ForPriceAttribute(): void
    {
        $this->createAttribute([
            'key' => 'klevu_test_price_attribute',
            'code' => 'klevu_test_price_attribute',
            'attribute_type' => 'price',
        ]);
        $attributeFixture = $this->attributeFixturePool->get('klevu_test_price_attribute');
        $magentoAttribute = $attributeFixture->getAttribute();

        $service = $this->instantiateTestObject();
        $dataType = $service->execute($magentoAttribute);

        $this->assertSame(
            expected: DataType::STRING,
            actual: $dataType,
            message: sprintf('Expected %s, Received %s', DataType::STRING->value, $dataType->value),
        );
    }

    public function testExecute_ReturnsDateTime_ForDateAttribute(): void
    {
        $this->createAttribute([
            'key' => 'klevu_test_date_attribute',
            'code' => 'klevu_test_date_attribute',
            'attribute_type' => 'date',
        ]);
        $attributeFixture = $this->attributeFixturePool->get('klevu_test_date_attribute');
        $magentoAttribute = $attributeFixture->getAttribute();

        $service = $this->instantiateTestObject();
        $dataType = $service->execute($magentoAttribute);

        $this->assertSame(
            expected: DataType::STRING,
            actual: $dataType,
            message: sprintf('Expected %s, Received %s', DataType::STRING->value, $dataType->value),
        );
    }

    public function testExecute_ReturnsMultiValue_ForSelectVarcharAttribute(): void
    {
        $this->createAttribute([
            'key' => 'klevu_test_select_attribute',
            'code' => 'klevu_test_select_attribute',
            'attribute_type' => 'custom',
            'data' => [
                'frontend_input' => 'select',
                'backend_type' => 'varchar',
            ],
        ]);
        $attributeFixture = $this->attributeFixturePool->get('klevu_test_select_attribute');
        $magentoAttribute = $attributeFixture->getAttribute();

        $service = $this->instantiateTestObject();
        $dataType = $service->execute($magentoAttribute);

        $this->assertSame(
            expected: DataType::STRING,
            actual: $dataType,
            message: sprintf('Expected %s, Received %s', DataType::STRING->value, $dataType->value),
        );
    }

    /**
     * @TODO change return type to Enum when it is supported in Klevu Indexing
     *  This test covers swatches as well as selects with data type int
     */
    public function testExecute_ReturnsMultiValue_ForSelectIntAttribute(): void
    {
        $this->createAttribute([
            'key' => 'klevu_test_select_attribute',
            'code' => 'klevu_test_select_attribute',
            'attribute_type' => 'custom',
            'data' => [
                'frontend_input' => 'select',
                'backend_type' => 'int',
            ],
        ]);
        $attributeFixture = $this->attributeFixturePool->get('klevu_test_select_attribute');
        $magentoAttribute = $attributeFixture->getAttribute();

        $service = $this->instantiateTestObject();
        $dataType = $service->execute($magentoAttribute);

        $this->assertSame(
            expected: DataType::STRING,
            actual: $dataType,
            message: sprintf('Expected %s, Received %s', DataType::STRING->value, $dataType->value),
        );
    }

    public function testExecute_ReturnsMultiValue_ForMultiselectAttribute(): void
    {
        $this->createAttribute([
            'key' => 'klevu_test_multiselect_attribute',
            'code' => 'klevu_test_multiselect_attribute',
            'attribute_type' => 'multiselect',
        ]);
        $attributeFixture = $this->attributeFixturePool->get('klevu_test_multiselect_attribute');
        $magentoAttribute = $attributeFixture->getAttribute();

        $service = $this->instantiateTestObject();
        $dataType = $service->execute($magentoAttribute);

        $this->assertSame(
            expected: DataType::MULTIVALUE,
            actual: $dataType,
            message: sprintf('Expected %s, Received %s', DataType::MULTIVALUE->value, $dataType->value),
        );
    }

    public function testExecute_ReturnsString_ForImageAttribute(): void
    {
        $this->createAttribute([
            'key' => 'klevu_test_multiselect_attribute',
            'code' => 'klevu_test_multiselect_attribute',
            'attribute_type' => 'image',
        ]);
        $attributeFixture = $this->attributeFixturePool->get('klevu_test_multiselect_attribute');
        $magentoAttribute = $attributeFixture->getAttribute();

        $service = $this->instantiateTestObject();
        $dataType = $service->execute($magentoAttribute);

        $this->assertSame(
            expected: DataType::STRING,
            actual: $dataType,
            message: sprintf('Expected %s, Received %s', DataType::STRING->value, $dataType->value),
        );
    }

    public function testExecute_ReturnsCustomMappingValues(): void
    {
        $this->createAttribute([
            'key' => 'klevu_test_text_attribute',
            'code' => 'klevu_test_text_attribute',
            'attribute_type' => 'text',
        ]);
        $attributeFixture = $this->attributeFixturePool->get('klevu_test_text_attribute');
        $magentoAttribute = $attributeFixture->getAttribute();

        $service = $this->instantiateTestObject([
            'customMapping' => [
                'klevu_test_text_attribute' => 'NUMBER',
            ],
        ]);
        $dataType = $service->execute($magentoAttribute);

        $this->assertSame(
            expected: DataType::STRING,
            actual: $dataType,
            message: sprintf('Expected %s, Received %s', DataType::STRING->value, $dataType->value),
        );
    }
}
