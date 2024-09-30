<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Plugin\Catalog\Model\ResourceModel\Attribute;

use Klevu\IndexingApi\Model\MagentoAttributeInterface;
use Magento\Eav\Model\ResourceModel\Entity\Attribute;
use Magento\Framework\Model\AbstractModel;

class SerializeCustomAttributeDataPlugin
{
    /**
     * @param Attribute $subject
     * @param AbstractModel $object
     *
     * @return AbstractModel[]
     */
    public function beforeSave(
        Attribute $subject, // phpcs:ignore SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter
        AbstractModel $object,
    ): array {
        $generateConfigurationForEntitySubtypes = $object->getData(
            MagentoAttributeInterface::ATTRIBUTE_PROPERTY_GENERATE_CONFIGURATION_FOR_ENTITY_SUBTYPES,
        );
        if (is_array($generateConfigurationForEntitySubtypes)) {
            $object->setData(
                MagentoAttributeInterface::ATTRIBUTE_PROPERTY_GENERATE_CONFIGURATION_FOR_ENTITY_SUBTYPES,
                implode(
                    separator: ',',
                    array: $generateConfigurationForEntitySubtypes,
                ),
            );
        }

        return [$object];
    }

    /**
     * @param Attribute $subject
     * @param mixed $return
     * @param AbstractModel $object
     *
     * @return void
     */
    public function afterLoad(
        Attribute $subject, // phpcs:ignore SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter
        mixed $return, // phpcs:ignore SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter
        AbstractModel $object,
    ): void {
        $this->unserializeGenerateConfigurationForEntitySubtypes($object);
    }

    /**
     * @param Attribute $subject
     * @param bool $return
     * @param AbstractModel $object
     * @param mixed $value
     * @param mixed $field
     *
     * @return bool
     */
    public function afterLoadByCode(
        Attribute $subject, // phpcs:ignore SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter
        bool $return,
        AbstractModel $object,
        mixed $value, // phpcs:ignore SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter
        mixed $field, // phpcs:ignore SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter
    ): bool {
        $this->unserializeGenerateConfigurationForEntitySubtypes($object);

        return $return;
    }

    /**
     * @param AbstractModel $object
     *
     * @return void
     */
    private function unserializeGenerateConfigurationForEntitySubtypes(
        AbstractModel $object,
    ): void {
        $generateConfigurationForEntitySubtypes = $object->getData(
            MagentoAttributeInterface::ATTRIBUTE_PROPERTY_GENERATE_CONFIGURATION_FOR_ENTITY_SUBTYPES,
        );
        if (!is_array($generateConfigurationForEntitySubtypes)) {
            $object->setData(
                MagentoAttributeInterface::ATTRIBUTE_PROPERTY_GENERATE_CONFIGURATION_FOR_ENTITY_SUBTYPES,
                array_filter(
                    explode(
                        separator: ',',
                        string: (string)$generateConfigurationForEntitySubtypes,
                    ),
                ),
            );
        }
    }
}
