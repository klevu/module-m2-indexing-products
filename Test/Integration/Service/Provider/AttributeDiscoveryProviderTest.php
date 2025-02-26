<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Test\Integration\Service\Provider;

use Klevu\Configuration\Service\Provider\ScopeProviderInterface;
use Klevu\Indexing\Service\Provider\AttributeDiscoveryProvider;
use Klevu\IndexingApi\Model\MagentoAttributeInterface;
use Klevu\IndexingApi\Model\Source\IndexType;
use Klevu\IndexingApi\Service\Provider\AttributeDiscoveryProviderInterface;
use Klevu\IndexingProducts\Model\Attribute\KlevuParentSkuInterface;
use Klevu\IndexingProducts\Service\Provider\AttributeDiscoveryProvider as AttributeDiscoveryProviderVirtualType;
use Klevu\TestFixtures\Catalog\Attribute\AttributeFixturePool;
use Klevu\TestFixtures\Catalog\AttributeTrait;
use Klevu\TestFixtures\Store\StoreFixturesPool;
use Klevu\TestFixtures\Store\StoreTrait;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\SetAuthKeysTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Klevu\TestFixtures\Website\WebsiteFixturesPool;
use Klevu\TestFixtures\Website\WebsiteTrait;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Klevu\Indexing\Service\Provider\AttributeDiscoveryProvider::class
 * @method AttributeDiscoveryProviderInterface instantiateTestObject(?array $arguments = null)
 * @method AttributeDiscoveryProviderInterface instantiateTestObjectFromInterface(?array $arguments = null)
 */
class AttributeDiscoveryProviderTest extends TestCase
{
    use AttributeTrait;
    use ObjectInstantiationTrait;
    use SetAuthKeysTrait;
    use StoreTrait;
    use TestImplementsInterfaceTrait;
    use WebsiteTrait;

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

