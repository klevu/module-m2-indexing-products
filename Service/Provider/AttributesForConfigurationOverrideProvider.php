<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Service\Provider;

use Klevu\Indexing\Exception\AttributeMappingMissingException;
use Klevu\IndexingApi\Api\ConvertEavAttributeToIndexingAttributeActionInterface;
use Klevu\IndexingApi\Model\MagentoAttributeInterface;
use Klevu\IndexingApi\Service\Provider\AttributeProviderInterface;
use Klevu\IndexingApi\Service\Provider\Discovery\AttributeCollectionInterface;
use Magento\Eav\Api\Data\AttributeInterface;
use Magento\Framework\Exception\NoSuchEntityException;

class AttributesForConfigurationOverrideProvider implements AttributeProviderInterface
{
    /**
     * @var AttributeCollectionInterface
     */
    private readonly AttributeCollectionInterface $attributeCollection;
    /**
     * @var ConvertEavAttributeToIndexingAttributeActionInterface
     */
    private readonly ConvertEavAttributeToIndexingAttributeActionInterface $convertEavAttributeToIndexingAttributeAction; // phpcs:ignore Generic.Files.LineLength.TooLong

    /**
     * @param AttributeCollectionInterface $attributeCollection
     * @param ConvertEavAttributeToIndexingAttributeActionInterface $convertEavAttributeToIndexingAttributeAction
     */
    public function __construct(
        AttributeCollectionInterface $attributeCollection,
        ConvertEavAttributeToIndexingAttributeActionInterface $convertEavAttributeToIndexingAttributeAction,
    ) {
        $this->attributeCollection = $attributeCollection;
        $this->convertEavAttributeToIndexingAttributeAction = $convertEavAttributeToIndexingAttributeAction;
    }

    /**
     * @param int[]|null $attributeIds
     *
     * @return \Generator<MagentoAttributeInterface>
     * @throws AttributeMappingMissingException
     * @throws NoSuchEntityException
     */
    public function get(?array $attributeIds = []): \Generator
    {
        $collection = $this->attributeCollection->get($attributeIds);
        $collection->addFieldToFilter(
            field: MagentoAttributeInterface::ATTRIBUTE_PROPERTY_IS_INDEXABLE,
            condition: ['eq' => 1],
        );
        $collection->addFieldToFilter(
            field: MagentoAttributeInterface::ATTRIBUTE_PROPERTY_GENERATE_CONFIGURATION_FOR_ENTITY_SUBTYPES,
            condition: ['neq' => ''],
        );

        /** @var AttributeInterface $attribute */
        foreach ($collection as $attribute) {
            yield $this->convertEavAttributeToIndexingAttributeAction->execute(
                entityType: 'KLEVU_PRODUCT',
                attribute: $attribute,
                store: null,
            );
        }
    }
}
