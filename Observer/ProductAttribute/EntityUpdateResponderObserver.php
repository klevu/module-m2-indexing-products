<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Observer\ProductAttribute;

use Klevu\Indexing\Model\Update\Entity as EntityUpdate;
use Klevu\IndexingApi\Model\MagentoAttributeInterface;
use Klevu\IndexingApi\Service\EntityUpdateResponderServiceInterface;
use Magento\Catalog\Api\Data\ProductAttributeInterface;
use Magento\Catalog\Model\ResourceModel\Eav\Attribute as EavAttribute;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Eav\Model\ConfigFactory as EavConfigFactory;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\LocalizedException;
use Psr\Log\LoggerInterface;

class EntityUpdateResponderObserver implements ObserverInterface
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
     * @var mixed[]
     */
    private array $storedData = [];

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
     * @param Observer $observer
     *
     * @return void
     */
    public function execute(Observer $observer): void
    {
        $event = $observer->getEvent();
        $attribute = $event->getData(key: 'attribute');
        if (!$this->isUpdateRequired(attribute: $attribute)) {
            return;
        }
        $changedEntitySubtypes = $this->getChangedEntitySubtypes(attribute: $attribute);
        if (!$changedEntitySubtypes) {
            return;
        }
        $this->responderService->execute(data: [
            EntityUpdate::ENTITY_SUBTYPES => $changedEntitySubtypes,
        ]);
    }

    /**
     * @param mixed $attribute
     *
     * @return bool
     */
    private function isUpdateRequired(mixed $attribute): bool
    {
        if (!($attribute instanceof EavAttribute)) {
            return false;
        }
        if ((int)$attribute->getData(key: 'entity_type_id') !== $this->getEntityTypeId()) {
            return false;
        }

        return $attribute->hasDataChanges() || $attribute->isDeleted();
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
                message: 'Method: {method}, Error: {message}',
                context: [
                    'method' => __METHOD__,
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
     * @param EavAttribute $attribute
     *
     * @return string[]
     */
    private function getChangedEntitySubtypes(EavAttribute $attribute): array
    {
        if ($this->hasRegisterWithKlevuChanged(attribute: $attribute)) {
            return $this->isRegisterWithKlevuEnabled(attribute: $attribute)
                ? $this->getNewSubtypes(attribute: $attribute)
                : $this->getOriginalSubtypes(attribute: $attribute);
        }

        return $this->getChangedSubtypes(attribute: $attribute);
    }

    /**
     * @param EavAttribute $attribute
     *
     * @return bool
     */
    private function hasRegisterWithKlevuChanged(EavAttribute $attribute): bool
    {
        $wasRegisterWithKlevuEnabled = $this->wasRegisterWithKlevuEnabled(attribute: $attribute);
        $isRegisterWithKlevuEnabled = $this->isRegisterWithKlevuEnabled(attribute: $attribute);

        return $wasRegisterWithKlevuEnabled !== $isRegisterWithKlevuEnabled;
    }

    /**
     * @param EavAttribute $attribute
     *
     * @return string[]
     */
    private function getChangedSubtypes(EavAttribute $attribute): array
    {
        $originalSubTypes = $this->getOriginalSubtypes(attribute: $attribute);
        $newSubTypes = $this->getNewSubtypes(attribute: $attribute);

        return array_merge(
            array_diff($originalSubTypes, $newSubTypes),
            array_diff($newSubTypes, $originalSubTypes),
        );
    }

    /**
     * @param EavAttribute $attribute
     * @param string $key
     *
     * @return mixed
     */
    private function getStoredData(EavAttribute $attribute, string $key): mixed
    {
        if (!($this->storedData[$attribute->getAttributeId()] ?? [])) {
            $this->storedData[$attribute->getAttributeId()] = $attribute->getStoredData();
        }

        return $this->storedData[$attribute->getAttributeId()][$key] ?? null;
    }

    /**
     * @param EavAttribute $attribute
     *
     * @return string[]
     */
    private function getOriginalSubtypes(EavAttribute $attribute): array
    {
        $originalSubTypesString = $this->getStoredData(
            attribute: $attribute,
            key: MagentoAttributeInterface::ATTRIBUTE_PROPERTY_GENERATE_CONFIGURATION_FOR_ENTITY_SUBTYPES,
        );

        return is_string($originalSubTypesString)
            ? explode(separator: ',', string: $originalSubTypesString)
            : [];
    }

    /**
     * @param EavAttribute $attribute
     *
     * @return string[]
     */
    private function getNewSubtypes(EavAttribute $attribute): array
    {
        $newSubTypesString = $attribute->getData(
            key: MagentoAttributeInterface::ATTRIBUTE_PROPERTY_GENERATE_CONFIGURATION_FOR_ENTITY_SUBTYPES,
        );

        return is_string($newSubTypesString)
            ? explode(separator: ',', string: $newSubTypesString)
            : [];
    }

    /**
     * @param EavAttribute $attribute
     *
     * @return int
     */
    private function wasRegisterWithKlevuEnabled(EavAttribute $attribute): int
    {
        return (int)$this->getStoredData(
            attribute: $attribute,
            key: MagentoAttributeInterface::ATTRIBUTE_PROPERTY_IS_INDEXABLE,
        );
    }

    /**
     * @param EavAttribute $attribute
     *
     * @return int
     */
    private function isRegisterWithKlevuEnabled(EavAttribute $attribute): int
    {
        return (int)$attribute->getData(
            key: MagentoAttributeInterface::ATTRIBUTE_PROPERTY_IS_INDEXABLE,
        );
    }
}
