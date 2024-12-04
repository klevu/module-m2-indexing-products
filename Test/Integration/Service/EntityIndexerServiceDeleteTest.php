<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Test\Integration\Service;

use Klevu\Configuration\Service\Provider\ScopeProviderInterface;
use Klevu\Configuration\Service\Provider\Sdk\BaseUrlsProvider;
use Klevu\Indexing\Model\IndexingEntity;
use Klevu\Indexing\Service\EntityIndexerService;
use Klevu\Indexing\Test\Integration\Traits\IndexingEntitiesTrait;
use Klevu\IndexingApi\Model\Source\Actions;
use Klevu\IndexingApi\Model\Source\IndexerResultStatuses;
use Klevu\IndexingApi\Service\EntityIndexerServiceInterface;
use Klevu\IndexingProducts\Constants;
use Klevu\IndexingProducts\Service\EntityIndexerService\Delete as EntityIndexerServiceVirtualType;
use Klevu\PhpSDK\Model\Indexing\RecordIterator;
use Klevu\PhpSDKPipelines\Model\ApiPipelineResult;
use Klevu\Pipelines\Exception\Pipeline\InvalidPipelineArgumentsException;
use Klevu\TestFixtures\Catalog\Attribute\AttributeFixturePool;
use Klevu\TestFixtures\Catalog\AttributeTrait;
use Klevu\TestFixtures\Catalog\CategoryTrait;
use Klevu\TestFixtures\Catalog\ProductTrait;
use Klevu\TestFixtures\Store\StoreFixturesPool;
use Klevu\TestFixtures\Store\StoreTrait;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\PipelineEntityApiCallTrait;
use Klevu\TestFixtures\Traits\SetAuthKeysTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Magento\Catalog\Model\Product\Type;
use Magento\Catalog\Model\Product\Visibility;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Downloadable\Model\Product\Type as DownloadableType;
use Magento\Framework\ObjectManagerInterface;
use Magento\GroupedProduct\Model\Product\Type\Grouped;
use Magento\Store\Model\StoreManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use TddWizard\Fixtures\Catalog\CategoryFixturePool;
use TddWizard\Fixtures\Catalog\ProductFixturePool;
use TddWizard\Fixtures\Core\ConfigFixture;

/**
 * @covers EntityIndexerService
 * @method EntityIndexerServiceInterface instantiateTestObject(?array $arguments = null)
 * @method EntityIndexerServiceInterface instantiateTestObjectFromInterface(?array $arguments = null)
 */
class EntityIndexerServiceDeleteTest extends TestCase
{
    use AttributeTrait;
    use CategoryTrait;
    use IndexingEntitiesTrait;
    use ObjectInstantiationTrait;
    use PipelineEntityApiCallTrait;
    use ProductTrait;
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

        $this->implementationFqcn = EntityIndexerServiceVirtualType::class; // @phpstan-ignore-line
        $this->interfaceFqcn = EntityIndexerServiceInterface::class;
        $this->implementationForVirtualType = EntityIndexerService::class;
        $this->objectManager = Bootstrap::getObjectManager();