        $this->implementationFqcn = AttributeDiscoveryProviderVirtualType::class; // @phpstan-ignore-line
        $this->interfaceFqcn = AttributeDiscoveryProviderInterface::class;
        $this->implementationForVirtualType = AttributeDiscoveryProvider::class;
        $this->objectManager = Bootstrap::getObjectManager();
        $this->websiteFixturesPool = $this->objectManager->get(WebsiteFixturesPool::class);
        $this->storeFixturesPool = $this->objectManager->get(StoreFixturesPool::class);
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
        $this->storeFixturesPool->rollback();
        $this->websiteFixturesPool->rollback();
    }

    public function testGetAttributeType_ReturnsCorrectString(): void
    {
        $provider = $this->instantiateTestObject();
        $this->assertSame(
            expected: 'KLEVU_PRODUCT',
            actual: $provider->getAttributeType(),
            message: 'Get Attribute Type',
        );
    }

    /**
     * @magentoDbIsolation disabled
     * @magentoConfigFixture klevu_test_store_1_store klevu_configuration/auth_keys/js_api_key klevu-js-api-key
     * @magentoConfigFixture klevu_test_store_1_store klevu_configuration/auth_keys/rest_auth_key klevu-rest-auth-key
     */
    public function testGetData_ReturnsIsIndexable_BasedOnIndexType(): void
    {
        $apiKey = 'klevu-js-api-key';

        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');
        $scopeProvider = $this->objectManager->get(ScopeProviderInterface::class);
        $scopeProvider->setCurrentScope(scope: $storeFixture->get());
        $this->setAuthKeys(
            $scopeProvider,
            $apiKey,
            'rest-auth-key',
        );
        $scopeProvider->unsetCurrentScope();

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
            'index_as' => IndexType::NO_INDEX,
            'attribute_type' => 'boolean',
        ]);
        $attribute2 = $this->attributeFixturePool->get('test_attribute_2');

        $provider = $this->instantiateTestObject();
        $productAttributesByApiKey = $provider->getData([$apiKey]);
        $this->assertCount(expectedCount: 1, haystack: $productAttributesByApiKey);
        $productAttributes = $productAttributesByApiKey[$apiKey];

        $this->assertArrayHasKey(key: $attribute1->getAttributeId(), array: $productAttributes);
        /** @var MagentoAttributeInterface $productAttribute1 */
        $productAttribute1 = $productAttributes[$attribute1->getAttributeId()];
        $this->assertSame(expected: (int)$attribute1->getAttributeId(), actual: $productAttribute1->getAttributeId());
        $this->assertSame(expected: $apiKey, actual: $productAttribute1->getApiKey());
        $this->assertTrue(condition: $productAttribute1->isIndexable());
        $this->assertSame(expected: 'klevu_test_attribute_1', actual: $productAttribute1->getKlevuAttributeName());

        $this->assertArrayHasKey(key: $attribute2->getAttributeId(), array: $productAttributes);
        /** @var MagentoAttributeInterface $productAttribute2 */
        $productAttribute2 = $productAttributes[$attribute2->getAttributeId()];
        $this->assertSame(expected: (int)$attribute2->getAttributeId(), actual: $productAttribute2->getAttributeId());
        $this->assertSame(expected: $apiKey, actual: $productAttribute2->getApiKey());
        $this->assertFalse(condition: $productAttribute2->isIndexable());
        $this->assertSame(expected: 'klevu_test_attribute_2', actual: $productAttribute2->getKlevuAttributeName());
    }

    /**
     * @magentoDbIsolation disabled
     * @magentoConfigFixture klevu_test_store_1_store klevu_configuration/auth_keys/js_api_key klevu-js-api-key
     * @magentoConfigFixture klevu_test_store_1_store klevu_configuration/auth_keys/rest_auth_key klevu-rest-auth-key
     * @magentoConfigFixture klevu_test_store_2_store klevu_configuration/auth_keys/js_api_key klevu-js-api-key
     * @magentoConfigFixture klevu_test_store_2_store klevu_configuration/auth_keys/rest_auth_key klevu-rest-auth-key
     */
    public function testGetData_ReturnsIsIndexable_BasedOnIndexType_Multistore(): void
    {
        $apiKey = 'klevu-js-api-key';

        $this->createStore([
            'key' => 'test_store_1',
            'code' => 'klevu_test_store_1',
        ]);
        $storeFixture1 = $this->storeFixturesPool->get('test_store_1');
        $scopeProvider = $this->objectManager->get(ScopeProviderInterface::class);
        $scopeProvider->setCurrentScope(scope: $storeFixture1->get());
        $this->setAuthKeys(
            $scopeProvider,
            $apiKey,
            'klevu-rest-auth-key',
        );
        $scopeProvider->unsetCurrentScope();

        $this->createStore([
            'key' => 'test_store_2',
            'code' => 'klevu_test_store_2',
        ]);
        $storeFixture2 = $this->storeFixturesPool->get('test_store_2');
        $scopeProvider = $this->objectManager->get(ScopeProviderInterface::class);
        $scopeProvider->setCurrentScope(scope: $storeFixture2->get());

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
            'index_as' => IndexType::NO_INDEX,
            'attribute_type' => 'boolean',
        ]);
        $attribute2 = $this->attributeFixturePool->get('test_attribute_2');

        $provider = $this->instantiateTestObject();
        $productAttributesByApiKey = $provider->getData([$apiKey]);
        $this->assertCount(expectedCount: 1, haystack: $productAttributesByApiKey);
        $productAttributes = $productAttributesByApiKey[$apiKey];

        $this->assertArrayHasKey(key: $attribute1->getAttributeId(), array: $productAttributes);
        /** @var MagentoAttributeInterface $productAttribute1 */
        $productAttribute1 = $productAttributes[$attribute1->getAttributeId()];
        $this->assertSame(expected: (int)$attribute1->getAttributeId(), actual: $productAttribute1->getAttributeId());
        $this->assertSame(expected: $apiKey, actual: $productAttribute1->getApiKey());
        $this->assertTrue(condition: $productAttribute1->isIndexable());
        $this->assertSame(expected: 'klevu_test_attribute_1', actual: $productAttribute1->getKlevuAttributeName());

        $this->assertArrayHasKey(key: $attribute2->getAttributeId(), array: $productAttributes);
        /** @var MagentoAttributeInterface $productAttribute2 */
        $productAttribute2 = $productAttributes[$attribute2->getAttributeId()];
        $this->assertSame(expected: (int)$attribute2->getAttributeId(), actual: $productAttribute2->getAttributeId());
        $this->assertSame(expected: $apiKey, actual: $productAttribute2->getApiKey());
        $this->assertFalse(condition: $productAttribute2->isIndexable());
        $this->assertSame(expected: 'klevu_test_attribute_2', actual: $productAttribute2->getKlevuAttributeName());
    }

    /**
     * @magentoDbIsolation disabled
     */
    public function testGetData_ReturnsParentSkuStaticAttribute_BasedOnIndexType(): void
    {
        $apiKey = 'klevu-js-api-key';

        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');
        $scopeProvider = $this->objectManager->get(ScopeProviderInterface::class);
        $scopeProvider->setCurrentScope(scope: $storeFixture->get());
        $this->setAuthKeys(
            $scopeProvider,
            $apiKey,
            'rest-auth-key',
        );
        $scopeProvider->unsetCurrentScope();

        $provider = $this->instantiateTestObject();
        $productAttributesByApiKey = $provider->getData([$apiKey]);
        $this->assertCount(expectedCount: 1, haystack: $productAttributesByApiKey);
        $productAttributes = $productAttributesByApiKey[$apiKey];

        $this->assertArrayHasKey(key: KlevuParentSkuInterface::ATTRIBUTE_ID, array: $productAttributes);
        /** @var MagentoAttributeInterface $parentSkuAttribute */
        $parentSkuAttribute = $productAttributes[KlevuParentSkuInterface::ATTRIBUTE_ID];
        $this->assertSame(
            expected: KlevuParentSkuInterface::ATTRIBUTE_ID,
            actual: $parentSkuAttribute->getAttributeId(),
        );
        $this->assertSame(expected: $apiKey, actual: $parentSkuAttribute->getApiKey());
        $this->assertTrue(condition: $parentSkuAttribute->isIndexable());
        $this->assertSame(
            expected: KlevuParentSkuInterface::ATTRIBUTE_CODE,
            actual: $parentSkuAttribute->getKlevuAttributeName(),
        );
    }
}
