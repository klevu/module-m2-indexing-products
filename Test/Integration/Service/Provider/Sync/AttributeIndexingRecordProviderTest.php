<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Test\Integration\Service\Provider\Sync;

use Klevu\Configuration\Service\Provider\ScopeProviderInterface;
use Klevu\Indexing\Model\IndexingAttribute;
use Klevu\Indexing\Service\Mapper\MagentoToKlevuAttributeMapper;
use Klevu\Indexing\Service\Provider\Sync\AttributeIndexingRecordProvider;
use Klevu\Indexing\Test\Integration\Traits\IndexingAttributesTrait;
use Klevu\IndexingApi\Model\Source\Actions;
use Klevu\IndexingApi\Model\Source\IndexType;
use Klevu\IndexingApi\Service\AttributeIndexingRecordCreatorServiceInterface;
use Klevu\IndexingApi\Service\Provider\Sync\AttributeIndexingRecordProviderInterface;
use Klevu\IndexingProducts\Model\Source\Aspect;
use Klevu\IndexingProducts\Service\AttributeIndexingRecordCreatorService;
use Klevu\IndexingProducts\Service\Provider\Sync\AttributeIndexingRecordProvider as AttributeIndexingRecordIndexingRecordProviderVirtualType; // phpcs:ignore Generic.Files.LineLength.TooLong
use Klevu\PhpSDK\Api\Model\Indexing\AttributeInterface as SdkAttributeInterface;
use Klevu\TestFixtures\Catalog\Attribute\AttributeFixturePool;
use Klevu\TestFixtures\Catalog\AttributeTrait;
use Klevu\TestFixtures\Store\StoreFixturesPool;
use Klevu\TestFixtures\Store\StoreTrait;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\SetAuthKeysTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Magento\Catalog\Api\Data\CategoryAttributeInterface;
use Magento\Catalog\Api\Data\ProductAttributeInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * @covers AttributeIndexingRecordProvider
 * @method AttributeIndexingRecordProviderInterface instantiateTestObject(?array $arguments = null)
 * @method AttributeIndexingRecordProviderInterface instantiateTestObjectFromInterface(?array $arguments = null)
 */
class AttributeIndexingRecordProviderTest extends TestCase
{
    use AttributeTrait;
    use IndexingAttributesTrait;
    use ObjectInstantiationTrait;
    use SetAuthKeysTrait;
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

