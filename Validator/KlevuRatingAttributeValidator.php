<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Validator;

use Klevu\IndexingApi\Validator\ValidatorInterface;
use Klevu\IndexingProducts\Model\Attribute\KlevuRatingInterface;
use Magento\Catalog\Api\Data\ProductAttributeInterface;
use Magento\Eav\Api\AttributeRepositoryInterface;
use Magento\Eav\Api\Data\AttributeInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Validator\AbstractValidator;

class KlevuRatingAttributeValidator extends AbstractValidator implements ValidatorInterface
{
    /**
     * @var AttributeRepositoryInterface
     */
    private readonly AttributeRepositoryInterface $attributeRepository;
    /**
     * @var AttributeInterface|null
     */
    private ?AttributeInterface $attribute = null;

    /**
     * @param AttributeRepositoryInterface $attributeRepository
     */
    public function __construct(
        AttributeRepositoryInterface $attributeRepository,
    ) {
        $this->attributeRepository = $attributeRepository;
    }

    /**
     * @param mixed $value
     *
     * @return bool
     */
    public function isValid(mixed $value = KlevuRatingInterface::ATTRIBUTE_CODE): bool
    {
        $this->_clearMessages();

        return $this->validateAttributeNameIsString($value)
            && $this->validateAttributeExists($value)
            && $this->validateAttributeType($value);
    }

    /**
     * @param mixed $attributeCode
     *
     * @return bool
     */
    private function validateAttributeNameIsString(mixed $attributeCode): bool
    {
        if (is_string($attributeCode)) {
            return true;
        }
        $this->_addMessages([
            __(
                'Invalid type provided. Expected string, received %1.',
                get_debug_type($attributeCode),
            )->render(),
        ]);

        return false;
    }

    /**
     * @param string $attributeCode
     *
     * @return bool
     */
    private function validateAttributeExists(string $attributeCode): bool
    {
        $attribute = $this->getAttribute($attributeCode);
        if (null === $attribute) {
            $this->_addMessages([
                __(
                    'The attribute with a "%1" attributeCode doesn\'t exist.'
                    . ' Verify the attribute and try again.',
                    $attributeCode,
                )->render(),
            ]);

            return false;
        }

        return true;
    }

    /**
     * @param string $attributeCode
     *
     * @return bool
     */
    private function validateAttributeType(string $attributeCode): bool
    {
        $attribute = $this->getAttribute($attributeCode);
        $expectedBackendType = 'decimal';
        $actualBackendType = $attribute?->getBackendType();
        if ($expectedBackendType !== $actualBackendType) {
            $this->_addMessages([
                __(
                    'Requested attribute %1, has incorrect backend type %2, expected %3.',
                    $attributeCode,
                    $actualBackendType,
                    $expectedBackendType,
                )->render(),
            ]);

            return false;
        }

        return true;
    }

    /**
     * @param string $attributeCode
     *
     * @return AttributeInterface|null
     */
    private function getAttribute(string $attributeCode): ?AttributeInterface
    {
        if (null === $this->attribute) {
            try {
                $this->attribute = $this->attributeRepository->get(
                    entityTypeCode: ProductAttributeInterface::ENTITY_TYPE_CODE,
                    attributeCode: $attributeCode,
                );
            } catch (NoSuchEntityException) {
                // this is fine, we pass a message back in the validator messages, the calling code will handle it
            }
        }

        return $this->attribute;
    }
}
