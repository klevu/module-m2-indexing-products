<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Test\Integration\Service\Provider;

use Klevu\Indexing\Service\Provider\IndexableAttributesProvider;
use Klevu\IndexingApi\Model\Source\IndexType;
use Klevu\IndexingApi\Service\Provider\IndexableAttributesProviderInterface;
use Klevu\IndexingProducts\Service\Provider\DefaultIndexingAttributesProvider as DefaultIndexingAttributesProviderVirtualType; // phpcs:ignore Generic.Files.LineLength.TooLong
use Klevu\IndexingProducts\Service\Provider\IndexableAttributesProvider as IndexableAttributesProviderVirtualType;
use Klevu\TestFixtures\Catalog\Attribute\AttributeFixturePool;
use Klevu\TestFixtures\Catalog\AttributeTrait;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Magento\Catalog\Api\Data\CategoryAttributeInterface;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Eav\Api\Data\AttributeInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * @covers IndexableAttributesProvider::class
 * @method IndexableAttributesProviderInterface instantiateTestObject(?array $arguments = null)
 * @method IndexableAttributesProviderInterface instantiateTestObjectFromInterface(?array $arguments = null)
 */
class IndexableAttributesProviderTest extends TestCase
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

        $this->implementationFqcn = IndexableAttributesProviderVirtualType::class; // @phpstan-ignore-line
        $this->interfaceFqcn = IndexableAttributesProviderInterface::class;
        $this->implementationForVirtualType = IndexableAttributesProvider::class;
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

    /**
     * @magentoAppIsolation enabled
     */
    public function testGet_ReturnsAttributeSetToBeIndexable(): void
    {
        $apiKey = 'klevu-js-api-key';

        $this->createAttribute([
            'attribute_type' => 'text',
            'code' => 'klevu_test_text_attribute',
            'key' => 'klevu_test_indexable_attribute',
            'index_as' => IndexType::INDEX,
        ]);
        $indexableAttributeFixture = $this->attributeFixturePool->get('klevu_test_indexable_attribute');

        $this->createAttribute([
            'attribute_type' => 'date',
            'code' => 'klevu_test_select_attribute',
            'key' => 'klevu_test_nonindexable_attribute',
            'index_as' => IndexType::NO_INDEX,
        ]);
        $nonIndexableAttributeFixture = $this->attributeFixturePool->get('klevu_test_nonindexable_attribute');

        $this->createAttribute([
            'attribute_type' => 'boolean',
            'code' => 'klevu_test_category_attribute',
            'entity_type' => CategoryAttributeInterface::ENTITY_TYPE_CODE,
            'key' => 'klevu_test_category_attribute',
            'index_as' => IndexType::INDEX,
        ]);
        $categoryAttributeFixture = $this->attributeFixturePool->get('klevu_test_category_attribute');

        $provider = $this->instantiateTestObject();
        $attributesToIndex = $provider->get(apiKey: $apiKey);

        $indexableAttributeArray = array_filter(
            array: $attributesToIndex,
            callback: static fn (AttributeInterface $attribute): bool => (
                (int)$attribute->getAttributeId() === (int)$indexableAttributeFixture->getAttributeId()
            ),
        );
        $indexableAttribute = array_shift($indexableAttributeArray);
        $this->assertInstanceOf(expected: AttributeInterface::class, actual: $indexableAttribute);
        $this->assertSame(expected: 'klevu_test_text_attribute', actual: $indexableAttribute->getAttributeCode());

        $nonIndexableAttributeArray = array_filter(
            array: $attributesToIndex,
            callback: static fn (AttributeInterface $attribute): bool => (
                (int)$attribute->getAttributeId() === (int)$nonIndexableAttributeFixture->getAttributeId()
            ),
        );
        $this->assertCount(expectedCount: 0, haystack: $nonIndexableAttributeArray);

        $categoryAttributeArray = array_filter(
            array: $attributesToIndex,
            callback: static fn (AttributeInterface $attribute): bool => (
                (int)$attribute->getAttributeId() === (int)$categoryAttributeFixture->getAttributeId()
            ),
        );
        $this->assertCount(expectedCount: 0, haystack: $categoryAttributeArray);
    }

    /**
     * @magentoAppIsolation enabled
     */
    public function testGetAttributeCodes_ReturnsAttributeSetToBeIndexable(): void
    {
        $apiKey = 'klevu-js-api-key';

        $this->createAttribute([
            'attribute_type' => 'textarea',
            'code' => 'klevu_test_text_attribute',
            'key' => 'klevu_test_indexable_attribute',
            'index_as' => IndexType::INDEX,
        ]);
        $indexableAttributeFixture = $this->attributeFixturePool->get('klevu_test_indexable_attribute');

        $this->createAttribute([
            'attribute_type' => 'text',
            'code' => 'klevu_test_select_attribute',
            'key' => 'klevu_test_nonindexable_attribute',
            'index_as' => IndexType::NO_INDEX,
        ]);
        $nonIndexableAttributeFixture = $this->attributeFixturePool->get('klevu_test_nonindexable_attribute');

        $this->createAttribute([
            'attribute_type' => 'image',
            'code' => 'klevu_test_category_attribute',
            'entity_type' => CategoryAttributeInterface::ENTITY_TYPE_CODE,
            'key' => 'klevu_test_category_attribute',
            'index_as' => IndexType::INDEX,
        ]);
        $categoryAttributeFixture = $this->attributeFixturePool->get('klevu_test_category_attribute');

        // phpcs:ignore Generic.Files.LineLength.TooLong
        $defaultAttributesProvider = $this->objectManager->create(DefaultIndexingAttributesProviderVirtualType::class, [
            'klevu_test_text_attribute' => 'another_name',
        ]);

        $provider = $this->instantiateTestObject([
            'defaultIndexingAttributesProvider' => $defaultAttributesProvider,
        ]);
        $attributesCodes = $provider->getAttributeCodes(apiKey: $apiKey);

        $this->assertContains(needle: $indexableAttributeFixture->getAttributeCode(), haystack: $attributesCodes);
        $this->assertNotContains(needle: $nonIndexableAttributeFixture->getAttributeCode(), haystack: $attributesCodes);
        $this->assertNotContains(needle: $categoryAttributeFixture->getAttributeCode(), haystack: $attributesCodes);
        $this->assertContains(needle: ProductInterface::NAME, haystack: $attributesCodes);
        $this->assertContains(needle: ProductInterface::PRICE, haystack: $attributesCodes);
        $this->assertContains(needle: ProductInterface::SKU, haystack: $attributesCodes);
        $this->assertContains(needle: ProductInterface::VISIBILITY, haystack: $attributesCodes);
    }
}
