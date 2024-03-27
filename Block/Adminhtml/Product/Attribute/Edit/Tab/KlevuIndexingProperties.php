<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Block\Adminhtml\Product\Attribute\Edit\Tab;

use Klevu\IndexingApi\Model\MagentoAttributeInterface;
use Klevu\IndexingApi\Service\Provider\DefaultIndexingAttributesProviderInterface;
use Klevu\IndexingApi\Validator\ValidatorInterface;
use Magento\Backend\Block\Template\Context;
use Magento\Backend\Block\Widget\Form\Generic;
use Magento\Catalog\Api\Data\EavAttributeInterface;
use Magento\Eav\Block\Adminhtml\Attribute\PropertyLocker;
use Magento\Framework\Data\Form;
use Magento\Framework\Data\Form\Element\Fieldset;
use Magento\Framework\Data\FormFactory;
use Magento\Framework\Data\OptionSourceInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Registry;

class KlevuIndexingProperties extends Generic
{
    /**
     * @var OptionSourceInterface
     */
    private readonly OptionSourceInterface $indexDataType;
    /**
     * @var PropertyLocker
     */
    private readonly PropertyLocker $propertyLocker;
    /**
     * @var DefaultIndexingAttributesProviderInterface
     */
    private readonly DefaultIndexingAttributesProviderInterface $defaultIndexingAttributesProvider;
    /**
     * @var OptionSourceInterface
     */
    private readonly OptionSourceInterface $aspectOptions;
    /**
     * @var ValidatorInterface
     */
    private ValidatorInterface $indexableAttributeValidator;
    /**
     * @var bool|null
     */
    private ?bool $isDefaultAttribute = null;
    /**
     * @var bool|null
     */
    private ?bool $isAttributeSupported = null;

    /**
     * @param Context $context
     * @param Registry $registry
     * @param FormFactory $formFactory
     * @param OptionSourceInterface $indexDataType
     * @param OptionSourceInterface $aspectOptions
     * @param PropertyLocker $propertyLocker
     * @param DefaultIndexingAttributesProviderInterface $defaultIndexingAttributesProvider
     * @param ValidatorInterface $indexableAttributeValidator
     * @param mixed[] $data
     */
    public function __construct(
        Context $context,
        Registry $registry,
        FormFactory $formFactory,
        OptionSourceInterface $indexDataType,
        OptionSourceInterface $aspectOptions,
        PropertyLocker $propertyLocker,
        DefaultIndexingAttributesProviderInterface $defaultIndexingAttributesProvider,
        ValidatorInterface $indexableAttributeValidator,
        array $data = [],
    ) {
        $this->indexDataType = $indexDataType;
        $this->aspectOptions = $aspectOptions;
        $this->propertyLocker = $propertyLocker;
        $this->defaultIndexingAttributesProvider = $defaultIndexingAttributesProvider;
        $this->indexableAttributeValidator = $indexableAttributeValidator;

        parent::__construct($context, $registry, $formFactory, $data);
    }

    /**
     * Initialize form fields values
     *
     * @return $this
     */
    protected function _initFormValues(): static
    {
        $attributeObject = $this->getAttribute();
        $form = $this->getForm();
        $form->addValues(
            values: $attributeObject->getData(), //@phpstan-ignore-line
        );

        return parent::_initFormValues();
    }

    /**
     * Adding product form elements for editing attribute
     *
     * @return $this
     * @throws LocalizedException
     * @SuppressWarnings(PHPMD)
     */
    protected function _prepareForm(): static
    {
        $attribute = $this->getAttribute();

        $form = $this->_formFactory->create([
            'data' => [
                'id' => 'edit_form',
                'action' => $this->getData('action'),
                'method' => 'post',
            ],
        ]);
        $fieldset = $this->createIndexingFieldset($form);
        $this->createDefaultIndexedInformation($fieldset, $attribute);
        $this->createUnsupportedAttributeInformation($fieldset, $attribute);
        $this->createIsIndexableField($fieldset, $attribute);
        $this->createAspectMappingField($fieldset, $attribute);

        $this->_eventManager->dispatch(
            'adminhtml_catalog_product_attribute_edit_klevu_prepare_form',
            ['form' => $form],
        );
        $this->setForm($form);
        $this->propertyLocker->lock($form);

        return $this;
    }

    /**
     * Retrieve attribute object from registry
     *
     * @return EavAttributeInterface
     */
    private function getAttribute(): EavAttributeInterface
    {
        return $this->_coreRegistry->registry('entity_attribute');
    }

    /**
     * @param Form $form
     *
     * @return Fieldset
     */
    private function createIndexingFieldset(Form $form): Fieldset
    {
        return $form->addFieldset(
            'klevu_indexing_fieldset',
            [
                'legend' => __('Klevu Indexing'),
                'collapsable' => true,
                'expanded' => false,
            ],
        );
    }

