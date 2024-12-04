<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Plugin\Catalog\Model\ResourceModel;

use Magento\Eav\Api\Data\AttributeInterface;
use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Serialize\SerializerInterface;
use Psr\Log\LoggerInterface;

trait AttributeHasChangesTrait
{
    /**
     * As this plugin fires after the attribute has been saved
     *  calling $attribute->hasDataChanges() always returns false
     *
     * @param AbstractModel&AttributeInterface $attribute
     *
     * @return bool
     */
    private function hasDataChanges(AbstractModel&AttributeInterface $attribute): bool
    {
        if (
            !isset($this->propertiesToCheck, $this->logger, $this->serializer)
            || !(is_array($this->propertiesToCheck))
            || !($this->logger instanceof LoggerInterface)
            || !($this->serializer instanceof SerializerInterface)
        ) {
            throw new \LogicException(
                'Invalid configuration for AttributeHasChangesTrait: missing required dependencies',
            );
        }
        foreach ($this->propertiesToCheck as $propertyToCheck => $type) {
            try {
                switch ($type) {
                    case 'boolean':
                        $originalValue = $this->castToBoolean($attribute->getOrigData(key: $propertyToCheck));
                        $currentValue = $this->castToBoolean($attribute->getData(key: $propertyToCheck));
                        break;
                    case 'int':
                        $originalValue = (int)$attribute->getOrigData(key: $propertyToCheck);
                        $currentValue = (int)$attribute->getData(key: $propertyToCheck);
                        break;
                    case 'array':
                        $originalValue = $this->serializer->serialize(
                            array_filter($attribute->getOrigData(key: $propertyToCheck) ?? []),
                        );
                        $currentValue = $this->serializer->serialize(
                            array_filter($attribute->getData(key: $propertyToCheck) ?? []),
                        );
                        break;
                    case 'string':
                    default:
                        $originalValue = $attribute->getOrigData(key: $propertyToCheck);
                        $currentValue = $attribute->getData(key: $propertyToCheck);
                        break;
                }
                if ($originalValue !== $currentValue) {
                    return true;
                }
            } catch (\InvalidArgumentException $exception) {
                $this->logger->error(
                    message: 'Method: {method}, Error: {message}',
                    context: [
                        'method' => __METHOD__,
                        'line' => __LINE__,
                        'message' => $exception->getMessage(),
                    ],
                );
            }
        }

        return false;
    }

    /**
     * @param mixed $value
     *
     * @return bool
     */
    private function castToBoolean(mixed $value): bool
    {
        return $value === 'false' || (bool)$value;
    }
}
