<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Plugin\Catalog\Model\ResourceModel;

use Klevu\IndexingApi\Service\RegenerateConfigurationOverridesInterface;
use Magento\Catalog\Api\Data\ProductAttributeInterface;
use Magento\Catalog\Model\ResourceModel\Eav\Attribute as EavAttributeResourceModel;
use Magento\Eav\Api\Data\AttributeInterface;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Eav\Model\ConfigFactory as EavConfigFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Magento\Framework\Serialize\SerializerInterface;
use Psr\Log\LoggerInterface;

class RegeneratePipelineOverridesOnAttributeSavePlugin
{
    use AttributeHasChangesTrait;

    /**
     * @var RegenerateConfigurationOverridesInterface
     */
    private readonly RegenerateConfigurationOverridesInterface $regenerateConfigurationOverrides;
    /**
     * @var EavConfigFactory
     */
    private readonly EavConfigFactory $eavConfigFactory;
    /**
     * @var LoggerInterface
     */
    private readonly LoggerInterface $logger;
    /**
     * @var SerializerInterface
     */
    private readonly SerializerInterface $serializer;
    /**
     * @var array<string, string>
     */
    private array $propertiesToCheck = [];

    /**
     * @param RegenerateConfigurationOverridesInterface $regenerateConfigurationOverrides
     * @param EavConfigFactory $eavConfigFactory
     * @param LoggerInterface $logger
     * @param SerializerInterface $serializer
     * @param array<string, string> $propertiesToCheck
     */
    public function __construct(
        RegenerateConfigurationOverridesInterface $regenerateConfigurationOverrides,
        EavConfigFactory $eavConfigFactory,
        LoggerInterface $logger,
        SerializerInterface $serializer,
        array $propertiesToCheck = [],
    ) {
        $this->regenerateConfigurationOverrides = $regenerateConfigurationOverrides;
        $this->eavConfigFactory = $eavConfigFactory;
        $this->logger = $logger;
        $this->serializer = $serializer;
        array_walk($propertiesToCheck, [$this, 'addPropertyToCheck']);
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
            $this->regenerateConfigurationOverrides->execute();
        }

        return $result;
    }

    /**
     * @param string $propertyToCheck
     * @param string $key
     *
     * @return void
     */
    private function addPropertyToCheck(string $propertyToCheck, string $key): void
    {
        $this->propertiesToCheck[$key] = $propertyToCheck;
    }

    /**
     * @param AbstractModel $attribute
     *
     * @return bool
     */
    private function isUpdateRequired(AbstractModel $attribute): bool
    {
        if (!($attribute instanceof AttributeInterface)) {
            return false;
        }
        /** @var AbstractModel&AttributeInterface $attribute */
        if ((int)$attribute->getData(key: 'entity_type_id') !== $this->getEntityTypeId()) {
            return false;
        }

        return $this->hasDataChanges(attribute: $attribute);
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
}