    /**
     * @param Fieldset $fieldset
     * @param EavAttributeInterface $attribute
     *
     * @return void
     */
    private function createDefaultIndexedInformation(Fieldset $fieldset, EavAttributeInterface $attribute): void
    {
        if (!$this->isDefaultAttribute($attribute)) {
            return;
        }
        $fieldset->addField(
            elementId: 'klevu_default_indexed_attribute',
            type: 'note',
            config: [
                'name' => 'klevu_default_indexed_attribute',
                'text' => __(
                    'This attribute is used as a standard Klevu attribute and is automatically indexed to Klevu.',
                    )
                    . '<br/>'
                    . __('Sync settings can not be changed via the admin for this attribute.'),
            ],
        );
    }

    /**
     * @param Fieldset $fieldset
     * @param EavAttributeInterface $attribute
     *
     * @return void
     */
    private function createUnsupportedAttributeInformation(Fieldset $fieldset, EavAttributeInterface $attribute): void
    {
        if (
            $this->isDefaultAttribute($attribute)
            || $this->isAttributeSupported($attribute)
        ) {
            return;
        }
        $fieldset->addField(
            elementId: 'klevu_unsupported_attribute',
            type: 'note',
            config: [
                'name' => 'klevu_unsupported_attribute',
                'text' => __(
                    'This attribute is not supported for syncing to Klevu.',
                    ),
            ],
        );
    }

    /**
     * @param Fieldset $fieldset
     * @param EavAttributeInterface $attribute
     *
     * @return void
     */
    private function createIsIndexableField(Fieldset $fieldset, EavAttributeInterface $attribute): void
    {
        if (!$this->isValidAttribute($attribute)) {
            return;
        }

        $fieldset->addField(
            elementId: MagentoAttributeInterface::ATTRIBUTE_PROPERTY_IS_INDEXABLE,
            type: 'select',
            config: [
                'name' => MagentoAttributeInterface::ATTRIBUTE_PROPERTY_IS_INDEXABLE,
                'label' => __('Register with Klevu'),
                'title' => __('Register with Klevu'),
                'values' => $this->indexDataType->toOptionArray(),
                // @phpstan-ignore-next-line
                'value' => $attribute->getData(MagentoAttributeInterface::ATTRIBUTE_PROPERTY_IS_INDEXABLE),
                'note' => __('Register the Attribute with Klevu.')
                    . ' '
                    . __('An attribute must be registered with Klevu in order to sync data for that attribute.'),
            ],
        );
    }

    /**
     * @param Fieldset $fieldset
     * @param EavAttributeInterface $attribute
     *
     * @return void
     */
    private function createAspectMappingField(Fieldset $fieldset, EavAttributeInterface $attribute): void
    {
        if (!$this->isValidAttribute($attribute)) {
            return;
        }

        $fieldset->addField(
            elementId: MagentoAttributeInterface::ATTRIBUTE_PROPERTY_ASPECT_MAPPING,
            type: 'select',
            config: [
                'name' => MagentoAttributeInterface::ATTRIBUTE_PROPERTY_ASPECT_MAPPING,
                'label' => __('Triggers Update of'),
                'title' => __('Triggers Update of'),
                'values' => $this->getAspectMappingOptions(),
                // @phpstan-ignore-next-line
                'value' => $attribute->getData(MagentoAttributeInterface::ATTRIBUTE_PROPERTY_ASPECT_MAPPING),
                'note' => __(
                        'When the value of this attribute changes, '
                        . 'what data should be sent in a partial update to Klevu?',
                    )
                    . '<br/>'
                    . __('e.g. when tax_class_id changes we want to trigger an update for price.'),
            ],
        );
    }

    /**
     * @param EavAttributeInterface $attribute
     *
     * @return bool
     */
    private function isValidAttribute(EavAttributeInterface $attribute): bool
    {
        if ($this->isDefaultAttribute($attribute)) {
            return false;
        }

        return $this->isAttributeSupported($attribute);
    }

    /**
     * @param EavAttributeInterface $attribute
     *
     * @return bool
     */
    private function isDefaultAttribute(EavAttributeInterface $attribute): bool
    {
        if (null === $this->isDefaultAttribute) {
            $this->isDefaultAttribute = array_key_exists(
                key: $attribute->getAttributeCode(),
                array: $this->defaultIndexingAttributesProvider->get(),
            );
        }

        return $this->isDefaultAttribute;
    }

    /**
     * @param EavAttributeInterface $attribute
     *
     * @return bool
     */
    private function isAttributeSupported(EavAttributeInterface $attribute): bool
    {
        if (null === $this->isAttributeSupported) {
            $this->isAttributeSupported = $this->indexableAttributeValidator->isValid($attribute);
        }

        return $this->isAttributeSupported;
    }

    /**
     * @return array<string, string>
     */
    private function getAspectMappingOptions(): array
    {
        return $this->aspectOptions->toOptionArray();
    }
}
