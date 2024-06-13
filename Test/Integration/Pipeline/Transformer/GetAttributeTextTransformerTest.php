<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Test\Integration\Pipeline\Transformer;

use Klevu\IndexingProducts\Pipeline\Transformer\GetAttributeText;
use Klevu\Pipelines\Exception\Transformation\InvalidInputDataException;
use Klevu\Pipelines\Exception\TransformationException;
use Klevu\Pipelines\Model\Argument;
use Klevu\Pipelines\Model\ArgumentIterator;
use Klevu\Pipelines\Transformer\TransformerInterface;
use Klevu\TestFixtures\Catalog\Attribute\AttributeFixturePool;
use Klevu\TestFixtures\Catalog\AttributeTrait;
use Klevu\TestFixtures\Catalog\ProductTrait;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Magento\Eav\Model\Entity\Attribute\AbstractAttribute;
use Magento\Eav\Model\Entity\Attribute\AttributeInterface;
use Magento\Framework\DataObject;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use TddWizard\Fixtures\Catalog\ProductFixturePool;

/**
 * @covers GetAttributeText
 * @method TransformerInterface instantiateTestObject(?array $arguments = null)
 * @method TransformerInterface instantiateTestObjectFromInterface(?array $arguments = null)
 */
class GetAttributeTextTransformerTest extends TestCase
{
    use AttributeTrait;
    use ObjectInstantiationTrait;
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

        $this->implementationFqcn = GetAttributeText::class;
        $this->interfaceFqcn = TransformerInterface::class;
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
     * @dataProvider testTransform_ThrowsException_WhenInvalidDataType_dataProvider
     */
    public function testTransform_ThrowsException_WhenInvalidDataType(mixed $invalidData): void
    {
        $this->expectException(InvalidInputDataException::class);
        $transformer = $this->instantiateTestObject();
        $transformer->transform(data: $invalidData);
    }

    /**
     * @return mixed[]
     */
    public function testTransform_ThrowsException_WhenInvalidDataType_dataProvider(): array
    {
        return [
            [null],
            ['string'],
            [1],
            [1.23],
            [true],
            [new DataObject()],
        ];
    }

    /**
     * @magentoDbIsolation disabled
     */
    public function testTransform_ThrowsException_WhenAttributeCodeNotValid(): void
    {
        $invalidAttributeCode = 'uehfiuehf';

        $this->expectException(TransformationException::class);
        $this->expectExceptionMessage(
            sprintf(
                'Invalid Attribute Code. Attribute Code "%s" not found.',
                $invalidAttributeCode,
            ),
        );

        $this->createProduct();
        $productFixture = $this->productFixturePool->get('test_product');

        $argument = $this->objectManager->create(
            type: Argument::class,
            arguments: [
                'value' => $invalidAttributeCode,
                'key' => 0,
            ],
        );
        $argumentIterator = $this->objectManager->create(
            type: ArgumentIterator::class,
            arguments: [
                'data' => [$argument],
            ],
        );

        $transformer = $this->instantiateTestObject();
        $transformer->transform(
            data: $productFixture->getProduct(),
            arguments: $argumentIterator,
        );
    }

    /**
     * @magentoDbIsolation disabled
     */
    public function testTransform_ReturnsNull_WhenAttributeCodeNotSet(): void
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

        $argument = $this->objectManager->create(
            type: Argument::class,
            arguments: [
                'value' => $attributeFixture->getAttributeCode(),
                'key' => 0,
            ],
        );
        $argumentIterator = $this->objectManager->create(
            type: ArgumentIterator::class,
            arguments: [
                'data' => [$argument],
            ],
        );

        $transformer = $this->instantiateTestObject();
        $result = $transformer->transform(
            data: $productFixture->getProduct(),
            arguments: $argumentIterator,
        );

        $this->assertNull(actual: $result);
    }

    /**
     * @magentoDbIsolation disabled
     */
    public function testTransform_ReturnsNull_WhenAttributeCodeSetToNonExistentValue(): void
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

        $argument = $this->objectManager->create(
            type: Argument::class,
            arguments: [
                'value' => $attributeFixture->getAttributeCode(),
                'key' => 0,
            ],
        );
        $argumentIterator = $this->objectManager->create(
            type: ArgumentIterator::class,
            arguments: [
                'data' => [$argument],
            ],
        );

        $transformer = $this->instantiateTestObject();
        $result = $transformer->transform(
            data: $productFixture->getProduct(),
            arguments: $argumentIterator,
        );

        $this->assertNull(actual: $result);
    }

    /**
     * @magentoDbIsolation disabled
     */
    public function testTransform_ReturnsString_WhenSelectAttributeIsSet(): void
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
                $attributeFixture->getAttributeCode() => $attributeSource->getOptionId('Option 2'),
            ],
        ]);
        $productFixture = $this->productFixturePool->get('test_product');

        $argument = $this->objectManager->create(
            type: Argument::class,
            arguments: [
                'value' => $attributeFixture->getAttributeCode(),
                'key' => 0,
            ],
        );
        $argumentIterator = $this->objectManager->create(
            type: ArgumentIterator::class,
            arguments: [
                'data' => [$argument],
            ],
        );

        $transformer = $this->instantiateTestObject();
        $result = $transformer->transform(
            data: $productFixture->getProduct(),
            arguments: $argumentIterator,
        );

        $this->assertSame(expected: 'Option 2', actual: $result);
    }

    /**
     * @magentoDbIsolation disabled
     */
    public function testTransform_ReturnsArray_WhenMultiSelectAttributeIsSet(): void
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

        $argument = $this->objectManager->create(
            type: Argument::class,
            arguments: [
                'value' => $attributeFixture->getAttributeCode(),
                'key' => 0,
            ],
        );
        $argumentIterator = $this->objectManager->create(
            type: ArgumentIterator::class,
            arguments: [
                'data' => [$argument],
            ],
        );

        $transformer = $this->instantiateTestObject();
        $result = $transformer->transform(
            data: $productFixture->getProduct(),
            arguments: $argumentIterator,
        );

        $this->assertIsArray(actual: $result);
        $this->assertContains(needle: 'Option 1', haystack: $result);
        $this->assertContains(needle: 'Option 3', haystack: $result);
    }
}
