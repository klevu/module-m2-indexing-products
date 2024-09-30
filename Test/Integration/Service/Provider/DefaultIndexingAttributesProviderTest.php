<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Test\Integration\Service\Provider;

use Klevu\Configuration\Service\Provider\ScopeProviderInterface;
use Klevu\Indexing\Cache\Attributes as AttributesCache;
use Klevu\Indexing\Service\Provider\DefaultIndexingAttributesProvider;
use Klevu\IndexingApi\Model\Source\IndexType;
use Klevu\IndexingApi\Service\Provider\DefaultIndexingAttributesProviderInterface;
use Klevu\IndexingApi\Service\Provider\Sdk\AttributesProviderInterface;
use Klevu\IndexingApi\Service\Provider\StandardAttributesProviderInterface;
use Klevu\IndexingProducts\Model\Attribute\KlevuImageInterface;
use Klevu\IndexingProducts\Model\Attribute\KlevuRatingCountInterface;
use Klevu\IndexingProducts\Model\Attribute\KlevuRatingInterface;
use Klevu\IndexingProducts\Service\Provider\DefaultIndexingAttributesProvider as DefaultIndexingAttributesProviderVirtualType; // phpcs:ignore Generic.Files.LineLength.TooLong
use Klevu\PhpSDK\Model\AccountCredentials;
use Klevu\PhpSDK\Model\Indexing\Attribute;
use Klevu\PhpSDK\Model\Indexing\AttributeIterator;
use Klevu\PhpSDK\Model\Indexing\DataType;
use Klevu\PhpSDK\Service\Indexing\AttributesService;
use Klevu\TestFixtures\Store\StoreFixturesPool;
use Klevu\TestFixtures\Store\StoreTrait;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\SetAuthKeysTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Magento\Catalog\Api\Data\ProductAttributeInterface;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Framework\App\Cache\StateInterface;
use Magento\Framework\App\Cache\TypeList;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Klevu\Indexing\Service\Provider\DefaultIndexingAttributesProvider::class
 * @method DefaultIndexingAttributesProviderInterface instantiateTestObject(?array $arguments = null)
 * @method DefaultIndexingAttributesProviderInterface instantiateTestObjectFromInterface(?array $arguments = null)
 */
class DefaultIndexingAttributesProviderTest extends TestCase
{
    use ObjectInstantiationTrait;
    use SetAuthKeysTrait;
    use StoreTrait;
    use TestImplementsInterfaceTrait;

    /**
     * @var ObjectManagerInterface|null
     */
    private ?ObjectManagerInterface $objectManager = null; //@phpstan-ignore-line

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->objectManager = Bootstrap::getObjectManager();

        $this->implementationFqcn = DefaultIndexingAttributesProviderVirtualType::class;
        $this->interfaceFqcn = DefaultIndexingAttributesProviderInterface::class;
        $this->implementationForVirtualType = DefaultIndexingAttributesProvider::class;

        $this->storeFixturesPool = $this->objectManager->get(StoreFixturesPool::class);
        $this->clearAttributesCache();
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

