<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Service\Mapper;

use Klevu\IndexingApi\Model\MagentoAttributeInterface;
use Klevu\IndexingApi\Service\Mapper\EntityAspectMapperServiceInterface;
use Klevu\IndexingApi\Service\Provider\Discovery\AttributeCollectionInterface;
use Klevu\IndexingProducts\Model\Source\Aspect;
use Magento\Eav\Api\Data\AttributeInterface;

class EntityAspectMapperService implements EntityAspectMapperServiceInterface
{
    /**
     * @var AttributeCollectionInterface
     */
    private readonly AttributeCollectionInterface $attributeCollection;

    /**
     * @param AttributeCollectionInterface $attributeCollection
     */
    public function __construct(AttributeCollectionInterface $attributeCollection)
    {
        $this->attributeCollection = $attributeCollection;
    }

    /**
     * @param string[] $attributeCodes
     *
     * @return Aspect[]
     */
    public function execute(array $attributeCodes): array
    {
        $attributeCollection = $this->attributeCollection->get();
        $attributeCollection->addFieldToFilter(
            'main_table.' . AttributeInterface::ATTRIBUTE_CODE,
            ['in' => implode(',', $attributeCodes)],
        );
        /** @var AttributeInterface[] $attributes */
        $attributes = $attributeCollection->getItems();

        return array_filter(
            array: array_map(
                callback: static fn (AttributeInterface $attribute): ?Aspect => (
                     Aspect::tryFrom(
                         value: (int)$attribute->getData( // @phpstan-ignore-line
                             key: MagentoAttributeInterface::ATTRIBUTE_PROPERTY_ASPECT_MAPPING,
                         ),
                     )
                ),
                array: $attributes,
            ),
        );
    }
}
