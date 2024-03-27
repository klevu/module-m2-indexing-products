<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Validator;

use Klevu\IndexingApi\Validator\ValidatorInterface;
use Magento\Eav\Api\Data\AttributeInterface;
use Magento\Framework\Validator\AbstractValidator;

class IndexableAttributeValidator extends AbstractValidator implements ValidatorInterface
{
    /**
     * @var string[]
     */
    private array $unsupportedFrontendInput = [
        'weee',
        'media_image',
    ];

    /**
     * @param mixed $value
     *
     * @return bool
     */
    public function isValid(mixed $value): bool
    {
        $this->_clearMessages();

        return $this->validateType($value)
            && $this->validateFrontendInputIsSupported(attribute: $value);
    }

    /**
     * @param mixed $value
     *
     * @return bool
     */
    private function validateType(mixed $value): bool
    {
        if ($value instanceof AttributeInterface) {
            return true;
        }
        $this->_addMessages([
            __(
                'Invalid type provided. Expected %1, received %2.',
                AttributeInterface::class,
                get_debug_type($value),
            )->render(),
        ]);

        return false;
    }

    /**
     * @param AttributeInterface $attribute
     *
     * @return bool
     */
    private function validateFrontendInputIsSupported(AttributeInterface $attribute): bool
    {
        if (!in_array($attribute->getFrontendInput(), $this->unsupportedFrontendInput, true)) {
            return true;
        }
        $this->_addMessages([
            __(
                'The provided attribute (%1) frontend input (%2) is not supported for indexing with Klevu.',
                $attribute->getAttributeCode(),
                $attribute->getFrontendInput(),
            )->render(),
        ]);

        return false;
    }
}
