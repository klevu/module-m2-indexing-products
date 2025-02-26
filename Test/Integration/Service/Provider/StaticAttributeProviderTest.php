<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Test\Integration\Service\Provider;

use Klevu\Indexing\Exception\InvalidStaticAttributeConfigurationException;
use Klevu\Indexing\Validator\StaticAttributeValidator;
use Klevu\IndexingApi\Service\Provider\StaticAttributeProviderInterface;
use Klevu\IndexingProducts\Model\Attribute\KlevuParentSkuInterface;
use Klevu\IndexingProducts\Service\Provider\StaticAttributeProvider;
use Klevu\TestFixtures\Store\StoreFixturesPool;
use Klevu\TestFixtures\Store\StoreTrait;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Magento\Catalog\Api\Data\ProductAttributeInterface;
use Magento\Framework\DataObject;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use TddWizard\Fixtures\Core\ConfigFixture;

class StaticAttributeProviderTest extends TestCase
{
    use ObjectInstantiationTrait;
    use StoreTrait;
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

        $this->implementationFqcn = StaticAttributeProvider::class;
        $this->interfaceFqcn = StaticAttributeProviderInterface::class;
        $this->objectManager = Bootstrap::getObjectManager();
        $this->storeFixturesPool = $this->objectManager->get(StoreFixturesPool::class);
    }

    /**
     * @return void
     * @throws \Exception
     */
    protected function tearDown(): void
    {
        parent::tearDown();

        $this->storeFixturesPool->rollback();
    }

    public function testGet_ReturnsNoAttributes_WhenNotSet(): void
    {
        $provider = $this->instantiateTestObject([
            'attributes' => [],
        ]);
        $results = iterator_to_array(iterator: $provider->get(), preserve_keys: false);
        $this->assertCount(expectedCount: 0, haystack: $results);
    }

    public function testGet_throwsException_WhenAttributeIdNotSet_InNonProductionMode(): void
    {
        $this->expectException(InvalidStaticAttributeConfigurationException::class);
        $this->expectExceptionMessage('"attribute_id" is a required field for static attributes');

        $provider = $this->instantiateTestObject([
            'attributes' => [
                'some_attribute' => [
                    'attribute_code' => 'some_attribute',
                    'is_searchable' => true,
                    'is_filterable' => false,
                    'is_returnable' => true,
                    'default_label' => 'Default Label',
                ],
            ],
            'validator' => $this->objectManager->get(StaticAttributeValidator::class),
        ]);
        $results = iterator_to_array(iterator: $provider->get(), preserve_keys: false);
        $this->assertCount(expectedCount: 1, haystack: $results);
    }

    public function testGet_throwsException_WhenAttributeCodeNotSet_InNonProductionMode(): void
    {
        $this->expectException(InvalidStaticAttributeConfigurationException::class);
        $this->expectExceptionMessage('"attribute_code" is a required field for static attributes');

        $provider = $this->instantiateTestObject([
            'attributes' => [
                'some_attribute' => [
                    'attribute_id' => 123456,
                    'is_searchable' => true,
                    'is_filterable' => false,
                    'is_returnable' => true,
                    'default_label' => 'Default Label',
                ],
            ],
            'validator' => $this->objectManager->get(StaticAttributeValidator::class),
        ]);
        $results = iterator_to_array(iterator: $provider->get(), preserve_keys: false);
        $this->assertCount(expectedCount: 1, haystack: $results);
    }

    public function testGet_throwsException_WhenAttributeIdandCodeNotSet_InNonProductionMode(): void
    {
        $this->expectException(InvalidStaticAttributeConfigurationException::class);
        $this->expectExceptionMessage('"attribute_id" is a required field for static attributes');

        $provider = $this->instantiateTestObject([
            'attributes' => [
                'some_attribute' => [
                    'is_searchable' => true,
                    'is_filterable' => false,
                    'is_returnable' => true,
                    'default_label' => 'Default Label',
                ],
            ],
            'validator' => $this->objectManager->get(StaticAttributeValidator::class),
        ]);
        $results = iterator_to_array(iterator: $provider->get(), preserve_keys: false);
        $this->assertCount(expectedCount: 1, haystack: $results);
    }

    public function testGet_ReturnsGeneratorOfAttributes(): void
    {
        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');
        ConfigFixture::setForStore(
            path: 'general/locale/code',
            value: 'de-DE',
            storeCode: $storeFixture->getCode(),
        );

        $provider = $this->instantiateTestObject([
            'attributes' => [
                'some_attribute' => [
                    'attribute_id' => 1000001,
                    'attribute_code' => 'some_attribute',
                    'is_searchable' => true,
                    'is_filterable' => false,
                    'is_returnable' => true,
                    'default_label' => 'Default Label',
                    'labels' => [
                        (int)$storeFixture->getId() => 'Standardbezeichnung',
                    ],
                ],
            ],
            'validator' => $this->objectManager->get(StaticAttributeValidator::class),
        ]);
        $results = iterator_to_array(iterator: $provider->get(), preserve_keys: false);
        $this->assertCount(expectedCount: 1, haystack: $results);

        /** @var DataObject|ProductAttributeInterface $result */
        $result = array_shift($results);

        $this->assertSame(expected: 1000001, actual: $result->getAttributeId());
        $this->assertSame(expected: 'some_attribute', actual: $result->getAttributeCode());
        $this->assertTrue(condition: $result->getIsSearchable());
        $this->assertFalse(condition: $result->getIsFilterable());
        $this->assertTrue(condition: $result->getData(key: 'used_in_product_listing'));
        $this->assertSame(expected: 'Default Label', actual: $result->getDefaultFrontendLabel());

        $storeFrontLabels = $result->getFrontendLabels();
        $this->assertCount(expectedCount: 1, haystack: $storeFrontLabels);
        $storeFrontLabel = array_shift($storeFrontLabels);
        $this->assertSame(expected: 'Standardbezeichnung', actual: $storeFrontLabel->getLabel());
        $this->assertSame(expected: $storeFixture->getId(), actual: $storeFrontLabel->getStoreId());
    }

    public function testGet_ReturnsFilteredAttributes_WhenAttributeIdsSet(): void
    {
        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');
        ConfigFixture::setForStore(
            path: 'general/locale/code',
            value: 'de-DE',
            storeCode: $storeFixture->getCode(),
        );

        $provider = $this->instantiateTestObject([
            'attributes' => [
                'some_attribute' => [
                    'attribute_id' => 1000001,
                    'attribute_code' => 'some_attribute',
                    'is_searchable' => true,
                    'is_filterable' => false,
                    'is_returnable' => true,
                    'default_label' => 'Default Label',
                    'labels' => [
                        (int)$storeFixture->getId() => 'Standardbezeichnung',
                    ],
                ],
                'another_attribute' => [
                    'attribute_id' => 1000002,
                    'attribute_code' => 'another_attribute',
                    'is_filterable' => true,
                    'labels' => [
                        (int)$storeFixture->getId() => 'Ein weiteres Etikett',
                    ],
                ],
            ],
            'validator' => $this->objectManager->get(StaticAttributeValidator::class),
        ]);
        $results = iterator_to_array(iterator: $provider->get([1000002]), preserve_keys: false);
        $this->assertCount(expectedCount: 1, haystack: $results);

        /** @var DataObject|ProductAttributeInterface $result */
        $result = array_shift($results);

        $this->assertSame(expected: 1000002, actual: $result->getAttributeId());
        $this->assertSame(expected: 'another_attribute', actual: $result->getAttributeCode());
        $this->assertFalse(condition: $result->getIsSearchable());
        $this->assertTrue(condition: $result->getIsFilterable());
        $this->assertFalse(condition: $result->getData(key: 'used_in_product_listing'));
        $this->assertSame(expected: 'Another attribute', actual: $result->getDefaultFrontendLabel());

        $storeFrontLabels = $result->getFrontendLabels();
        $this->assertCount(expectedCount: 1, haystack: $storeFrontLabels);
        $storeFrontLabel = array_shift($storeFrontLabels);
        $this->assertSame(expected: 'Ein weiteres Etikett', actual: $storeFrontLabel->getLabel());
        $this->assertSame(expected: $storeFixture->getId(), actual: $storeFrontLabel->getStoreId());
    }

    public function testGetByAttributeCode_ReturnsNull_WHenAttributeDoesNotExist(): void
    {
        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');
        ConfigFixture::setForStore(
            path: 'general/locale/code',
            value: 'de-DE',
            storeCode: $storeFixture->getCode(),
        );

        $provider = $this->instantiateTestObject([
            'attributes' => [
                'some_attribute' => [
                    'attribute_id' => 1000001,
                    'attribute_code' => 'some_attribute',
                    'is_searchable' => true,
                    'is_filterable' => false,
                    'is_returnable' => true,
                    'default_label' => 'Default Label',
                    'labels' => [
                        (int)$storeFixture->getId() => 'Standardbezeichnung',
                    ],
                ],
            ],
            'validator' => $this->objectManager->get(StaticAttributeValidator::class),
        ]);
        /** @var ProductAttributeInterface $result */
        $result = $provider->getByAttributeCode('another_attribute');

        $this->assertNull(actual: $result);
    }

    public function testGetByAttributeCode_ReturnsTheCorrectAttribute(): void
    {
        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');
        ConfigFixture::setForStore(
            path: 'general/locale/code',
            value: 'de-DE',
            storeCode: $storeFixture->getCode(),
        );

        $provider = $this->instantiateTestObject([
            'attributes' => [
                'some_attribute' => [
                    'attribute_id' => 1000001,
                    'attribute_code' => 'some_attribute',
                    'is_searchable' => true,
                    'is_returnable' => true,
                    'default_label' => 'Default Label',
                    'labels' => [
                        (int)$storeFixture->getId() => 'Standardbezeichnung',
                    ],
                ],
                'another_attribute' => [
                    'attribute_id' => 1000002,
                    'attribute_code' => 'another_attribute',
                    'is_searchable' => false,
                    'is_filterable' => true,
                    'is_returnable' => false,
                    'default_label' => 'Another Label',
                    'labels' => [
                        (int)$storeFixture->getId() => 'Ein weiteres Etikett',
                    ],
                ],
            ],
            'validator' => $this->objectManager->get(StaticAttributeValidator::class),
        ]);
        /** @var DataObject|ProductAttributeInterface $result */
        $result = $provider->getByAttributeCode('another_attribute');

        $this->assertSame(expected: 1000002, actual: $result->getAttributeId());
        $this->assertSame(expected: 'another_attribute', actual: $result->getAttributeCode());
        $this->assertFalse(condition: $result->getIsSearchable());
        $this->assertTrue(condition: $result->getIsFilterable());
        $this->assertFalse(condition: $result->getData(key: 'used_in_product_listing'));
        $this->assertSame(expected: 'Another Label', actual: $result->getDefaultFrontendLabel());

        $storeFrontLabels = $result->getFrontendLabels();
        $this->assertCount(expectedCount: 1, haystack: $storeFrontLabels);
        $storeFrontLabel = array_shift($storeFrontLabels);
        $this->assertSame(expected: 'Ein weiteres Etikett', actual: $storeFrontLabel->getLabel());
        $this->assertSame(expected: $storeFixture->getId(), actual: $storeFrontLabel->getStoreId());
    }

    public function testGet_ReturnsGeneratorOfAttributes_IncludingParentSku(): void
    {
        $provider = $this->instantiateTestObject();
        $results = iterator_to_array(iterator: $provider->get(), preserve_keys: false);
        $this->assertCount(expectedCount: 1, haystack: $results);

        $resultArray = array_filter(
            $results,
            static fn (DataObject|ProductAttributeInterface $attribute): bool => (
                $attribute->getAttributeCode() === KlevuParentSkuInterface::ATTRIBUTE_CODE
            ),
        );
        /** @var DataObject|ProductAttributeInterface $result */
        $result = array_shift($resultArray);

        $this->assertSame(
            expected: KlevuParentSkuInterface::ATTRIBUTE_ID,
            actual: $result->getAttributeId(),
        );
        $this->assertSame(
            expected: KlevuParentSkuInterface::ATTRIBUTE_CODE,
            actual: $result->getAttributeCode(),
        );
        $this->assertSame(
            expected: KlevuParentSkuInterface::IS_SEARCHABLE,
            actual: $result->getIsSearchable(),
        );
        $this->assertSame(
            expected: KlevuParentSkuInterface::IS_FILTERABLE,
            actual: $result->getIsFilterable(),
        );
        $this->assertSame(
            expected: KlevuParentSkuInterface::IS_RETURNABLE,
            actual: $result->getData(key: 'used_in_product_listing'),
        );
        $this->assertSame(
            expected: KlevuParentSkuInterface::ATTRIBUTE_LABEL,
            actual: $result->getDefaultFrontendLabel(),
        );
    }
}