        // @phpstan-ignore-next-line
        $this->implementationFqcn = AttributeIndexingRecordIndexingRecordProviderVirtualType::class;
        $this->interfaceFqcn = AttributeIndexingRecordProviderInterface::class;
        $this->implementationForVirtualType = AttributeIndexingRecordProvider::class;
        $this->constructorArgumentDefaults = [
            'action' => 'Add',
        ];
        $this->objectManager = Bootstrap::getObjectManager();
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
    }

    /**
     * @magentoAppIsolation enabled
     * @magentoDbIsolation disabled
     * @magentoConfigFixture default/general/locale/code en_US
     * @magentoConfigFixture klevu_test_store_1_store general/locale/code en_GB
     */
    public function testGet_WithAction_ReturnsIndexingRecordsForAction(): void
    {
        $apiKey = 'klevu-test-api-key';

        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');
        $scopeProvider = $this->objectManager->create(ScopeProviderInterface::class);
        $scopeProvider->setCurrentScope(scope: $storeFixture->get());
        $this->setAuthKeys(
            scopeProvider: $scopeProvider,
            jsApiKey: $apiKey,
            restAuthKey: 'klevu-rest-key-1',
        );

        $this->createAttribute([
            'key' => 'klevu_product_text_attribute',
            'code' => 'klevu_product_text_attribute',
            'attribute_type' => 'text',
            'label' => 'TEST ATTRIBUTE',
            'labels' => [
                $storeFixture->getId() => 'Label Store 1',
            ],
            'data' => [
                'is_searchable' => true,
                'is_filterable' => false,
                'used_in_product_listing' => true,
            ],
            'index_as' => IndexType::INDEX,
            'aspect' => Aspect::ATTRIBUTES,
        ]);
        $attributeFixture1 = $this->attributeFixturePool->get('klevu_product_text_attribute');
        $productAttribute = $attributeFixture1->getAttribute();

        $this->createAttribute([
            'key' => 'klevu_category_text_attribute',
            'code' => 'klevu_category_text_attribute',
            'attribute_type' => 'text',
            'entity_type' => CategoryAttributeInterface::ENTITY_TYPE_CODE,
        ]);
        $attributeFixture2 = $this->attributeFixturePool->get('klevu_category_text_attribute');
        $categoryAttribute = $attributeFixture2->getAttribute();

        $this->createAttribute([
            'key' => 'klevu_product_text_attribute_2',
            'code' => 'klevu_product_text_attribute_2',
            'attribute_type' => 'text',
        ]);
        $attributeFixture3 = $this->attributeFixturePool->get('klevu_product_text_attribute_2');
        $productAttribute2 = $attributeFixture3->getAttribute();

        $this->cleanIndexingAttributes(apiKey: $apiKey);
        $this->createIndexingAttribute([
            IndexingAttribute::API_KEY => $apiKey,
            IndexingAttribute::TARGET_ID => $productAttribute->getAttributeId(),
            IndexingAttribute::TARGET_CODE => $productAttribute->getAttributeCode(),
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE => 'KLEVU_PRODUCT',
            IndexingAttribute::NEXT_ACTION => Actions::ADD,
        ]);
        $this->createIndexingAttribute([
            IndexingAttribute::API_KEY => $apiKey,
            IndexingAttribute::TARGET_ID => $categoryAttribute->getAttributeId(),
            IndexingAttribute::TARGET_CODE => $categoryAttribute->getAttributeCode(),
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE => 'KLEVU_CATEGORY',
            IndexingAttribute::NEXT_ACTION => Actions::ADD,
        ]);
        $this->createIndexingAttribute([
            IndexingAttribute::API_KEY => $apiKey,
            IndexingAttribute::TARGET_ID => $productAttribute2->getAttributeId(),
            IndexingAttribute::TARGET_CODE => $productAttribute2->getAttributeCode(),
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE => 'KLEVU_PRODUCT',
            IndexingAttribute::NEXT_ACTION => Actions::ADD,
            IndexingAttribute::IS_INDEXABLE => false,
        ]);

        $mockLogger = $this->getMockBuilder(LoggerInterface::class)
            ->getMock();
        $mockLogger->expects($this->never())
            ->method('error');

        $provider = $this->instantiateTestObject([
            'logger' => $mockLogger,
            'action' => 'Add',
        ]);
        $result = $provider->get($apiKey);

        $indexingRecords = [];
        foreach ($result as $indexingRecord) {
            $indexingRecords[] = $indexingRecord;
        }
        $filteredResult = array_filter(
            array: $indexingRecords,
            callback: static fn (SdkAttributeInterface $indexingRecord): bool => (
                $indexingRecord->getAttributeName() === $productAttribute->getAttributeCode()
            ),
        );
        $this->assertCount(expectedCount: 1, haystack: $filteredResult);

        $filteredResult = array_filter(
            array: $indexingRecords,
            callback: static fn (SdkAttributeInterface $indexingRecord): bool => (
                $indexingRecord->getAttributeName() === 'cat-' . $categoryAttribute->getAttributeCode()
            ),
        );
        $this->assertCount(expectedCount: 0, haystack: $filteredResult);

        $filteredResult = array_filter(
            array: $indexingRecords,
            callback: static fn (SdkAttributeInterface $indexingRecord): bool => (
                $indexingRecord->getAttributeName() === $productAttribute2->getAttributeCode()
            ),
        );
        $this->assertCount(expectedCount: 0, haystack: $filteredResult);

        $this->cleanIndexingAttributes(apiKey: $apiKey);
    }

    /**
     * @magentoAppIsolation enabled
     * @magentoConfigFixture default/general/locale/code en_US
     * @magentoConfigFixture klevu_test_store_1_store general/locale/code en_GB
     */
    public function testGet_LogsError_WhenAttributeMappingMissingExceptionThrown(): void
    {
        $apiKey = 'klevu-test-api-key';

        $this->createStore([
            'key' => 'test_store_1',
            'code' => 'klevu_test_store_1',
        ]);
        $storeFixture1 = $this->storeFixturesPool->get('test_store_1');
        $scopeProvider1 = $this->objectManager->create(ScopeProviderInterface::class);
        $scopeProvider1->setCurrentScope(scope: $storeFixture1->get());
        $this->setAuthKeys(
            scopeProvider: $scopeProvider1,
            jsApiKey: $apiKey,
            restAuthKey: 'klevu-rest-key-1',
        );

        $this->createAttribute([
            'key' => 'klevu_attribute_1',
            'code' => 'klevu_attribute_1',
            'attribute_type' => 'text',
            'index_as' => IndexType::INDEX,
            'aspect' => Aspect::ATTRIBUTES,
        ]);
        $attributeFixture1 = $this->attributeFixturePool->get('klevu_attribute_1');
        $productAttribute1 = $attributeFixture1->getAttribute();

        $this->createAttribute([
            'key' => 'klevu_attribute_2',
            'code' => 'klevu_attribute_2',
            'attribute_type' => 'text',
            'index_as' => IndexType::INDEX,
            'aspect' => Aspect::ATTRIBUTES,
        ]);
        $attributeFixture2 = $this->attributeFixturePool->get('klevu_attribute_2');
        $productAttribute2 = $attributeFixture2->getAttribute();

        $this->cleanIndexingAttributes(apiKey: $apiKey);
        $this->createIndexingAttribute([
            IndexingAttribute::API_KEY => $apiKey,
            IndexingAttribute::TARGET_ID => $productAttribute1->getAttributeId(),
            IndexingAttribute::TARGET_CODE => $productAttribute1->getAttributeCode(),
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE => 'KLEVU_PRODUCT',
            IndexingAttribute::NEXT_ACTION => Actions::ADD,
        ]);
        $this->createIndexingAttribute([
            IndexingAttribute::API_KEY => $apiKey,
            IndexingAttribute::TARGET_ID => $productAttribute2->getAttributeId(),
            IndexingAttribute::TARGET_CODE => $productAttribute2->getAttributeCode(),
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE => 'KLEVU_PRODUCT',
            IndexingAttribute::NEXT_ACTION => Actions::ADD,
        ]);

        $mockLogger = $this->getMockBuilder(LoggerInterface::class)
            ->getMock();
        $mockLogger->expects($this->once())
            ->method('error')
            ->with(
                'Method: {method}, Error: {message}, Attribute: {attribute_code}',
                [
                    // phpcs:ignore Generic.Files.LineLength.TooLong
                    'method' => 'Klevu\Indexing\Service\Provider\Sync\AttributeIndexingRecordProvider::syncAttributes',
                    'message' => sprintf(
                        'Attribute mapping for Magento attribute %s is missing. '
                        . 'Klevu attribute %s is mapped to Magento attribute %s. '
                        . '2 Magento attributes can not be mapped to the same Klevu attribute. '
                        . 'Either add mapping for Magento attribute %s or set it not to be indexable.',
                        $productAttribute2->getAttributeCode(),
                        $productAttribute2->getAttributeCode(),
                        $productAttribute1->getAttributeCode(),
                        $productAttribute2->getAttributeCode(),
                    ),
                    'attribute_code' => $productAttribute2->getAttributeCode(),
                ],
            );

        $magentoToKlevuAttributeMapper = $this->objectManager->create(MagentoToKlevuAttributeMapper::class, [
            'entityType' => ProductAttributeInterface::ENTITY_TYPE_CODE,
            'attributeMapping' => [
                'klevu_attribute_1' => 'klevu_attribute_2',
            ],
        ]);
        $attributeIndexingRecordCreatorService = $this->objectManager->create(
            type: AttributeIndexingRecordCreatorService::class,
            arguments: [
                'attributeMapperService' => $magentoToKlevuAttributeMapper,
            ],
        );

        $provider = $this->instantiateTestObject([
            'indexingRecordCreatorService' => $attributeIndexingRecordCreatorService,
            'logger' => $mockLogger,
            'action' => 'Add',
        ]);
        $result = $provider->get($apiKey);

        $indexingRecords = [];
        foreach ($result as $indexingRecord) {
            $indexingRecords[] = $indexingRecord;
        }
        $this->assertCount(expectedCount: 1, haystack: $indexingRecords);
        $this->cleanIndexingAttributes(apiKey: $apiKey);
    }

    /**
     * @magentoAppIsolation enabled
     * @magentoConfigFixture default/general/locale/code en_US
     * @magentoConfigFixture klevu_test_store_1_store general/locale/code en_GB
     */
    public function testGet_NoErrorLogged_WhenMissingMappingIsForNonIndexableAttribute(): void
    {
        $apiKey = 'klevu-test-api-key';

        $this->createStore([
            'key' => 'test_store_1',
            'code' => 'klevu_test_store_1',
        ]);
        $storeFixture1 = $this->storeFixturesPool->get('test_store_1');
        $scopeProvider1 = $this->objectManager->create(ScopeProviderInterface::class);
        $scopeProvider1->setCurrentScope(scope: $storeFixture1->get());
        $this->setAuthKeys(
            scopeProvider: $scopeProvider1,
            jsApiKey: $apiKey,
            restAuthKey: 'klevu-rest-key-1',
        );

        $this->createAttribute([
            'key' => 'klevu_attribute_1',
            'code' => 'klevu_attribute_1',
            'attribute_type' => 'text',
            'index_as' => IndexType::INDEX,
            'aspect' => Aspect::ATTRIBUTES,
        ]);
        $attributeFixture1 = $this->attributeFixturePool->get('klevu_attribute_1');
        $productAttribute1 = $attributeFixture1->getAttribute();

        $this->createAttribute([
            'key' => 'klevu_attribute_2',
            'code' => 'klevu_attribute_2',
            'attribute_type' => 'text',
            'index_as' => IndexType::NO_INDEX,
            'aspect' => Aspect::ATTRIBUTES,
        ]);
        $attributeFixture2 = $this->attributeFixturePool->get('klevu_attribute_2');
        $productAttribute2 = $attributeFixture2->getAttribute();

        $this->cleanIndexingAttributes(apiKey: $apiKey);
        $this->createIndexingAttribute([
            IndexingAttribute::API_KEY => $apiKey,
            IndexingAttribute::TARGET_ID => $productAttribute1->getAttributeId(),
            IndexingAttribute::TARGET_CODE => $productAttribute1->getAttributeCode(),
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE => 'KLEVU_PRODUCT',
            IndexingAttribute::NEXT_ACTION => Actions::ADD,
        ]);
        $this->createIndexingAttribute([
            IndexingAttribute::API_KEY => $apiKey,
            IndexingAttribute::TARGET_ID => $productAttribute2->getAttributeId(),
            IndexingAttribute::TARGET_CODE => $productAttribute2->getAttributeCode(),
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE => 'KLEVU_PRODUCT',
            IndexingAttribute::NEXT_ACTION => Actions::NO_ACTION,
        ]);

        $mockLogger = $this->getMockBuilder(LoggerInterface::class)
            ->getMock();
        $mockLogger->expects($this->never())
            ->method('error');

        $magentoToKlevuAttributeMapper = $this->objectManager->create(MagentoToKlevuAttributeMapper::class, [
            'entityType' => ProductAttributeInterface::ENTITY_TYPE_CODE,
            'attributeMapping' => [
                'klevu_attribute_1' => 'klevu_attribute_2',
            ],
        ]);
        $attributeIndexingRecordCreatorService = $this->objectManager->create(
            type: AttributeIndexingRecordCreatorService::class,
            arguments: [
                'attributeMapperService' => $magentoToKlevuAttributeMapper,
            ],
        );

        $provider = $this->instantiateTestObject([
            'indexingRecordCreatorService' => $attributeIndexingRecordCreatorService,
            'logger' => $mockLogger,
            'action' => 'Add',
        ]);
        $result = $provider->get($apiKey);

        $indexingRecords = [];
        foreach ($result as $indexingRecord) {
            $indexingRecords[] = $indexingRecord;
        }
        $this->assertCount(expectedCount: 1, haystack: $indexingRecords);
    }

    /**
     * @magentoAppIsolation enabled
     * @magentoConfigFixture default/general/locale/code en_US
     * @magentoConfigFixture klevu_test_store_1_store general/locale/code en_GB
     */
    public function testGet_LogsError_WhenTypeErrorThrown(): void
    {
        $apiKey = 'klevu-test-api-key';

        $typeErrorMessage = 'Klevu\PhpSDK\Model\Indexing\Attribute::addLabel(): '
            . 'Argument #1 ($label) must be of type string, null given, called in '
            . '/var/www/html/magento2-plugin/php-sdk/src/Model/Indexing/Attribute.php on line 224';

        $this->createStore([
            'key' => 'test_store_1',
            'code' => 'klevu_test_store_1',
        ]);
        $storeFixture1 = $this->storeFixturesPool->get('test_store_1');
        $scopeProvider1 = $this->objectManager->create(ScopeProviderInterface::class);
        $scopeProvider1->setCurrentScope(scope: $storeFixture1->get());
        $this->setAuthKeys(
            scopeProvider: $scopeProvider1,
            jsApiKey: $apiKey,
            restAuthKey: 'klevu-rest-key-1',
        );

        $this->createAttribute([
            'key' => 'klevu_attribute_1',
            'code' => 'klevu_attribute_1',
            'attribute_type' => 'text',
            'index_as' => IndexType::INDEX,
            'aspect' => Aspect::ATTRIBUTES,
        ]);
        $attributeFixture1 = $this->attributeFixturePool->get('klevu_attribute_1');
        $productAttribute1 = $attributeFixture1->getAttribute();

        $this->cleanIndexingAttributes(apiKey: $apiKey);
        $this->createIndexingAttribute([
            IndexingAttribute::API_KEY => $apiKey,
            IndexingAttribute::TARGET_ID => $productAttribute1->getAttributeId(),
            IndexingAttribute::TARGET_CODE => $productAttribute1->getAttributeCode(),
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE => 'KLEVU_PRODUCT',
            IndexingAttribute::NEXT_ACTION => Actions::ADD,
        ]);

        $mockLogger = $this->getMockBuilder(LoggerInterface::class)
            ->getMock();
        $mockLogger->expects($this->once())
            ->method('error')
            ->with(
                'Method: {method}, Error: {message}, Attribute: {attribute_code}',
                [
                    // phpcs:ignore Generic.Files.LineLength.TooLong
                    'method' => 'Klevu\Indexing\Service\Provider\Sync\AttributeIndexingRecordProvider::syncAttributes',
                    'message' => $typeErrorMessage,
                    'attribute_code' => $productAttribute1->getAttributeCode(),
                ],
            );

        $mockAttributeIndexingRecordCreatorService = $this->getMockBuilder(
            className: AttributeIndexingRecordCreatorServiceInterface::class,
        )
            ->disableOriginalConstructor()
            ->getMock();
        $mockAttributeIndexingRecordCreatorService->method('execute')
            ->willThrowException(
                new \TypeError($typeErrorMessage),
            );

        $provider = $this->instantiateTestObject([
            'indexingRecordCreatorService' => $mockAttributeIndexingRecordCreatorService,
            'logger' => $mockLogger,
            'action' => 'Add',
        ]);
        $result = $provider->get($apiKey);

        $indexingRecords = [];
        foreach ($result as $indexingRecord) {
            $indexingRecords[] = $indexingRecord;
        }
        $this->assertCount(expectedCount: 0, haystack: $indexingRecords);
    }
}
