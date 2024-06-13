<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Service\Provider\Catalog;

use Klevu\IndexingApi\Service\Provider\Catalog\AttributeTextProviderInterface;
use Klevu\IndexingProducts\Exception\InvalidAttributeCodeException;
use Klevu\Pipelines\Exception\TransformationException;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\ResourceModel\Product as ProductResourceModel;
use Magento\Catalog\Model\ResourceModel\ProductFactory as ProductResourceModelFactory;
use Magento\Eav\Model\Entity\Attribute\Source\AbstractSource;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException;

class AttributeTextProvider implements AttributeTextProviderInterface
{
    /**
     * @var ProductResourceModelFactory
     */
    private readonly ProductResourceModelFactory $productResourceModelFactory;

    /**
     * @param ProductResourceModelFactory $productResourceModelFactory
     */
    public function __construct(ProductResourceModelFactory $productResourceModelFactory)
    {
        $this->productResourceModelFactory = $productResourceModelFactory;
    }

    /**
     * @param ProductInterface $product
     * @param string $attributeCode
     *
     * @return string[]|string|null
     * @throws InvalidAttributeCodeException
     * @throws LocalizedException
     * @throws TransformationException
     */
    public function get(ProductInterface $product, string $attributeCode): array|string|null
    {
        $attributeSource = $this->getAttributeSource(
            attributeCode: $attributeCode,
        );
        /** @var DataObject&ProductInterface $product */
        $value = $product->getData(key: $attributeCode);
        if (null === $value) {
            return null;
        }
        $optionText = $attributeSource->getOptionText($value);

        return false === $optionText
            ? null
            : $optionText;
    }

    /**
     * @param string $attributeCode
     *
     * @return AbstractSource
     * @throws InvalidAttributeCodeException
     * @throws LocalizedException
     * @throws TransformationException
     */
    private function getAttributeSource(string $attributeCode): AbstractSource
    {
        /** @var ProductResourceModel $productResourceModel */
        $productResourceModel = $this->productResourceModelFactory->create();
        $attribute = $productResourceModel->getAttribute(attribute: $attributeCode);
        if (!$attribute) {
            throw new InvalidAttributeCodeException(
                phrase: __(
                    'Invalid Attribute Code. Attribute Code "%1" not found.',
                    $attributeCode,
                ),
            );
        }

        return $attribute->getSource();
    }
}