        $this->storeFixturesPool = $this->objectManager->get(StoreFixturesPool::class);
        $this->attributeFixturePool = $this->objectManager->get(AttributeFixturePool::class);
        $this->categoryFixturePool = $this->objectManager->get(CategoryFixturePool::class);
        $this->productFixturePool = $this->objectManager->get(ProductFixturePool::class);
    }

    /**
     * @return void
     * @throws \Exception
     */
    protected function tearDown(): void
    {
        parent::tearDown();

        $this->productFixturePool->rollback();
        $this->categoryFixturePool->rollback();
        $this->attributeFixturePool->rollback();
        $this->storeFixturesPool->rollback();
    }

    /**
     * @magentoAppIsolation enabled
     * @magentoDbIsolation disabled
     */
    public function testExecute_ThrowsException_ForInvalidJsApiKey(): void
    {
        $apiKey = 'invalid-js-api-key';
        $this->cleanIndexingEntities(apiKey: $apiKey);

        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');
        $scopeProvider = $this->objectManager->get(ScopeProviderInterface::class);
        $scopeProvider->setCurrentScope($storeFixture->get());
        $this->setAuthKeys(
            scopeProvider: $scopeProvider,
            jsApiKey: $apiKey,
            restAuthKey: 'KlevuRestAuthKey123',
        );

        $this->createProduct();
        $productFixture = $this->productFixturePool->get('test_product');

        $this->cleanIndexingEntities(apiKey: $apiKey);
        $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ID => $productFixture->getId(),
            IndexingEntity::NEXT_ACTION => Actions::DELETE,
        ]);

        $this->expectException(InvalidPipelineArgumentsException::class);
        $this->expectExceptionMessage(
            'Invalid arguments for pipeline "Klevu\PhpSDKPipelines\Pipeline\Stage\Indexing\SendBatchDeleteRequest". '
            . 'JS API Key argument (jsApiKey): Data is not valid',
        );

        $this->mockBatchServicePutApiCall(isCalled: false);
        $this->mockBatchServiceDeleteApiCall(isCalled: false);

        $service = $this->instantiateTestObject();
        $service->execute(apiKey: $apiKey);
    }

    /**
     * @magentoAppIsolation enabled
     * @magentoDbIsolation disabled
     */
    public function testExecute_ThrowsException_ForInvalidRestAuthKey(): void
    {
        $apiKey = 'klevu-123456789';
        $this->cleanIndexingEntities(apiKey: $apiKey);

        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');
        $scopeProvider = $this->objectManager->get(ScopeProviderInterface::class);
        $scopeProvider->setCurrentScope($storeFixture->get());
        $this->setAuthKeys(
            scopeProvider: $scopeProvider,
            jsApiKey: $apiKey,
            restAuthKey: 'invalid-auth-key',
        );

        $this->createProduct();
        $productFixture = $this->productFixturePool->get('test_product');

        $this->cleanIndexingEntities(apiKey: $apiKey);
        $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ID => $productFixture->getId(),
            IndexingEntity::NEXT_ACTION => Actions::DELETE,
        ]);

        $this->expectException(InvalidPipelineArgumentsException::class);
        $this->expectExceptionMessage(
            'Invalid arguments for pipeline "Klevu\PhpSDKPipelines\Pipeline\Stage\Indexing\SendBatchDeleteRequest". '
            . 'REST AUTH Key argument (restAuthKey): Data is not valid',
        );

        $this->mockBatchServicePutApiCall(isCalled: false);
        $this->mockBatchServiceDeleteApiCall(isCalled: false);

        $service = $this->instantiateTestObject();
        $service->execute(apiKey: $apiKey);
    }

    public function testExecute_ReturnsNoop_WhenNoProductsToDelete(): void
    {
        $apiKey = 'klevu-js-key';
        $this->cleanIndexingEntities(apiKey: $apiKey);

        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');
        $scopeProvider = $this->objectManager->get(ScopeProviderInterface::class);
        $scopeProvider->setCurrentScope($storeFixture->get());
        $this->setAuthKeys(
            scopeProvider: $scopeProvider,
            jsApiKey: $apiKey,
            restAuthKey: 'klevu-rest-key',
        );

        $this->mockBatchServicePutApiCall(isCalled: false);
        $this->mockBatchServiceDeleteApiCall(isCalled: false);

        $service = $this->instantiateTestObject();
        $result = $service->execute(apiKey: $apiKey);

        $this->assertSame(
            expected: IndexerResultStatuses::NOOP,
            actual: $result->getStatus(),
            message: 'Status: ' . $result->getStatus()->name,
        );
    }

    /**
     * @magentoDbIsolation disabled
     */
    public function testExecute_ReturnsNoop_WhenProductSyncDisabled(): void
    {
        $apiKey = 'klevu-js-key';
        $this->cleanIndexingEntities(apiKey: $apiKey);

        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');
        $scopeProvider = $this->objectManager->get(ScopeProviderInterface::class);
        $scopeProvider->setCurrentScope($storeFixture->get());
        $this->setAuthKeys(
            scopeProvider: $scopeProvider,
            jsApiKey: $apiKey,
            restAuthKey: 'klevu-rest-key',
        );

        ConfigFixture::setForStore(
            path: Constants::XML_PATH_PRODUCT_SYNC_ENABLED,
            value: 0,
            storeCode: $storeFixture->getCode(),
        );

        $this->createProduct([
            'key' => 'test_product_1',
        ]);
        $productFixture1 = $this->productFixturePool->get('test_product_1');

        $this->cleanIndexingEntities(apiKey: $apiKey);
        $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ID => $productFixture1->getId(),
            IndexingEntity::NEXT_ACTION => Actions::DELETE,
        ]);

        $this->mockBatchServicePutApiCall(isCalled: false);
        $this->mockBatchServiceDeleteApiCall(isCalled: false);

        $service = $this->instantiateTestObject();
        $result = $service->execute(apiKey: $apiKey);

        $this->assertSame(
            expected: IndexerResultStatuses::NOOP,
            actual: $result->getStatus(),
            message: 'Status: ' . $result->getStatus()->name,
        );
    }

    /**
     * @magentoAppIsolation enabled
     * @magentoDbIsolation disabled
     */
    public function testExecute_ReturnsPartial_WhenSomeApiCallsFail(): void
    {
        $apiKey = 'klevu-123456789';

        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');
        $scopeProvider = $this->objectManager->get(ScopeProviderInterface::class);
        $scopeProvider->setCurrentScope($storeFixture->get());
        $this->setAuthKeys(
            scopeProvider: $scopeProvider,
            jsApiKey: $apiKey,
            restAuthKey: 'SomeValidRestKey123',
        );

        $this->createProduct([
            'key' => 'test_product_1',
        ]);
        $productFixture1 = $this->productFixturePool->get('test_product_1');

        $this->cleanIndexingEntities(apiKey: $apiKey);
        $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ID => $productFixture1->getId(),
            IndexingEntity::NEXT_ACTION => Actions::DELETE,
        ]);

        $this->mockBatchServicePutApiCall(isCalled: false);
        $this->mockBatchServiceDeleteApiCall(isCalled: true, isSuccessful: false);

        $service = $this->instantiateTestObject();
        $result = $service->execute(apiKey: $apiKey);

        $this->assertSame(
            expected: IndexerResultStatuses::PARTIAL,
            actual: $result->getStatus(),
            message: 'Status: ' . $result->getStatus()->name,
        );

        $pipelineResultsArray = $result->getPipelineResult();
        $this->assertCount(expectedCount: 1, haystack: $pipelineResultsArray);
        $pipelineResults = array_shift($pipelineResultsArray);
        $this->assertCount(expectedCount: 1, haystack: $pipelineResults);
        /** @var ApiPipelineResult $pipelineResult */
        $pipelineResult = array_shift($pipelineResults);

        $this->assertFalse(condition: $pipelineResult->success);
        $this->assertCount(expectedCount: 1, haystack: $pipelineResult->messages);
        $this->assertContains(needle: 'There has been an ERROR', haystack: $pipelineResult->messages);

        $this->cleanIndexingEntities(apiKey: $apiKey);
    }

    /**
     * @magentoAppIsolation enabled
     * @magentoDbIsolation disabled
     */
    public function testExecute_ReturnsSuccess_WhenSimpleProductDeleted(): void
    {
        $apiKey = 'klevu-123456789';

        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');
        $scopeProvider = $this->objectManager->get(ScopeProviderInterface::class);
        $scopeProvider->setCurrentScope($storeFixture->get());
        $this->setAuthKeys(
            scopeProvider: $scopeProvider,
            jsApiKey: $apiKey,
            restAuthKey: 'weign934jt93jg934j',
        );

        $this->createProduct();
        $productFixture = $this->productFixturePool->get('test_product');

        $this->cleanIndexingEntities(apiKey: $apiKey);
        $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ID => $productFixture->getId(),
            IndexingEntity::NEXT_ACTION => Actions::DELETE,
        ]);

        $this->mockBatchServicePutApiCall(isCalled: false);
        $this->mockBatchServiceDeleteApiCall(isCalled: true, isSuccessful: true);

        $service = $this->instantiateTestObject();
        $result = $service->execute(apiKey: $apiKey);

        $this->assertSame(
            expected: IndexerResultStatuses::SUCCESS,
            actual: $result->getStatus(),
            message: 'Status: ' . $result->getStatus()->name,
        );
        $pipelineResultsArray = $result->getPipelineResult();
        $this->assertCount(expectedCount: 1, haystack: $pipelineResultsArray);
        $pipelineResults = array_shift($pipelineResultsArray);
        $this->assertCount(expectedCount: 1, haystack: $pipelineResults);
        /** @var ApiPipelineResult $pipelineResult */
        $pipelineResult = array_shift($pipelineResults);

        $this->assertTrue(condition: $pipelineResult->success);
        $this->assertCount(expectedCount: 1, haystack: $pipelineResult->messages);
        $this->assertContains(needle: 'Batch accepted successfully', haystack: $pipelineResult->messages);

        /** @var RecordIterator $payload */
        $payload = $pipelineResult->payload;
        $this->assertCount(expectedCount: 1, haystack: $payload);

        $this->assertContains(
            needle: (string)$productFixture->getId(),
            haystack: $payload,
        );

        $this->cleanIndexingEntities(apiKey: $apiKey);
    }

    /**
     * @magentoAppIsolation enabled
     * @magentoDbIsolation disabled
     */
    public function testExecute_ReturnsSuccess_WhenVirtualProductDeleted(): void
    {
        $apiKey = 'klevu-123456789';

        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');
        $scopeProvider = $this->objectManager->get(ScopeProviderInterface::class);
        $scopeProvider->setCurrentScope($storeFixture->get());
        $this->setAuthKeys(
            scopeProvider: $scopeProvider,
            jsApiKey: $apiKey,
            restAuthKey: 'weign934jt93jg934j',
        );

        $this->createProduct([
            'type_id' => Type::TYPE_VIRTUAL,
        ]);
        $productFixture = $this->productFixturePool->get('test_product');

        $this->cleanIndexingEntities(apiKey: $apiKey);
        $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ID => $productFixture->getId(),
            IndexingEntity::NEXT_ACTION => Actions::DELETE,
        ]);

        $this->mockBatchServicePutApiCall(isCalled: false);
        $this->mockBatchServiceDeleteApiCall(isCalled: true, isSuccessful: true);

        $service = $this->instantiateTestObject();
        $result = $service->execute(apiKey: $apiKey);

        $this->assertSame(
            expected: IndexerResultStatuses::SUCCESS,
            actual: $result->getStatus(),
            message: 'Status: ' . $result->getStatus()->name,
        );
        $pipelineResultsArray = $result->getPipelineResult();
        $this->assertCount(expectedCount: 1, haystack: $pipelineResultsArray);
        $pipelineResults = array_shift($pipelineResultsArray);
        $this->assertCount(expectedCount: 1, haystack: $pipelineResults);
        /** @var ApiPipelineResult $pipelineResult */
        $pipelineResult = array_shift($pipelineResults);

        $this->assertTrue(condition: $pipelineResult->success);
        $this->assertCount(expectedCount: 1, haystack: $pipelineResult->messages);
        $this->assertContains(needle: 'Batch accepted successfully', haystack: $pipelineResult->messages);

        /** @var RecordIterator $payload */
        $payload = $pipelineResult->payload;
        $this->assertCount(expectedCount: 1, haystack: $payload);

        $this->assertContains(
            needle: (string)$productFixture->getId(),
            haystack: $payload,
        );
        $this->cleanIndexingEntities(apiKey: $apiKey);
    }

    /**
     * @magentoAppIsolation enabled
     * @magentoDbIsolation disabled
     */
    public function testExecute_ReturnsSuccess_WhenDownloadableProductDeleted(): void
    {
        $apiKey = 'klevu-123456789';

        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');
        $scopeProvider = $this->objectManager->get(ScopeProviderInterface::class);
        $scopeProvider->setCurrentScope($storeFixture->get());
        $this->setAuthKeys(
            scopeProvider: $scopeProvider,
            jsApiKey: $apiKey,
            restAuthKey: 'weign934jt93jg934j',
        );

        $this->createProduct([
            'type_id' => DownloadableType::TYPE_DOWNLOADABLE,
        ]);
        $productFixture = $this->productFixturePool->get('test_product');

        $this->cleanIndexingEntities(apiKey: $apiKey);
        $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ID => $productFixture->getId(),
            IndexingEntity::NEXT_ACTION => Actions::DELETE,
        ]);

        $this->mockBatchServicePutApiCall(isCalled: false);
        $this->mockBatchServiceDeleteApiCall(isCalled: true, isSuccessful: true);

        $service = $this->instantiateTestObject();
        $result = $service->execute(apiKey: $apiKey);

        $this->assertSame(
            expected: IndexerResultStatuses::SUCCESS,
            actual: $result->getStatus(),
            message: 'Status: ' . $result->getStatus()->name,
        );
        $pipelineResultsArray = $result->getPipelineResult();
        $this->assertCount(expectedCount: 1, haystack: $pipelineResultsArray);
        $pipelineResults = array_shift($pipelineResultsArray);
        $this->assertCount(expectedCount: 1, haystack: $pipelineResults);
        /** @var ApiPipelineResult $pipelineResult */
        $pipelineResult = array_shift($pipelineResults);

        $this->assertTrue(condition: $pipelineResult->success);
        $this->assertCount(expectedCount: 1, haystack: $pipelineResult->messages);
        $this->assertContains(needle: 'Batch accepted successfully', haystack: $pipelineResult->messages);

        /** @var RecordIterator $payload */
        $payload = $pipelineResult->payload;
        $this->assertCount(expectedCount: 1, haystack: $payload);

        $this->assertContains(
            needle: (string)$productFixture->getId(),
            haystack: $payload,
        );

        $this->cleanIndexingEntities(apiKey: $apiKey);
    }

    /**
     * @magentoAppIsolation enabled
     * @magentoDbIsolation disabled
     */
    public function testExecute_ReturnsSuccess_WhenConfigurableProductDeleted(): void
    {
        $apiKey = 'klevu-123456789';

        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');
        $scopeProvider = $this->objectManager->get(ScopeProviderInterface::class);
        $scopeProvider->setCurrentScope($storeFixture->get());
        $this->setAuthKeys(
            scopeProvider: $scopeProvider,
            jsApiKey: $apiKey,
            restAuthKey: 'weign934jt93jg934j',
        );

        $this->createCategory([
            'key' => 'top_cat',
        ]);
        $topCategoryFixture = $this->categoryFixturePool->get('top_cat');
        $this->createCategory([
            'key' => 'test_category',
            'parent' => $topCategoryFixture,
        ]);
        $categoryFixture = $this->categoryFixturePool->get('test_category');

        $this->createAttribute([
            'attribute_type' => 'configurable',
        ]);
        $attributeFixture = $this->attributeFixturePool->get('test_attribute');

        $this->createProduct([
            'key' => 'test_product_variant_1',
            'name' => 'Klevu Simple Product Test',
            'sku' => 'KLEVU-SIMPLE-SKU-001',
            'price' => 49.99,
            'category_ids' => [
                $topCategoryFixture->getId(),
                $categoryFixture->getId(),
            ],
            'data' => [
                'short_description' => 'This is a short description variant 1',
                'description' => 'This is a longer description than the short description variant 1',
                $attributeFixture->getAttributeCode() => '1',
            ],
        ]);
        $variantProductFixture1 = $this->productFixturePool->get('test_product_variant_1');
        $this->createProduct([
            'key' => 'test_product_variant_2',
            'name' => 'Klevu Simple Product Test',
            'sku' => 'KLEVU-SIMPLE-SKU-002',
            'price' => 39.99,
            'category_ids' => [
                $topCategoryFixture->getId(),
                $categoryFixture->getId(),
            ],
            'data' => [
                'short_description' => 'This is a short description variant 2',
                'description' => 'This is a longer description than the short description variant 2',
                $attributeFixture->getAttributeCode() => '2',
            ],
        ]);
        $variantProductFixture2 = $this->productFixturePool->get('test_product_variant_2');

        $this->createProduct([
            'type_id' => Configurable::TYPE_CODE,
            'name' => 'Klevu Configurable Product Test',
            'sku' => 'KLEVU-CONFIGURABLE-SKU-001',
            'price' => 99.99,
            'visibility' => Visibility::VISIBILITY_IN_SEARCH,
            'category_ids' => [
                $categoryFixture->getId(),
            ],
            'configurable_attributes' => [
                $attributeFixture->getAttribute(),
            ],
            'variants' => [
                $variantProductFixture1->getProduct(),
                $variantProductFixture2->getProduct(),
            ],
            'data' => [
                'short_description' => 'This is a Configurable product short description',
                'description' => 'This is a Configurable product longer description than the short description',
                'special_price' => 54.99,
                'special_price_from' => '1970-01-01',
                'special_price_to' => '2099-12-31',
            ],
        ]);
        $productFixture = $this->productFixturePool->get('test_product');

        $this->cleanIndexingEntities(apiKey: $apiKey);
        $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ID => $productFixture->getId(),
            IndexingEntity::NEXT_ACTION => Actions::DELETE,
        ]);

        $this->mockBatchServicePutApiCall(isCalled: false);
        $this->mockBatchServiceDeleteApiCall(isCalled: true, isSuccessful: true);

        $service = $this->instantiateTestObject();
        $result = $service->execute(apiKey: $apiKey);

        $this->assertSame(
            expected: IndexerResultStatuses::SUCCESS,
            actual: $result->getStatus(),
            message: 'Status: ' . $result->getStatus()->name,
        );
        $pipelineResultsArray = $result->getPipelineResult();
        $this->assertCount(expectedCount: 1, haystack: $pipelineResultsArray);
        $pipelineResults = array_shift($pipelineResultsArray);
        $this->assertCount(expectedCount: 1, haystack: $pipelineResults);
        /** @var ApiPipelineResult $pipelineResult */
        $pipelineResult = array_shift($pipelineResults);

        $this->assertTrue(condition: $pipelineResult->success);
        $this->assertCount(expectedCount: 1, haystack: $pipelineResult->messages);
        $this->assertContains(needle: 'Batch accepted successfully', haystack: $pipelineResult->messages);

        /** @var RecordIterator $payload */
        $payload = $pipelineResult->payload;
        $this->assertCount(expectedCount: 1, haystack: $payload);

        $this->assertContains(
            needle: (string)$productFixture->getId(),
            haystack: $payload,
        );

        $this->cleanIndexingEntities(apiKey: $apiKey);
    }

    /**
     * @magentoAppIsolation enabled
     * @magentoDbIsolation disabled
     */
    public function testExecute_ReturnsSuccess_WhenVariantProductDeleted(): void
    {
        $apiKey = 'klevu-123456789';

        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');
        $scopeProvider = $this->objectManager->get(ScopeProviderInterface::class);
        $scopeProvider->setCurrentScope($storeFixture->get());
        $this->setAuthKeys(
            scopeProvider: $scopeProvider,
            jsApiKey: $apiKey,
            restAuthKey: 'weign934jt93jg934j',
        );

        $this->createCategory([
            'key' => 'top_cat',
        ]);
        $topCategoryFixture = $this->categoryFixturePool->get('top_cat');
        $this->createCategory([
            'key' => 'test_category',
            'parent' => $topCategoryFixture,
        ]);
        $categoryFixture = $this->categoryFixturePool->get('test_category');

        $this->createAttribute([
            'attribute_type' => 'configurable',
        ]);
        $attributeFixture = $this->attributeFixturePool->get('test_attribute');

        $this->createProduct([
            'key' => 'test_product_variant_1',
            'name' => 'Klevu Simple Product Test',
            'sku' => 'KLEVU-SIMPLE-SKU-001',
            'price' => 99.99,
            'in_stock' => false,
            'qty' => -3,
            'category_ids' => [
                $topCategoryFixture->getId(),
                $categoryFixture->getId(),
            ],
            'data' => [
                'short_description' => 'This is a short description variant 1',
                'description' => 'This is a longer description than the short description variant 1',
                $attributeFixture->getAttributeCode() => '1',
                'special_price' => 49.99,
                'special_price_from' => '1970-01-01',
                'special_price_to' => '2099-12-31',
            ],
        ]);
        $variantProductFixture1 = $this->productFixturePool->get('test_product_variant_1');
        $this->createProduct([
            'key' => 'test_product_variant_2',
            'name' => 'Klevu Simple Product Test',
            'sku' => 'KLEVU-SIMPLE-SKU-002',
            'price' => 99.99,
            'in_stock' => true,
            'qty' => 100,
            'category_ids' => [
                $topCategoryFixture->getId(),
                $categoryFixture->getId(),
            ],
            'data' => [
                'short_description' => 'This is a short description variant 2',
                'description' => 'This is a longer description than the short description variant 2',
                $attributeFixture->getAttributeCode() => '2',
                'special_price' => 39.99,
                'special_price_from' => '1970-01-01',
                'special_price_to' => '2099-12-31',
            ],
        ]);
        $variantProductFixture2 = $this->productFixturePool->get('test_product_variant_2');

        $this->createProduct([
            'type_id' => Configurable::TYPE_CODE,
            'name' => 'Klevu Configurable Product Test',
            'sku' => 'KLEVU-CONFIGURABLE-SKU-001',
            'price' => 99.99,
            'in_stock' => true,
            'visibility' => Visibility::VISIBILITY_IN_SEARCH,
            'category_ids' => [
                $categoryFixture->getId(),
            ],
            'configurable_attributes' => [
                $attributeFixture->getAttribute(),
            ],
            'variants' => [
                $variantProductFixture1->getProduct(),
                $variantProductFixture2->getProduct(),
            ],
            'data' => [
                'short_description' => 'This is a Configurable product short description',
                'description' => 'This is a Configurable product longer description than the short description',
                'special_price' => 54.99,
                'special_price_from' => '1970-01-01',
                'special_price_to' => '2099-12-31',
            ],
        ]);
        $productFixture = $this->productFixturePool->get('test_product');

        $this->cleanIndexingEntities(apiKey: $apiKey);
        $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ID => $variantProductFixture1->getId(),
            IndexingEntity::TARGET_PARENT_ID => $productFixture->getId(),
            IndexingEntity::NEXT_ACTION => Actions::DELETE,
        ]);

        $this->mockBatchServicePutApiCall(isCalled: false);
        $this->mockBatchServiceDeleteApiCall(isCalled: true, isSuccessful: true);

        $service = $this->instantiateTestObject();
        $result = $service->execute(apiKey: $apiKey);

        $this->assertSame(
            expected: IndexerResultStatuses::SUCCESS,
            actual: $result->getStatus(),
            message: 'Status: ' . $result->getStatus()->name,
        );
        $pipelineResultsArray = $result->getPipelineResult();
        $this->assertCount(expectedCount: 1, haystack: $pipelineResultsArray);
        $pipelineResults = array_shift($pipelineResultsArray);
        $this->assertCount(expectedCount: 1, haystack: $pipelineResults);
        /** @var ApiPipelineResult $pipelineResult */
        $pipelineResult = array_shift($pipelineResults);

        $this->assertTrue(condition: $pipelineResult->success);
        $this->assertCount(expectedCount: 1, haystack: $pipelineResult->messages);
        $this->assertContains(needle: 'Batch accepted successfully', haystack: $pipelineResult->messages);

        /** @var RecordIterator $payload */
        $payload = $pipelineResult->payload;
        $this->assertCount(expectedCount: 1, haystack: $payload);

        $this->assertContains(
            needle: $productFixture->getId() . '-' . $variantProductFixture1->getId(),
            haystack: $payload,
        );

        $this->cleanIndexingEntities(apiKey: $apiKey);
    }

    /**
     * @magentoAppIsolation enabled
     * @magentoDbIsolation disabled
     */
    public function testExecute_ReturnsSuccess_WhenGroupedProductDeleted(): void
    {
        $apiKey = 'klevu-123456789';

        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');
        $scopeProvider = $this->objectManager->get(ScopeProviderInterface::class);
        $scopeProvider->setCurrentScope($storeFixture->get());
        $this->setAuthKeys(
            scopeProvider: $scopeProvider,
            jsApiKey: $apiKey,
            restAuthKey: 'weign934jt93jg934j',
        );

        $this->createCategory([
            'key' => 'top_cat',
        ]);
        $topCategoryFixture = $this->categoryFixturePool->get('top_cat');
        $this->createCategory([
            'key' => 'test_category',
            'parent' => $topCategoryFixture,
        ]);
        $categoryFixture = $this->categoryFixturePool->get('test_category');

        $this->createProduct([
            'key' => 'test_product_simple_1',
            'name' => 'Klevu Simple Product Test',
            'sku' => 'KLEVU-SIMPLE-SKU-001',
            'price' => 69.99,
            'category_ids' => [
                $topCategoryFixture->getId(),
                $categoryFixture->getId(),
            ],
            'data' => [
                'short_description' => 'This is a short description variant 1',
                'description' => 'This is a longer description than the short description variant 1',
                'special_price' => 44.99,
                'special_price_from' => '1970-01-01',
                'special_price_to' => '2099-12-31',
            ],
        ]);
        $simpleProductFixture1 = $this->productFixturePool->get('test_product_simple_1');
        $this->createProduct([
            'key' => 'test_product_simple_2',
            'name' => 'Klevu Simple Product Test',
            'sku' => 'KLEVU-SIMPLE-SKU-002',
            'price' => 79.99,
            'category_ids' => [
                $topCategoryFixture->getId(),
                $categoryFixture->getId(),
            ],
            'data' => [
                'short_description' => 'This is a short description variant 2',
                'description' => 'This is a longer description than the short description variant 2',
                'special_price' => 49.99,
                'special_price_from' => '1970-01-01',
                'special_price_to' => '2099-12-31',
            ],
        ]);
        $simpleProductFixture2 = $this->productFixturePool->get('test_product_simple_2');

        $this->createProduct([
            'type_id' => Grouped::TYPE_CODE,
            'name' => 'Klevu Grouped Product Test',
            'sku' => 'KLEVU-GROUPED-SKU-001',
            'price' => 99.99,
            'visibility' => Visibility::VISIBILITY_BOTH,
            'category_ids' => [
                $topCategoryFixture->getId(),
                $categoryFixture->getId(),
            ],
            'linked_products' => [
                $simpleProductFixture1,
                $simpleProductFixture2,
            ],
            'data' => [
                'short_description' => 'This is a Grouped product short description',
                'description' => 'This is a Grouped product longer description than the short description',
                'special_price' => 54.99,
                'special_price_from' => '1970-01-01',
                'special_price_to' => '2099-12-31',
            ],
        ]);
        $productFixture = $this->productFixturePool->get('test_product');

        $this->cleanIndexingEntities(apiKey: $apiKey);
        $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ID => $productFixture->getId(),
            IndexingEntity::NEXT_ACTION => Actions::DELETE,
        ]);

        $this->mockBatchServicePutApiCall(isCalled: false);
        $this->mockBatchServiceDeleteApiCall(isCalled: true, isSuccessful: true);

        $service = $this->instantiateTestObject();
        $result = $service->execute(apiKey: $apiKey);

        $this->assertSame(
            expected: IndexerResultStatuses::SUCCESS,
            actual: $result->getStatus(),
            message: 'Status: ' . $result->getStatus()->name,
        );
        $pipelineResultsArray = $result->getPipelineResult();
        $this->assertCount(expectedCount: 1, haystack: $pipelineResultsArray);
        $pipelineResults = array_shift($pipelineResultsArray);
        $this->assertCount(expectedCount: 1, haystack: $pipelineResults);
        /** @var ApiPipelineResult $pipelineResult */
        $pipelineResult = array_shift($pipelineResults);

        $this->assertTrue(condition: $pipelineResult->success);
        $this->assertCount(expectedCount: 1, haystack: $pipelineResult->messages);
        $this->assertContains(needle: 'Batch accepted successfully', haystack: $pipelineResult->messages);

        /** @var RecordIterator $payload */
        $payload = $pipelineResult->payload;
        $this->assertCount(expectedCount: 1, haystack: $payload);

        $this->assertContains(
            needle: (string)$productFixture->getId(),
            haystack: $payload,
        );

        $this->cleanIndexingEntities(apiKey: $apiKey);
    }

    /**
     * @magentoAppIsolation enabled
     * @magentoDbIsolation disabled
     */
    public function testExecute_ForRealApiKeys(): void
    {
        /**
         * This test requires your Klevu API keys
         * These API keys can be set in dev/tests/integration/phpunit.xml
         * <phpunit>
         *     <testsuites>
         *      ...
         *     </testsuites>
         *     <php>
         *         ...
         *         <env name="KLEVU_JS_API_KEY" value="" force="true" />
         *         <env name="KLEVU_REST_API_KEY" value="" force="true" />
         *         <env name="KLEVU_API_REST_URL" value="api.ksearchnet.com" force="true" />
         *         // KLEVU_TIERS_URL only required for none production env
         *         <env name="KLEVU_TIERS_URL" value="tiers.klevu.com" force="true" />
         *     </php>
         */
        $restApiKey = getenv('KLEVU_REST_API_KEY');
        $jsApiKey = getenv('KLEVU_JS_API_KEY');
        $restApiUrl = getenv('KLEVU_REST_API_URL');
        $tiersApiUrl = getenv('KLEVU_TIERS_URL');
        $indexingUrl = getenv('KLEVU_INDEXING_URL');
        if (!$restApiKey || !$jsApiKey || !$restApiUrl || !$tiersApiUrl || !$indexingUrl) {
            $this->markTestSkipped('Klevu API keys are not set in `dev/tests/integration/phpunit.xml`. Test Skipped');
        }

        $storeManager = $this->objectManager->get(StoreManagerInterface::class);
        $store = $storeManager->getDefaultStoreView();
        $scopeProvider = $this->objectManager->get(ScopeProviderInterface::class);
        $scopeProvider->setCurrentScope(scope: $store);
        $this->setAuthKeys(
            scopeProvider: $scopeProvider,
            jsApiKey: $jsApiKey,
            restAuthKey: $restApiKey,
        );

        ConfigFixture::setGlobal(
            path: BaseUrlsProvider::CONFIG_XML_PATH_URL_INDEXING,
            value: $indexingUrl,
        );
        ConfigFixture::setGlobal(
            path: BaseUrlsProvider::CONFIG_XML_PATH_URL_API,
            value: $restApiUrl,
        );
        ConfigFixture::setGlobal(
            path: BaseUrlsProvider::CONFIG_XML_PATH_URL_TIERS,
            value: $tiersApiUrl,
        );

        $this->createProduct();
        $productFixture = $this->productFixturePool->get('test_product');

        $this->cleanIndexingEntities(apiKey: $jsApiKey);
        $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::API_KEY => $jsApiKey,
            IndexingEntity::TARGET_ID => $productFixture->getId(),
            IndexingEntity::NEXT_ACTION => Actions::DELETE,
        ]);

        $service = $this->instantiateTestObject();
        $result = $service->execute(apiKey: $jsApiKey);

        $this->assertSame(
            expected: IndexerResultStatuses::SUCCESS,
            actual: $result->getStatus(),
            message: 'Status: ' . $result->getStatus()->name,
        );
        $pipelineResultsArray = $result->getPipelineResult();
        $this->assertCount(expectedCount: 1, haystack: $pipelineResultsArray);
        $pipelineResults = array_shift($pipelineResultsArray);
        $this->assertCount(expectedCount: 1, haystack: $pipelineResults);
        /** @var ApiPipelineResult $pipelineResult */
        $pipelineResult = array_shift($pipelineResults);

        $this->assertTrue(condition: $pipelineResult->success);
        $this->assertCount(expectedCount: 1, haystack: $pipelineResult->messages);
        $this->assertContains(needle: 'Batch accepted successfully', haystack: $pipelineResult->messages);

        /** @var RecordIterator $payload */
        $payload = $pipelineResult->payload;
        $this->assertCount(expectedCount: 1, haystack: $payload);

        $this->assertContains(
            needle: (string)$productFixture->getId(),
            haystack: $payload,
        );

        $this->cleanIndexingEntities(apiKey: $jsApiKey);
    }
}
