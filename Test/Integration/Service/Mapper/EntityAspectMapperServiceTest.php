<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Test\Integration\Service\Mapper;

use Klevu\IndexingApi\Model\Source\IndexType;
use Klevu\IndexingApi\Service\Mapper\EntityAspectMapperServiceInterface;
use Klevu\IndexingApi\Service\Provider\Discovery\AttributeCollectionInterface;
use Klevu\IndexingProducts\Model\Source\Aspect;
use Klevu\IndexingProducts\Service\Mapper\EntityAspectMapperService;
use Klevu\TestFixtures\Catalog\Attribute\AttributeFixturePool;
use Klevu\TestFixtures\Catalog\AttributeTrait;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Magento\Catalog\Model\ResourceModel\Product\Attribute\Collection;
use Magento\Eav\Model\Entity\Attribute;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * @covers EntityAspectMapperService
 * @method EntityAspectMapperServiceInterface instantiateTestObject(?array $arguments = null)
 * @method EntityAspectMapperServiceInterface instantiateTestObjectFromInterface(?array $arguments = null)
 */
class EntityAspectMapperServiceTest extends TestCase
{
    use AttributeTrait;
    use ObjectInstantiationTrait;
    use TestImplementsInterfaceTrait;

    /**
     * @var ObjectManagerInterface|null
     */
    private ?ObjectManagerInterface $objectManager = null; // @phpstan-ignore-line

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->implementationFqcn = EntityAspectMapperService::class;
        $this->interfaceFqcn = EntityAspectMapperServiceInterface::class;
        $this->objectManager = Bootstrap::getObjectManager();
        $this->attributeFixturePool = $this->objectManager->get(AttributeFixturePool::class);
    }

    /**
     * @return void
     * @throws \Exception
     */
    protected function tearDown(): void
    {
        parent::tearDown();

        $this->attributeFixturePool->rollback();
    }

    public function testExecute_ReturnsEmptyArray_WhenAttributeCodesProvided(): void
    {
        $service = $this->instantiateTestObject();
        $result = $service->execute([]);

        $this->assertCount(expectedCount: 0, haystack: $result);
    }

    public function testExecute_ReturnsEmptyArray_WhenNoMapping(): void
    {
        $service = $this->instantiateTestObject();
        $result = $service->execute([
            'klevu_test_attribute_1',
            'klevu_test_attribute_2',
        ]);

        $this->assertCount(expectedCount: 0, haystack: $result);
    }

    public function testExecute_ReturnsAllAsAspect_ForAttributeCodesWithNoAspectMapped(): void
    {
        $this->createAttribute([
            'key' => 'test_attribute_1',
            'code' => 'klevu_test_attribute_1',
            'index_as' => IndexType::INDEX,
            'attribute_type' => 'text',
            'aspect' => Aspect::ALL,
        ]);
        $attribute1 = $this->attributeFixturePool->get('test_attribute_1');

        $this->createAttribute([
            'key' => 'test_attribute_2',
            'code' => 'klevu_test_attribute_2',
            'index_as' => IndexType::INDEX,
            'attribute_type' => 'text',
            'aspect' => Aspect::PRICE,
        ]);
        $attribute2 = $this->attributeFixturePool->get('test_attribute_2');

        $this->createAttribute([
            'key' => 'test_attribute_3',
            'code' => 'klevu_test_attribute_3',
            'index_as' => IndexType::INDEX,
            'attribute_type' => 'text',
        ]);
        $attribute3 = $this->attributeFixturePool->get('test_attribute_3');

        $service = $this->instantiateTestObject([]);
        $result = $service->execute([
            $attribute1->getAttributeCode(),
            $attribute2->getAttributeCode(),
            $attribute3->getAttributeCode(),
        ]);

        $this->assertCount(expectedCount: 3, haystack: $result);

        $this->assertContains(needle: Aspect::ALL, haystack: $result);
        $this->assertContains(needle: Aspect::PRICE, haystack: $result);
        $this->assertContains(needle: Aspect::NONE, haystack: $result);
    }

    public function testExecute_FiltersInvalidAspects(): void
    {
        $mockAttribute = $this->getMockBuilder(Attribute::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockAttribute->expects($this->once())
            ->method('getData')
            ->willReturn(999);

        $mockCollection = $this->getMockBuilder(Collection::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockCollection->expects($this->once())
            ->method('addFieldToFilter');
        $mockCollection->expects($this->once())
            ->method('getItems')
            ->willReturn([$mockAttribute]);

        $mockAttributeCollection = $this->getMockBuilder(AttributeCollectionInterface::class)
            ->getMock();
        $mockAttributeCollection->expects($this->once())
            ->method('get')
            ->willReturn($mockCollection);

        $service = $this->instantiateTestObject([
            'attributeCollection' => $mockAttributeCollection,
        ]);
        $result = $service->execute(['some_attribute']);

        $this->assertCount(expectedCount: 0, haystack: $result);
    }
}
