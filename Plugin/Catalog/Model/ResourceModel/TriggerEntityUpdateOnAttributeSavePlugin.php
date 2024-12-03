<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Plugin\Catalog\Model\ResourceModel;

use Klevu\Indexing\Model\Update\Entity as EntityUpdate;
use Klevu\IndexingApi\Model\MagentoAttributeInterface;
use Klevu\IndexingApi\Service\EntityUpdateResponderServiceInterface;
use Magento\Catalog\Api\Data\ProductAttributeInterface;
use Magento\Catalog\Model\ResourceModel\Eav\Attribute as EavAttributeResourceModel;
use Magento\Eav\Api\Data\AttributeInterface;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Eav\Model\ConfigFactory as EavConfigFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Psr\Log\LoggerInterface;

class TriggerEntityUpdateOnAttributeSavePlugin
{
    /**
     * @var EntityUpdateResponderServiceInterface
     */
    private readonly EntityUpdateResponderServiceInterface $responderService;
    /**
     * @var EavConfigFactory
     */
    private readonly EavConfigFactory $eavConfigFactory;
    /**
     * @var LoggerInterface
     */
    private readonly LoggerInterface $logger;

    /**
     * @param EntityUpdateResponderServiceInterface $responderService
     * @param EavConfigFactory $eavConfigFactory
     * @param LoggerInterface $logger
     */
    public function __construct(
        EntityUpdateResponderServiceInterface $responderService,
        EavConfigFactory $eavConfigFactory,
        LoggerInterface $logger,
    ) {
        $this->responderService = $responderService;
        $this->eavConfigFactory = $eavConfigFactory;
        $this->logger = $logger;
    }

    /**
     * @param AbstractDb $attributeResourceModel
     * @param EavAttributeResourceModel $result
     * @param AbstractModel $object
     *
     * @return EavAttributeResourceModel
     */
    public function afterSave(
        // phpcs:ignore SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter
        AbstractDb $attributeResourceModel,
        mixed $result,
        AbstractModel $object,
    ): mixed {
        if ($this->isUpdateRequired(attribute: $object)) {
            /** @var AbstractModel&AttributeInterface $object */
            $changedEntitySubtypes = $this->getChangedEntitySubtypes(attribute: $object);
            if ($changedEntitySubtypes) {
                $this->responderService->execute(data: [
                    EntityUpdate::ENTITY_SUBTYPES => $changedEntitySubtypes,
                ]);
            }
        }

        return $result;
    }

    /**
     * @param mixed $attribute
     *
     * @return bool
     */
    private function isUpdateRequired(mixed $attribute): bool
    {
        if (!($attribute instanceof AttributeInterface) || !($attribute instanceof AbstractModel)) {
            return false;
        }

        return (int)$attribute->getData(key: 'entity_type_id') === $this->getEntityTypeId();
    }

    /**
     * @return int|null
     */
    private function getEntityTypeId(): ?int
    {
        /** @var EavConfig $eavConfig */
        $eavConfig = $this->eavConfigFactory->create();
        try {
            $entityType = $eavConfig->getEntityType(code: ProductAttributeInterface::ENTITY_TYPE_CODE);
        } catch (LocalizedException $exception) {
            $this->logger->error(
                message: 'Method: {method}:{line}, Error: {message}',
                context: [
                    'method' => __METHOD__,
                    'line' => __LINE__,
                    'message' => $exception->getMessage(),
                ],
            );

            return null;
        }

        return $entityType->getEntityTypeId()
            ? (int)$entityType->getEntityTypeId()
            : null;
    }

    /**
     * @param AttributeInterface&AbstractModel $attribute
     *
     * @return string[]
     */
    private function getChangedEntitySubtypes(AbstractModel&AttributeInterface $attribute): array
    {
        if ($this->hasRegisterWithKlevuChanged(attribute: $attribute)) {
            return $this->isRegisterWithKlevuEnabled(attribute: $attribute)
                ? $this->getNewSubtypes(attribute: $attribute)
                : $this->getOriginalSubtypes(attribute: $attribute);
        }

        return $this->getChangedSubtypes(attribute: $attribute);
    }

    /**
     * @param AttributeInterface&AbstractModel $attribute
     *
     * @return bool
     */
    private function hasRegisterWithKlevuChanged(AbstractModel&AttributeInterface $attribute): bool
    {
        $wasRegisterWithKlevuEnabled = $this->wasRegisterWithKlevuEnabled(attribute: $attribute);
        $isRegisterWithKlevuEnabled = $this->isRegisterWithKlevuEnabled(attribute: $attribute);

        return $wasRegisterWithKlevuEnabled !== $isRegisterWithKlevuEnabled;
    }

    /**
     * @param AttributeInterface&AbstractModel $attribute
     *
     * @return int
     */
    private function wasRegisterWithKlevuEnabled(AbstractModel&AttributeInterface $attribute): int
    {
        return (int)$attribute->getOrigData(
            key: MagentoAttributeInterface::ATTRIBUTE_PROPERTY_IS_INDEXABLE,
        );
    }

    /**
     * @param AttributeInterface&AbstractModel $attribute
     *
     * @return int
     */
    private function isRegisterWithKlevuEnabled(AbstractModel&AttributeInterface $attribute): int
    {
        return (int)$attribute->getData(
            key: MagentoAttributeInterface::ATTRIBUTE_PROPERTY_IS_INDEXABLE,
        );
    }

    /**
     * @param AttributeInterface&AbstractModel $attribute
     *
     * @return string[]
     */
    private function getChangedSubtypes(AbstractModel&AttributeInterface $attribute): array
    {
        $originalSubTypes = $this->getOriginalSubtypes(attribute: $attribute);
        $newSubTypes = $this->getNewSubtypes(attribute: $attribute);

        return array_merge(
            array_diff($originalSubTypes, $newSubTypes),
            array_diff($newSubTypes, $originalSubTypes),
        );
    }

    /**
     * @param AttributeInterface&AbstractModel $attribute
     *
     * @return string[]
     */
    private function getOriginalSubtypes(AbstractModel&AttributeInterface $attribute): array
    {
        $originalSubTypesString = $attribute->getOrigData(
            key: MagentoAttributeInterface::ATTRIBUTE_PROPERTY_GENERATE_CONFIGURATION_FOR_ENTITY_SUBTYPES,
        );

        return is_string($originalSubTypesString)
            ? explode(separator: ',', string: $originalSubTypesString)
            : [];
    }

    /**
     * @param AttributeInterface&AbstractModel $attribute
     *
     * @return string[]
     */
    private function getNewSubtypes(AbstractModel&AttributeInterface $attribute): array
    {
        $newSubTypesString = $attribute->getData(
            key: MagentoAttributeInterface::ATTRIBUTE_PROPERTY_GENERATE_CONFIGURATION_FOR_ENTITY_SUBTYPES,
        );

        return is_string($newSubTypesString)
            ? explode(separator: ',', string: $newSubTypesString)
            : [];
    }
}
