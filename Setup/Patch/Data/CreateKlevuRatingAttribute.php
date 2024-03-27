<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Setup\Patch\Data;

use Klevu\Indexing\Exception\AttributeCreationException;
use Klevu\IndexingProducts\Model\Attribute\KlevuRatingInterface;
use Magento\Catalog\Model\Product;
use Magento\Eav\Api\AttributeSetRepositoryInterface;
use Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface;
use Magento\Eav\Setup\EavSetup;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\Api\SearchCriteria;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SearchCriteriaBuilderFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Psr\Log\LoggerInterface;

class CreateKlevuRatingAttribute implements DataPatchInterface
{
    /**
     * @var ModuleDataSetupInterface
     */
    private readonly ModuleDataSetupInterface $moduleDataSetup;
    /**
     * @var EavSetupFactory
     */
    private readonly EavSetupFactory $eavSetupFactory;
    /**
     * @var AttributeSetRepositoryInterface
     */
    private readonly AttributeSetRepositoryInterface $attributeSetRepository;
    /**
     * @var SearchCriteriaBuilderFactory
     */
    private readonly SearchCriteriaBuilderFactory $searchCriteriaBuilderFactory;
    /**
     * @var LoggerInterface
     */
    private readonly LoggerInterface $logger;

    /**
     * @param ModuleDataSetupInterface $moduleDataSetup
     * @param EavSetupFactory $eavSetupFactory
     * @param AttributeSetRepositoryInterface $attributeSetRepository
     * @param SearchCriteriaBuilderFactory $searchCriteriaBuilderFactory
     * @param LoggerInterface $logger
     */
    public function __construct(
        ModuleDataSetupInterface $moduleDataSetup,
        EavSetupFactory $eavSetupFactory,
        AttributeSetRepositoryInterface $attributeSetRepository,
        SearchCriteriaBuilderFactory $searchCriteriaBuilderFactory,
        LoggerInterface $logger,
    ) {
        $this->moduleDataSetup = $moduleDataSetup;
        $this->eavSetupFactory = $eavSetupFactory;
        $this->attributeSetRepository = $attributeSetRepository;
        $this->searchCriteriaBuilderFactory = $searchCriteriaBuilderFactory;
        $this->logger = $logger;
    }

    /**
     * @return self
     */
    public function apply(): self
    {
        /** @var EavSetup $eavSetup */
        $eavSetup = $this->eavSetupFactory->create([
            'setup' => $this->moduleDataSetup,
        ]);
        try {
            $this->createAttribute($eavSetup);
            $this->assignToAttributeSets($eavSetup);
        } catch (\Exception $exception) {
            $this->logger->error(
                message: 'Error creating attribute {attributeCode}, Method: {method}, Error: {message}',
                context: [
                    'attributeCode' => KlevuRatingInterface::ATTRIBUTE_CODE,
                    'method' => __METHOD__,
                    'message' => $exception->getMessage(),
                ],
            );
        }

        return $this;
    }

    /**
     * @return array|string[]
     */
    public function getAliases(): array
    {
        return [];
    }

    /**
     * @return array|string[]
     */
    public static function getDependencies(): array
    {
        return [];
    }

    /**
     * @param EavSetup $eavSetup
     *
     * @return void
     * @throws LocalizedException
     * @throws \Zend_Validate_Exception
     */
    private function createAttribute(EavSetup $eavSetup): void
    {
        $eavSetup->addAttribute(
            entityTypeId: Product::ENTITY,
            code: KlevuRatingInterface::ATTRIBUTE_CODE,
            attr: [
                'type' => 'decimal',
                'label' => 'Klevu Rating',
                'input' => 'text',
                'required' => false,
                'sort_order' => 100,
                'frontend' => '',
                'backend' => '',
                'global' => ScopedAttributeInterface::SCOPE_STORE,
                'used_in_product_listing' => true,
                'user_defined' => false,
                'visible' => false,
                'visible_on_front' => false,
            ],
        );
    }

    /**
     * @param EavSetup $eavSetup
     *
     * @return void
     * @throws AttributeCreationException
     */
    private function assignToAttributeSets(EavSetup $eavSetup): void
    {
        $attributeId = $eavSetup->getAttributeId(
            Product::ENTITY,
            KlevuRatingInterface::ATTRIBUTE_CODE,
        );
        if (!$attributeId) {
            throw new AttributeCreationException(
                __(
                    'Attribute code %1 for %2 not found during attribute set assignment.',
                    KlevuRatingInterface::ATTRIBUTE_CODE,
                    Product::ENTITY,
                ),
            );
        }
        $searchCriteria = $this->getAttributeSetSearchCriteria();
        $attributeSets = $this->attributeSetRepository->getList($searchCriteria);

        foreach ($attributeSets->getItems() as $attributeSet) {
            $eavSetup->addAttributeToGroup(
                entityType: Product::ENTITY,
                setId: $attributeSet->getAttributeSetId(),
                groupId: 'General',
                attributeId: $attributeId,
                sortOrder: 100,
            );
        }
    }

    /**
     * @return SearchCriteria
     */
    private function getAttributeSetSearchCriteria(): SearchCriteria
    {
        /** @var SearchCriteriaBuilder $searchCriteriaBuilder */
        $searchCriteriaBuilder = $this->searchCriteriaBuilderFactory->create();

        return $searchCriteriaBuilder->create();
    }
}
