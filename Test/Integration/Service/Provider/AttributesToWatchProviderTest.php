<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Test\Integration\Service\Provider;

use Klevu\IndexingApi\Model\Source\IndexType;
use Klevu\IndexingApi\Service\Provider\AttributesToWatchProviderInterface;
use Klevu\IndexingProducts\Model\Source\Aspect;
use Klevu\IndexingProducts\Service\Provider\AttributesToWatchProvider;
use Klevu\TestFixtures\Catalog\Attribute\AttributeFixturePool;
use Klevu\TestFixtures\Catalog\AttributeTrait;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Klevu\IndexingProducts\Service\Provider\AttributesToWatchProvider::class
 * @method AttributesToWatchProviderInterface instantiateTestObject(?array $arguments = null)
 * @method AttributesToWatchProviderInterface instantiateTestObjectFromInterface(?array $arguments = null)
 */
class AttributesToWatchProviderTest extends TestCase
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

        $this->implementationFqcn = AttributesToWatchProvider::class; // @phpstan-ignore-line
        $this->interfaceFqcn = AttributesToWatchProviderInterface::class;
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

    public function testGetAttributeCode_ReturnsMergedListOfAttributeCodes(): void
    {
        $this->createAttribute([
            'attribute_type' => 'text',
            'code' => 'klevu_test_text_attribute',
            'key' => 'klevu_test_indexable_attribute',
            'index_as' => IndexType::INDEX,
            'aspect' => Aspect::ATTRIBUTES,
        ]);
        $indexableAttributeFixture = $this->attributeFixturePool->get('klevu_test_indexable_attribute');

        $this->createAttribute([
            'attribute_type' => 'date',
            'code' => 'klevu_test_select_attribute',
            'key' => 'klevu_test_nonindexable_attribute',
            'index_as' => IndexType::NO_INDEX,
            'aspect' => Aspect::NONE,
        ]);
        $nonIndexableAttributeFixture = $this->attributeFixturePool->get('klevu_test_nonindexable_attribute');

        $provider = $this->instantiateTestObject([
            'attributesToWatch' => [
                'custom_attr_to_watch_1' => Aspect::STOCK,
                'custom_attr_to_watch_2' => Aspect::RELATIONS,
            ],
        ]);
        $attributesToIndex = $provider->getAttributeCodes();

        $this->assertContains(needle: $indexableAttributeFixture->getAttributeCode(), haystack: $attributesToIndex);
        $this->assertContains(needle: 'custom_attr_to_watch_1', haystack: $attributesToIndex);
        $this->assertContains(needle: 'custom_attr_to_watch_1', haystack: $attributesToIndex);
        $this->assertContains(needle: 'special_price', haystack: $attributesToIndex);
        $this->assertContains(needle: 'tax_class_id', haystack: $attributesToIndex);
        $this->assertContains(needle: 'name', haystack: $attributesToIndex);
        $this->assertContains(needle: 'price', haystack: $attributesToIndex);
        $this->assertNotContains(
            needle: $nonIndexableAttributeFixture->getAttributeCode(),
            haystack: $attributesToIndex,
        );
    }

    public function testGetAspectMapping_ReturnsMergedListOfAttributeCodes(): void
    {
        $this->createAttribute([
            'attribute_type' => 'text',
            'code' => 'klevu_test_attribute_1',
            'key' => 'klevu_test_indexable_attribute',
            'index_as' => IndexType::INDEX,
            'aspect' => Aspect::ATTRIBUTES,
        ]);
        $indexableAttributeFixture = $this->attributeFixturePool->get('klevu_test_indexable_attribute');

        $this->createAttribute([
            'attribute_type' => 'date',
            'code' => 'klevu_test_attribute_2',
            'key' => 'klevu_test_nonindexable_attribute',
            'index_as' => IndexType::NO_INDEX,
            'aspect' => Aspect::PRICE,
        ]);
        $nonIndexableAttributeFixture = $this->attributeFixturePool->get('klevu_test_nonindexable_attribute');

        $this->createAttribute([
            'attribute_type' => 'text',
            'code' => 'klevu_test_attribute_3',
            'key' => 'klevu_test_indexable_attribute_noaspect',
            'index_as' => IndexType::NO_INDEX,
            'aspect' => Aspect::NONE,
        ]);
        $nonAttributeNoAspectFixture = $this->attributeFixturePool->get('klevu_test_indexable_attribute_noaspect');

        $provider = $this->instantiateTestObject([
            'attributesToWatch' => [
                'custom_attr_to_watch_1' => Aspect::ALL,
                'custom_attr_to_watch_2' => Aspect::NONE,
            ],
        ]);
        $aspectMapping = $provider->getAspectMapping();

        $this->assertArrayHasKey(key: $indexableAttributeFixture->getAttributeCode(), array: $aspectMapping);
        $this->assertSame(
            expected: Aspect::ATTRIBUTES,
            actual: $aspectMapping[$indexableAttributeFixture->getAttributeCode()],
        );

        $this->assertArrayHasKey(key: $nonIndexableAttributeFixture->getAttributeCode(), array: $aspectMapping);
        $this->assertSame(
            expected: Aspect::PRICE,
            actual: $aspectMapping[$nonIndexableAttributeFixture->getAttributeCode()],
        );

        $this->assertArrayNotHasKey(key: $nonAttributeNoAspectFixture->getAttributeCode(), array: $aspectMapping);

        $this->assertArrayHasKey(key: 'custom_attr_to_watch_1', array: $aspectMapping);
        $this->assertSame(
            expected: Aspect::ALL,
            actual: $aspectMapping['custom_attr_to_watch_1'],
        );

        $this->assertArrayHasKey(key: 'custom_attr_to_watch_2', array: $aspectMapping);
        $this->assertSame(
            expected: Aspect::NONE,
            actual: $aspectMapping['custom_attr_to_watch_2'],
        );

        $this->assertArrayHasKey(key: 'special_price', array: $aspectMapping);
        $this->assertSame(
            expected: Aspect::PRICE,
            actual: $aspectMapping['special_price'],
        );

        $this->assertArrayHasKey(key: 'tax_class_id', array: $aspectMapping);
        $this->assertSame(
            expected: Aspect::PRICE,
            actual: $aspectMapping['tax_class_id'],
        );

        $this->assertArrayHasKey(key: 'name', array: $aspectMapping);
        $this->assertSame(
            expected: Aspect::ATTRIBUTES,
            actual: $aspectMapping['name'],
        );

        $this->assertArrayHasKey(key: 'price', array: $aspectMapping);
        $this->assertSame(
            expected: Aspect::PRICE,
            actual: $aspectMapping['price'],
        );
    }
}
