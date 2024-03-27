<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Test\Integration\Validator;

use Klevu\IndexingApi\Validator\ValidatorInterface;
use Klevu\IndexingProducts\Validator\IndexableAttributeValidator;
use Klevu\TestFixtures\Catalog\Attribute\AttributeFixturePool;
use Klevu\TestFixtures\Catalog\AttributeTrait;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Magento\Eav\Api\Data\AttributeInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

class IndexableAttributeValidatorTest extends TestCase
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

        $this->implementationFqcn = IndexableAttributeValidator::class;
        $this->interfaceFqcn = ValidatorInterface::class;
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

    /**
     * @testWith [1]
     *           [1.23]
     *           [true]
     *           [false]
     *           [["string"]]
     *           [null]
     */
    public function testIsValid_ReturnsFalse_withMessage_WhenValueIsNotAttribute(mixed $incorrectType): void
    {
        $validator = $this->instantiateTestObject();
        $this->assertFalse(condition: $validator->isValid($incorrectType));
        $this->assertTrue(condition: $validator->hasMessages());
        $this->assertContains(
            needle: sprintf(
                'Invalid type provided. Expected %s, received %s.',
                AttributeInterface::class,
                get_debug_type($incorrectType),
            ),
            haystack: $validator->getMessages(),
        );
    }

    /**
     * @testWith ["weee"]
     *           ["image"]
     */
    public function testIsValid_ReturnsFalse_WhenAttributeTypeIsNotSupported(string $invalidAttributeType): void
    {
        $this->createAttribute([
            'attribute_type' => $invalidAttributeType,
        ]);
        $attributeFixture = $this->attributeFixturePool->get('test_attribute');
        $attribute = $attributeFixture->getAttribute();

        $validator = $this->instantiateTestObject();
        $this->assertFalse(condition: $validator->isValid($attribute));
        $this->assertTrue(condition: $validator->hasMessages());
        $this->assertContains(
            needle: sprintf(
                'The provided attribute (%s) frontend input (%s) is not supported for indexing with Klevu.',
                $attribute->getAttributeCode(),
                $attribute->getFrontendInput(),
            ),
            haystack: $validator->getMessages(),
        );
    }

}
