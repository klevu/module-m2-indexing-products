<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Service;

use Klevu\Configuration\Service\Provider\StoreLocaleCodesProviderInterface;
use Klevu\Indexing\Exception\AttributeMappingMissingException;
use Klevu\IndexingApi\Service\AttributeIndexingRecordCreatorServiceInterface;
use Klevu\IndexingApi\Service\Mapper\AttributeTypeMapperServiceInterface;
use Klevu\IndexingApi\Service\Mapper\MagentoToKlevuAttributeMapperInterface;
use Klevu\PhpSDK\Api\Model\Indexing\AttributeInterface as SdkAttributeInterface;
use Klevu\PhpSDK\Model\Indexing\AttributeFactory;
use Klevu\PhpSDK\Model\Indexing\DataType;
use Magento\Eav\Api\Data\AttributeInterface;

class AttributeIndexingRecordCreatorService implements AttributeIndexingRecordCreatorServiceInterface
{
    /**
     * @var AttributeFactory
     */
    private readonly AttributeFactory $attributeFactory;
    /**
     * @var AttributeTypeMapperServiceInterface
     */
    private readonly AttributeTypeMapperServiceInterface $attributeTypeMapperService;
    /**
     * @var MagentoToKlevuAttributeMapperInterface
     */
    private readonly MagentoToKlevuAttributeMapperInterface $attributeMapperService;
    /**
     * @var StoreLocaleCodesProviderInterface
     */
    private readonly StoreLocaleCodesProviderInterface $localeCodesProvider;

    /**
     * @param AttributeFactory $attributeFactory
     * @param AttributeTypeMapperServiceInterface $attributeTypeMapperService
     * @param MagentoToKlevuAttributeMapperInterface $attributeMapperService
     * @param StoreLocaleCodesProviderInterface $localeCodesProvider
     */
    public function __construct(
        AttributeFactory $attributeFactory,
        AttributeTypeMapperServiceInterface $attributeTypeMapperService,
        MagentoToKlevuAttributeMapperInterface $attributeMapperService,
        StoreLocaleCodesProviderInterface $localeCodesProvider,
    ) {
        $this->attributeFactory = $attributeFactory;
        $this->attributeTypeMapperService = $attributeTypeMapperService;
        $this->attributeMapperService = $attributeMapperService;
        $this->localeCodesProvider = $localeCodesProvider;
    }

    /**
     *
     * @param AttributeInterface $attribute
     * @param string $apiKey
     *
     * @return SdkAttributeInterface
     * @throws AttributeMappingMissingException
     * @throws \TypeError
     */
    public function execute(AttributeInterface $attribute, string $apiKey): SdkAttributeInterface
    {
        return $this->attributeFactory->create(data: [
            'attributeName' => $this->getAttributeName(attribute: $attribute, apiKey: $apiKey),
            'datatype' => $this->getDataType(attribute: $attribute),
            'label' => $this->getLabels(attribute: $attribute, apiKey: $apiKey),
            'searchable' => $this->getIsSearchable(attribute: $attribute),
            'filterable' => $this->getIsFilterable(attribute: $attribute),
            'returnable' => $this->getIsReturnable(attribute: $attribute),
        ]);
    }

    /**
     * @param AttributeInterface $attribute
     *
     * @return string
     * @throws AttributeMappingMissingException
     */
    private function getAttributeName(AttributeInterface $attribute, string $apiKey): string
    {
        return $this->attributeMapperService->get(attribute: $attribute, apiKey: $apiKey);
    }

    /**
     * @param AttributeInterface $attribute
     *
     * @return DataType
     */
    private function getDataType(AttributeInterface $attribute): DataType
    {
        return $this->attributeTypeMapperService->execute(attribute: $attribute);
    }

    /**
     * @param AttributeInterface $attribute
     *
     * @return bool
     */
    private function getIsSearchable(AttributeInterface $attribute): bool
    {
        $return = false;
        if (method_exists($attribute, 'getIsSearchable')) {
            $return = (bool)$attribute->getIsSearchable();
        }

        return $return;
    }

    /**
     * @param AttributeInterface $attribute
     *
     * @return bool
     */
    private function getIsFilterable(AttributeInterface $attribute): bool
    {
        $return = false;
        if (method_exists($attribute, 'getIsFilterable')) {
            $return = (bool)$attribute->getIsFilterable();
        }

        return $return;
    }

    /**
     * @param AttributeInterface $attribute
     *
     * @return bool
     */
    private function getIsReturnable(AttributeInterface $attribute): bool
    {
        $return = false;
        if (method_exists($attribute, 'getUsedInProductListing')) {
            $return = (bool)$attribute->getUsedInProductListing();
        }

        return $return;
    }

    /**
     * @param AttributeInterface $attribute
     * @param string $apiKey
     *
     * @return string[]
     */
    private function getLabels(AttributeInterface $attribute, string $apiKey): array
    {
        $defaultLabel = $attribute->getDefaultFrontendLabel();
        $storeLabels = [];
        $locales = $this->localeCodesProvider->get(apiKey: $apiKey);
        $labels = $attribute->getFrontendLabels();
        foreach ($labels as $label) {
            if (!($locales[(string)$label->getStoreId()] ?? null)) {
                // is not integrated with this api key
                continue;
            }
            $storeLabel = $label->getLabel();
            if ($defaultLabel !== $storeLabel) {
                $storeLabels[$locales[$label->getStoreId()]] = $storeLabel;
            }
        }
        $return = $storeLabels
            ? array_shift($storeLabels)
            : $defaultLabel;

        return ['default' => $return];
    }
}