    public function testGet_ReturnsAttributeList(): void
    {
        $provider = $this->instantiateTestObject();
        $attributes = $provider->get();

        $this->assertArrayHasKey(key: ProductAttributeInterface::CODE_DESCRIPTION, array: $attributes);
        $this->assertSame(
            expected: IndexType::INDEX,
            actual: $attributes[ProductAttributeInterface::CODE_DESCRIPTION],
        );

        $this->assertArrayHasKey(key: KlevuImageInterface::ATTRIBUTE_CODE, array: $attributes);
        $this->assertSame(expected: IndexType::INDEX, actual: $attributes[KlevuImageInterface::ATTRIBUTE_CODE]);

        $this->assertArrayHasKey(key: ProductInterface::NAME, array: $attributes);
        $this->assertSame(expected: IndexType::INDEX, actual: $attributes[ProductInterface::NAME]);

        $this->assertArrayHasKey(key: ProductInterface::PRICE, array: $attributes);
        $this->assertSame(expected: IndexType::INDEX, actual: $attributes[ProductInterface::PRICE]);

        $this->assertArrayHasKey(key: KlevuRatingInterface::ATTRIBUTE_CODE, array: $attributes);
        $this->assertSame(expected: IndexType::INDEX, actual: $attributes[KlevuRatingInterface::ATTRIBUTE_CODE]);

        $this->assertArrayHasKey(key: KlevuRatingCountInterface::ATTRIBUTE_CODE, array: $attributes);
        $this->assertSame(expected: IndexType::INDEX, actual: $attributes[KlevuRatingCountInterface::ATTRIBUTE_CODE]);

        $this->assertArrayHasKey(key: ProductAttributeInterface::CODE_SHORT_DESCRIPTION, array: $attributes);
        $this->assertSame(
            expected: IndexType::INDEX,
            actual: $attributes[ProductAttributeInterface::CODE_SHORT_DESCRIPTION],
        );

        $this->assertArrayHasKey(key: ProductInterface::SKU, array: $attributes);
        $this->assertSame(expected: IndexType::INDEX, actual: $attributes[ProductInterface::SKU]);

        $this->assertArrayHasKey(key: ProductInterface::VISIBILITY, array: $attributes);
        $this->assertSame(expected: IndexType::INDEX, actual: $attributes[ProductInterface::VISIBILITY]);

        $this->assertArrayHasKey(key: ProductAttributeInterface::CODE_SEO_FIELD_URL_KEY, array: $attributes);
        $this->assertSame(
            expected: IndexType::INDEX,
            actual: $attributes[ProductAttributeInterface::CODE_SEO_FIELD_URL_KEY],
        );
    }

    /**
     * @magentoAppIsolation enabled
     */
    public function testGet_ReturnsStandardAttributes_WhenNotStoreIntegrated(): void
    {
        $provider = $this->instantiateTestObject();
        $results = $provider->get();

        $this->assertArrayHasKey(key: 'description', array: $results);
        $this->assertArrayHasKey(key: 'klevu_image', array: $results);
        $this->assertArrayHasKey(key: 'klevu_rating', array: $results);
        $this->assertArrayHasKey(key: 'name', array: $results);
        $this->assertArrayHasKey(key: 'price', array: $results);
        $this->assertArrayHasKey(key: 'salePrice', array: $results);
        $this->assertArrayHasKey(key: 'sku', array: $results);
        $this->assertArrayHasKey(key: 'short_description', array: $results);
        $this->assertArrayHasKey(key: 'visibility', array: $results);
    }

