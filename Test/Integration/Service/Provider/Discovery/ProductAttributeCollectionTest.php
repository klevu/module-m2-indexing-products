<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Test\Integration\Service\Provider\Discovery;

use Klevu\IndexingApi\Model\Source\IndexType;
use Klevu\IndexingApi\Service\Provider\Discovery\AttributeCollectionInterface;
use Klevu\IndexingProducts\Service\Provider\Discovery\ProductAttributeCollection;
use Klevu\TestFixtures\Catalog\Attribute\AttributeFixturePool;
use Klevu\TestFixtures\Catalog\AttributeTrait;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Magento\Eav\Api\Data\AttributeInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Klevu\IndexingProducts\Service\Provider\Discovery\ProductAttributeCollection::class
 * @method AttributeCollectionInterface instantiateTestObject(?array $arguments = null)
 * @method AttributeCollectionInterface instantiateTestObjectFromInterface(?array $arguments = null)
 */
class ProductAttributeCollectionTest extends TestCase
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

        $this->implementationFqcn = ProductAttributeCollection::class;
        $this->interfaceFqcn = AttributeCollectionInterface::class;
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

    public function testGet_ReturnsCollection_withoutFilter(): void
    {
        $this->createAttribute([
            'key' => 'test_attribute_1',
            'code' => 'klevu_test_attribute_1',
            'index_as' => IndexType::INDEX,
            'attribute_type' => 'text',
        ]);
        $attribute1 = $this->attributeFixturePool->get('test_attribute_1');

        $provider = $this->instantiateTestObject();
        $collection = $provider->get();
        /** @var AttributeInterface[] $attributes */
        $attributes = $collection->getItems();

        $attributeCodes = array_map(
            static fn (AttributeInterface $attribute) => ($attribute->getAttributeCode()),
            $attributes,
        );

        $this->assertContains(needle: $attribute1->getAttributeCode(), haystack: $attributeCodes);
    }

    public function testGet_ReturnsCollection_withAttributeIdFilter(): void
    {
        $this->createAttribute([
            'key' => 'test_attribute_1',
            'code' => 'klevu_test_attribute_1',
            'index_as' => IndexType::INDEX,
            'attribute_type' => 'text',
        ]);
        $attribute1 = $this->attributeFixturePool->get('test_attribute_1');

        $this->createAttribute([
            'key' => 'test_attribute_2',
            'code' => 'klevu_test_attribute_2',
            'index_as' => IndexType::INDEX,
            'attribute_type' => 'text',
        ]);
        $attribute2 = $this->attributeFixturePool->get('test_attribute_2');

        $provider = $this->instantiateTestObject();
        $collection = $provider->get([(int)$attribute1->getAttributeId()]);
        /** @var AttributeInterface[] $attributes */
        $attributes = $collection->getItems();

        $attributeCodes = array_map(
            static fn (AttributeInterface $attribute) => ($attribute->getAttributeCode()),
            $attributes,
        );

        $this->assertContains(needle: $attribute1->getAttributeCode(), haystack: $attributeCodes);
        $this->assertNotContains(needle: $attribute2->getAttributeCode(), haystack: $attributeCodes);
    }
}
