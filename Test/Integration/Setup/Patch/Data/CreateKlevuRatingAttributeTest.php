<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Test\Integration\Setup\Patch\Data;

use Klevu\IndexingProducts\Model\Attribute\KlevuRatingInterface;
use Klevu\IndexingProducts\Setup\Patch\Data\CreateKlevuRatingAttribute;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Magento\Catalog\Model\Product;
use Magento\Eav\Api\AttributeRepositoryInterface;
use Magento\Eav\Api\AttributeSetRepositoryInterface;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Eav\Setup\EavSetup;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\TestFramework\Eav\Model\ResourceModel\GetEntityIdByAttributeId;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class CreateKlevuRatingAttributeTest extends TestCase
{
    use ObjectInstantiationTrait;
    use TestImplementsInterfaceTrait;

    /**
     * @var ObjectManagerInterface|null
     */
    private ?ObjectManagerInterface $objectManager = null;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->objectManager = Bootstrap::getObjectManager();

        $this->implementationFqcn = CreateKlevuRatingAttribute::class;
        $this->interfaceFqcn = DataPatchInterface::class;
    }

    public function testGetAliases_ReturnsEmptyArray(): void
    {
        $patch = $this->instantiateTestObject();
        $aliases = $patch->getAliases();

        $this->assertCount(expectedCount: 0, haystack: $aliases);
    }

    public function testGetDependencies_ReturnsEmptyArray(): void
    {
        $patch = $this->instantiateTestObject();
        $dependencies = $patch->getDependencies();

        $this->assertCount(expectedCount: 0, haystack: $dependencies);
    }

    public function testApply_LogsError_WhenExceptionThrow_DuringAttributeCreation(): void
    {
        $exceptionMessage = 'Exception thrown by $eavSetup->addAttribute()';

        $mockLogger = $this->getMockBuilder(LoggerInterface::class)
            ->getMock();
        $mockLogger->expects($this->once())
            ->method('error')
            ->with(
                'Error creating attribute {attributeCode}, Method: {method}, Error: {message}',
                [
                    'attributeCode' => KlevuRatingInterface::ATTRIBUTE_CODE,
                    'method' => 'Klevu\IndexingProducts\Setup\Patch\Data\CreateKlevuRatingAttribute::apply',
                    'message' => $exceptionMessage,
                ],
            );

        $mockEavSetup = $this->getMockBuilder(EavSetup::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockEavSetup->expects($this->once())
            ->method('addAttribute')
            ->willThrowException(new LocalizedException(__($exceptionMessage)));

        $mockEavSetupFactory = $this->getMockBuilder(EavSetupFactory::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockEavSetupFactory->expects($this->once())
            ->method('create')
            ->willReturn($mockEavSetup);

        $patch = $this->instantiateTestObject([
            'eavSetupFactory' => $mockEavSetupFactory,
            'logger' => $mockLogger,
        ]);
        $patch->apply();
    }

    public function testApply_LogsError_WhenExceptionThrow_DuringAttributeSetAssignment(): void
    {
        $exceptionMessage = sprintf(
            'Attribute code %s for %s not found during attribute set assignment.',
            KlevuRatingInterface::ATTRIBUTE_CODE,
            Product::ENTITY,
        );

        $mockLogger = $this->getMockBuilder(LoggerInterface::class)
            ->getMock();
        $mockLogger->expects($this->once())
            ->method('error')
            ->with(
                'Error creating attribute {attributeCode}, Method: {method}, Error: {message}',
                [
                    'attributeCode' => KlevuRatingInterface::ATTRIBUTE_CODE,
                    'method' => 'Klevu\IndexingProducts\Setup\Patch\Data\CreateKlevuRatingAttribute::apply',
                    'message' => $exceptionMessage,
                ],
            );

        $mockEavSetup = $this->getMockBuilder(EavSetup::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockEavSetup->expects($this->once())
            ->method('addAttribute');
        $mockEavSetup->expects($this->once())
            ->method('getAttributeId')
            ->willReturn(false);

        $mockEavSetupFactory = $this->getMockBuilder(EavSetupFactory::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockEavSetupFactory->expects($this->once())
            ->method('create')
            ->willReturn($mockEavSetup);

        $patch = $this->instantiateTestObject([
            'eavSetupFactory' => $mockEavSetupFactory,
            'logger' => $mockLogger,
        ]);
        $patch->apply();
    }

    public function testApply_createsAttribute(): void
    {
        $attributeRepository = $this->objectManager->create(AttributeRepositoryInterface::class);
        try {
            $attribute = $attributeRepository->get(
                entityTypeCode: Product::ENTITY,
                attributeCode: KlevuRatingInterface::ATTRIBUTE_CODE,
            );
            // setIsUserDefined to true allows us to remove the attribute during testing
            $attribute->setIsUserDefined(true);
            $deleted = $attributeRepository->delete(attribute: $attribute);
            $this->assertTrue(condition: $deleted);
        } catch (NoSuchEntityException) {
            // attribute does not exist, this is fine
        }

        try {
            $attributeRepository->get(
                entityTypeCode: Product::ENTITY,
                attributeCode: KlevuRatingInterface::ATTRIBUTE_CODE,
            );
            $this->fail('Attribute was not deleted before test run.');
        } catch (NoSuchEntityException) {
            // attribute does not exist, this is fine
        }

        $patch = $this->instantiateTestObject();
        $patch->apply();

        $attribute = null;
        try {
            $eavConfig = $this->objectManager->get(EavConfig::class);
            // remove cached data
            $eavConfig->clear();
            $attributeRepository = $this->objectManager->create(AttributeRepositoryInterface::class, [
                'eavConfig' => $eavConfig,
            ]);
            $attribute = $attributeRepository->get(
                entityTypeCode: Product::ENTITY,
                attributeCode: KlevuRatingInterface::ATTRIBUTE_CODE,
            );
        } catch (NoSuchEntityException) {
            $this->fail(
                sprintf(
                    'Attribute %s was not created by the patch.',
                    KlevuRatingInterface::ATTRIBUTE_CODE,
                ),
            );
        }

        $searchCriteriaBuilder = $this->objectManager->get(SearchCriteriaBuilder::class);
        $searchCriteria = $searchCriteriaBuilder->create();
        $attributeSetRepository = $this->objectManager->get(AttributeSetRepositoryInterface::class);
        $attributeSets = $attributeSetRepository->getList($searchCriteria);
        $attributeGroup = 'General';
        $eavSetup = $this->objectManager->get(EavSetup::class);

        $getEntityIdByAttributeId = $this->objectManager->get(GetEntityIdByAttributeId::class);
        foreach ($attributeSets->getItems() as $attributeSet) {
            $attributeGroupId = $eavSetup->getAttributeGroupId(
                entityTypeId: $attributeSet->getAttributeSetId(),
                setId: $attributeSet->getAttributeSetId(),
                groupId: $attributeGroup,
            );

            $entityAttributeId = $getEntityIdByAttributeId->execute(
                setId: (int)$attributeSet->getId(),
                attributeId: (int)$attribute?->getId(),
                attributeGroupId: (int)$attributeGroupId,
            );
            $this->assertNotNull($entityAttributeId);
        }
    }
}