    /**
     * @magentoAppIsolation enabled
     */
    public function testGet_ReturnsForAllApiKeys_WhenKeyNotSet(): void
    {
        $jsApiKey1 = 'klevu-js-key-1';
        $restAuthKey1 = 'klevu-rest-key-1';
        $this->createStore();
        $storeFixture1 = $this->storeFixturesPool->get('test_store');
        $scopeProvider1 = $this->objectManager->get(ScopeProviderInterface::class);
        $scopeProvider1->setCurrentScope($storeFixture1->get());
        $this->setAuthKeys(
            scopeProvider: $scopeProvider1,
            jsApiKey: $jsApiKey1,
            restAuthKey: $restAuthKey1,
        );
        $accountCredentials1 = new AccountCredentials(
            jsApiKey: $jsApiKey1,
            restAuthKey: $restAuthKey1,
        );

        $jsApiKey2 = 'klevu-js-key-2';
        $restAuthKey2 = 'klevu-rest-key-2';
        $this->createStore([
            'key' => 'test_store_2',
            'code' => 'klevu_test_store_2',
        ]);
        $storeFixture2 = $this->storeFixturesPool->get('test_store_2');
        $scopeProvider2 = $this->objectManager->get(ScopeProviderInterface::class);
        $scopeProvider2->setCurrentScope($storeFixture2->get());
        $this->setAuthKeys(
            scopeProvider: $scopeProvider2,
            jsApiKey: $jsApiKey2,
            restAuthKey: $restAuthKey2,
            removeApiKeys: false,
        );
        $accountCredentials2 = new AccountCredentials(
            jsApiKey: $jsApiKey2,
            restAuthKey: $restAuthKey2,
        );

        $attributeIterator1 = $this->objectManager->create(AttributeIterator::class);
        $attributeIterator1->addItem(
            item: $this->objectManager->create(
                type: Attribute::class,
                arguments: [
                    'attributeName' => 'my_standard_attribute_1',
                    'datatype' => DataType::STRING->value,
                    'searchable' => true,
                    'filterable' => false,
                    'returnable' => true,
                    'immutable' => true,
                ],
            ),
        );
        $attributeIterator1->addItem(
            item: $this->objectManager->create(
                type: Attribute::class,
                arguments: [
                    'attributeName' => 'my_standard_attribute_2',
                    'datatype' => DataType::STRING->value,
                    'searchable' => true,
                    'filterable' => false,
                    'returnable' => true,
                    'immutable' => true,
                ],
            ),
        );
        $attributeIterator2 = $this->objectManager->create(AttributeIterator::class);
        $attributeIterator2->addItem(
            item: $this->objectManager->create(
                type: Attribute::class,
                arguments: [
                    'attributeName' => 'my_standard_attribute_3',
                    'datatype' => DataType::STRING->value,
                    'searchable' => true,
                    'filterable' => false,
                    'returnable' => true,
                    'immutable' => true,
                ],
            ),
        );
        $attributeIterator2->addItem(
            item: $this->objectManager->create(
                type: Attribute::class,
                arguments: [
                    'attributeName' => 'my_custom_attribute_4',
                    'datatype' => DataType::NUMBER->value,
                    'searchable' => false,
                    'filterable' => true,
                    'returnable' => false,
                    'immutable' => false,
                ],
            ),
        );

        $mockAttributesService = $this->getMockBuilder(AttributesService::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockAttributesService->method('get')
            ->withConsecutive([$accountCredentials1], [$accountCredentials2])
            ->willReturnOnConsecutiveCalls($attributeIterator1, $attributeIterator2);

        $attributesProvider = $this->objectManager->create(AttributesProviderInterface::class, [
            'attributesService' => $mockAttributesService,
        ]);
        $standardAttributeProvider = $this->objectManager->create(
            StandardAttributesProviderInterface::class,
            [
                'attributesProvider' => $attributesProvider,
            ],
        );

        $provider = $this->instantiateTestObject([
            'standardAttributesProvider' => $standardAttributeProvider,
        ]);
        $results = $provider->get();

        $this->assertArrayNotHasKey(key: 'desc', array: $results);
        $this->assertArrayNotHasKey(key: 'name', array: $results);
        $this->assertArrayNotHasKey(key: 'sku', array: $results);
        $this->assertArrayHasKey(key: 'my_standard_attribute_1', array: $results);
        $this->assertArrayHasKey(key: 'my_standard_attribute_2', array: $results);
        $this->assertArrayHasKey(key: 'my_standard_attribute_3', array: $results);
        $this->assertArrayNotHasKey(key: 'my_custom_attribute_4', array: $results);
    }

    /**
     * @magentoAppIsolation enabled
     */
    public function testGet_ReturnsForApiKey_WhenKeySet(): void
    {
        $jsApiKey1 = 'klevu-js-key-1';
        $restAuthKey1 = 'klevu-rest-key-1';
        $this->createStore();
        $storeFixture1 = $this->storeFixturesPool->get('test_store');
        $scopeProvider1 = $this->objectManager->get(ScopeProviderInterface::class);
        $scopeProvider1->setCurrentScope($storeFixture1->get());
        $this->setAuthKeys(
            scopeProvider: $scopeProvider1,
            jsApiKey: $jsApiKey1,
            restAuthKey: $restAuthKey1,
        );

        $jsApiKey2 = 'klevu-js-key-2';
        $restAuthKey2 = 'klevu-rest-key-2';
        $this->createStore([
            'key' => 'test_store_2',
            'code' => 'klevu_test_store_2',
        ]);
        $storeFixture2 = $this->storeFixturesPool->get('test_store_2');
        $scopeProvider2 = $this->objectManager->get(ScopeProviderInterface::class);
        $scopeProvider2->setCurrentScope($storeFixture2->get());
        $this->setAuthKeys(
            scopeProvider: $scopeProvider2,
            jsApiKey: $jsApiKey2,
            restAuthKey: $restAuthKey2,
            removeApiKeys: false,
        );
        $accountCredentials2 = new AccountCredentials(
            jsApiKey: $jsApiKey2,
            restAuthKey: $restAuthKey2,
        );

        $attributeIterator1 = $this->objectManager->create(AttributeIterator::class);
        $attributeIterator1->addItem(
            item: $this->objectManager->create(
                type: Attribute::class,
                arguments: [
                    'attributeName' => 'my_standard_attribute_1',
                    'datatype' => DataType::STRING->value,
                    'searchable' => true,
                    'filterable' => false,
                    'returnable' => true,
                    'immutable' => true,
                ],
            ),
        );
        $attributeIterator1->addItem(
            item: $this->objectManager->create(
                type: Attribute::class,
                arguments: [
                    'attributeName' => 'my_standard_attribute_2',
                    'datatype' => DataType::STRING->value,
                    'searchable' => true,
                    'filterable' => false,
                    'returnable' => true,
                    'immutable' => true,
                ],
            ),
        );
        $attributeIterator2 = $this->objectManager->create(AttributeIterator::class);
        $attributeIterator2->addItem(
            item: $this->objectManager->create(
                type: Attribute::class,
                arguments: [
                    'attributeName' => 'my_standard_attribute_3',
                    'datatype' => DataType::STRING->value,
                    'searchable' => true,
                    'filterable' => false,
                    'returnable' => true,
                    'immutable' => true,
                ],
            ),
        );
        $attributeIterator2->addItem(
            item: $this->objectManager->create(
                type: Attribute::class,
                arguments: [
                    'attributeName' => 'my_custom_attribute_4',
                    'datatype' => DataType::NUMBER->value,
                    'searchable' => false,
                    'filterable' => true,
                    'returnable' => false,
                    'immutable' => false,
                ],
            ),
        );

        $mockAttributesService = $this->getMockBuilder(AttributesService::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockAttributesService->expects($this->once())
            ->method('get')
            ->with($accountCredentials2)
            ->willReturn($attributeIterator2);

        $attributesProvider = $this->objectManager->create(AttributesProviderInterface::class, [
            'attributesService' => $mockAttributesService,
        ]);
        $standardAttributeProvider = $this->objectManager->create(
            StandardAttributesProviderInterface::class,
            [
                'attributesProvider' => $attributesProvider,
            ],
        );

        $provider = $this->instantiateTestObject([
            'standardAttributesProvider' => $standardAttributeProvider,
        ]);
        $results = $provider->get(apiKey: $jsApiKey2);

        $this->assertArrayNotHasKey(key: 'desc', array: $results);
        $this->assertArrayNotHasKey(key: 'name', array: $results);
        $this->assertArrayNotHasKey(key: 'sku', array: $results);
        $this->assertArrayNotHasKey(key: 'my_standard_attribute_1', array: $results);
        $this->assertArrayNotHasKey(key: 'my_standard_attribute_2', array: $results);
        $this->assertArrayHasKey(key: 'my_standard_attribute_3', array: $results);
        $this->assertArrayNotHasKey(key: 'my_custom_attribute_4', array: $results);
    }

    /**
     * @return void
     */
    private function clearAttributesCache(): void
    {
        $cacheState = $this->objectManager->get(type: StateInterface::class);
        $cacheState->setEnabled(cacheType: AttributesCache::TYPE_IDENTIFIER, isEnabled: true);

        $typeList = $this->objectManager->get(TypeList::class);
        $typeList->cleanType(AttributesCache::TYPE_IDENTIFIER);
    }
}
