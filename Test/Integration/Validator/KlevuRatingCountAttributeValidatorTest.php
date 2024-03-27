<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Test\Integration\Validator;

use Klevu\IndexingApi\Validator\ValidatorInterface;
use Klevu\IndexingProducts\Model\Attribute\KlevuRatingCountInterface;
use Klevu\IndexingProducts\Validator\KlevuRatingCountAttributeValidator;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Magento\Catalog\Api\Data\ProductAttributeInterface;
use Magento\Eav\Api\AttributeRepositoryInterface;
use Magento\Eav\Api\Data\AttributeInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * @covers KlevuRatingCountAttributeValidator
 * @method KlevuRatingCountAttributeValidator instantiateTestObject(?array $arguments = null)
 */
class KlevuRatingCountAttributeValidatorTest extends TestCase
{
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

        $this->implementationFqcn = KlevuRatingCountAttributeValidator::class;
        $this->interfaceFqcn = ValidatorInterface::class;
        $this->objectManager = Bootstrap::getObjectManager();
    }

    /**
     * @testWith [1]
     *           [1.23]
     *           [true]
     *           [false]
     *           [["string"]]
     */
    public function testIsValid_ReturnsFalse_withMessages_WhenRequestedAttributeValueIsNoString(mixed $incorrectType,
    ): void {
        $validator = $this->instantiateTestObject();
        $this->assertFalse(condition: $validator->isValid($incorrectType));
        $this->assertTrue(condition: $validator->hasMessages());
        $this->assertContains(
            needle: sprintf(
                'Invalid type provided. Expected string, received %s.',
                get_debug_type($incorrectType),
            ),
            haystack: $validator->getMessages(),
        );
    }

    public function testIsValid_ReturnsFalse_withMessages_WhenAttributeMissing(): void
    {
        $mockAttributeRepository = $this->getMockBuilder(AttributeRepositoryInterface::class)
            ->getMock();
        $mockAttributeRepository->method('get')
            ->with(
                ProductAttributeInterface::ENTITY_TYPE_CODE,
                KlevuRatingCountInterface::ATTRIBUTE_CODE,
            )
            ->willThrowException(
                exception: new NoSuchEntityException(
                    phrase: __(
                        'The attribute with a "%1" attributeCode doesn\'t exist.'
                        . ' Verify the attribute and try again.',
                        KlevuRatingCountInterface::ATTRIBUTE_CODE,
                    ),
                ),
            );

        $this->objectManager->addSharedInstance(
            instance: $mockAttributeRepository,
            className: AttributeRepositoryInterface::class,
            forPreference: true,
        );

        // pass [] to instantiateTestObject to trigger create over get, more performant than @magentoAppIsolation
        $validator = $this->instantiateTestObject([]);
        $this->assertFalse(condition: $validator->isValid());
        $this->assertTrue(condition: $validator->hasMessages());
        $this->assertContains(
            needle: sprintf(
                'The attribute with a "%s" attributeCode doesn\'t exist.'
                . ' Verify the attribute and try again.',
                KlevuRatingCountInterface::ATTRIBUTE_CODE,
            ),
            haystack: $validator->getMessages(),
        );
    }

    /**
     * @testWith ["datetime"]
     *           ["decimal"]
     *           ["text"]
     *           ["varchar"]
     */
    public function testIsValid_ReturnsFalse_withMessages_WhenAttributeHasIncorrectBackendType(
        string $invalidBackendType,
    ): void {
        $mockAttribute = $this->getMockBuilder(AttributeInterface::class)
            ->getMock();
        $mockAttribute->expects($this->once())
            ->method('getBackendType')
            ->willReturn($invalidBackendType);

        $mockAttributeRepository = $this->getMockBuilder(AttributeRepositoryInterface::class)
            ->getMock();
        $mockAttributeRepository->method('get')
            ->willReturn($mockAttribute);

        $validator = $this->instantiateTestObject([
            'attributeRepository' => $mockAttributeRepository,
        ]);
        $this->assertFalse(condition: $validator->isValid());
        $this->assertTrue(condition: $validator->hasMessages());
        $this->assertContains(
            needle: sprintf(
                'Requested attribute %s, has incorrect backend type %s, expected %s.',
                KlevuRatingCountInterface::ATTRIBUTE_CODE,
                $invalidBackendType,
                'int',
            ),
            haystack: $validator->getMessages(),
        );
    }
}
