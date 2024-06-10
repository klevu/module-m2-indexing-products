<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Test\Integration\Service\Provider\Catalog;

use Klevu\IndexingApi\Service\Provider\Catalog\AttributeTextProviderInterface;
use Klevu\IndexingProducts\Exception\InvalidAttributeCodeException;
use Klevu\IndexingProducts\Service\Provider\Catalog\AttributeTextProvider;
use Klevu\TestFixtures\Catalog\Attribute\AttributeFixturePool;
use Klevu\TestFixtures\Catalog\AttributeTrait;
use Klevu\TestFixtures\Catalog\ProductTrait;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Klevu\TestFixtures\Traits\TestInterfacePreferenceTrait;
use Magento\Eav\Model\Entity\Attribute\AbstractAttribute;
use Magento\Eav\Model\Entity\Attribute\AttributeInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use TddWizard\Fixtures\Catalog\ProductFixturePool;

/**
 * @covers AttributeTextProvider
 * @method AttributeTextProviderInterface instantiateTestObject(?array $arguments = null)
 * @method AttributeTextProviderInterface instantiateTestObjectFromInterface(?array $arguments = null)
 */
class AttributeTextProviderTest extends TestCase
{
    use AttributeTrait;
    use ObjectInstantiationTrait;
    use ProductTrait;
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

        $this->implementationFqcn = AttributeTextProvider::class;
        $this->interfaceFqcn = AttributeTextProviderInterface::class;
        $this->objectManager = Bootstrap::getObjectManager();
        $this->attributeFixturePool = $this->objectManager->get(AttributeFixturePool::class);
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
        $this->attributeFixturePool->rollback();
    }

    /**
     * @magentoDbIsolation disabled
     */
    public function testGet_ThrowsException_WhenAttributeCodeNotValid(): void
    {
        $invalidAttributeCode = 'wefdgd';

        $this->expectException(InvalidAttributeCodeException::class);
        $this->expectExceptionMessage(
            sprintf(
                'Invalid Attribute Code. Attribute Code "%s" not found.',
                $invalidAttributeCode,
            ),
        );

        $this->createProduct();
        $productFixture = $this->productFixturePool->get('test_product');

        $provider = $this->instantiateTestObject();
        $provider->get(
            product: $productFixture->getProduct(),
            attributeCode: $invalidAttributeCode,
        );
    }

    /**
     * @magentoDbIsolation disabled
     */
    public function testGet_ReturnsNull_WhenAttributeCodeNotSet(): void
    {
        $this->createAttribute([
            'attribute_type' => 'configurable',
            'options' => [
                '1' => 'Option 1',
                '2' => 'Option 2',
                '3' => 'Option 3',
            ],
        ]);
        $attributeFixture = $this->attributeFixturePool->get('test_attribute');

        $this->createProduct();
        $productFixture = $this->productFixturePool->get('test_product');

        $provider = $this->instantiateTestObject();
        $result = $provider->get(
            product: $productFixture->getProduct(),
            attributeCode: $attributeFixture->getAttributeCode(),
        );

        $this->assertNull($result);
    }

    /**
     * @magentoDbIsolation disabled
     */
    public function testGet_ReturnsNull_WhenAttributeCodeSetToNonExistentValue(): void
    {
        $this->createAttribute([
            'attribute_type' => 'configurable',
            'options' => [
                '1' => 'Option 1',
                '2' => 'Option 2',
                '3' => 'Option 3',
            ],
        ]);
        $attributeFixture = $this->attributeFixturePool->get('test_attribute');

        $this->createProduct([
            'data' => [
                $attributeFixture->getAttributeCode() => '999999999999999999',
            ],
        ]);
        $productFixture = $this->productFixturePool->get('test_product');

        $provider = $this->instantiateTestObject();
        $result = $provider->get(
            product: $productFixture->getProduct(),
            attributeCode: $attributeFixture->getAttributeCode(),
        );

        $this->assertNull($result);
    }

    /**
     * @magentoDbIsolation disabled
     */
    public function testGet_ReturnsString_WhenSelectAttributeIsSet(): void
    {
        $this->createAttribute([
            'attribute_type' => 'configurable',
            'options' => [
                '1' => 'Option 1',
                '2' => 'Option 2',
                '3' => 'Option 3',
            ],
        ]);
        $attributeFixture = $this->attributeFixturePool->get('test_attribute');
        /** @var AbstractAttribute&AttributeInterface $attribute */
        $attribute = $attributeFixture->getAttribute();
        $attributeSource = $attribute->getSource();

        $this->createProduct([
            'data' => [
                $attributeFixture->getAttributeCode() => $attributeSource->getOptionId('Option 1'),
            ],
        ]);
        $productFixture = $this->productFixturePool->get('test_product');

        $provider = $this->instantiateTestObject();
        $result = $provider->get(
            product: $productFixture->getProduct(),
            attributeCode: $attributeFixture->getAttributeCode(),
        );

        $this->assertSame(expected: 'Option 1', actual: $result);
    }

    /**
     * @magentoDbIsolation disabled
     */
    public function testGet_ReturnsArray_WhenMultiSelectAttributeIsSet(): void
    {
        $this->createAttribute([
            'attribute_type' => 'multiselect',
            'options' => [
                '1' => 'Option 1',
                '2' => 'Option 2',
                '3' => 'Option 3',
            ],
        ]);
        $attributeFixture = $this->attributeFixturePool->get('test_attribute');
        /** @var AbstractAttribute&AttributeInterface $attribute */
        $attribute = $attributeFixture->getAttribute();
        $attributeSource = $attribute->getSource();

        $this->createProduct([
            'data' => [
                $attributeFixture->getAttributeCode() => implode(
                    separator: ',',
                    array: [
                        $attributeSource->getOptionId('Option 1'),
                        $attributeSource->getOptionId('Option 3'),
                    ],
                ),
            ],
        ]);
        $productFixture = $this->productFixturePool->get('test_product');

        $provider = $this->instantiateTestObject();
        $result = $provider->get(
            product: $productFixture->getProduct(),
            attributeCode: $attributeFixture->getAttributeCode(),
        );

        $this->assertIsArray(actual: $result);
        $this->assertContains(needle: 'Option 1', haystack: $result);
        $this->assertContains(needle: 'Option 3', haystack: $result);
    }
}
