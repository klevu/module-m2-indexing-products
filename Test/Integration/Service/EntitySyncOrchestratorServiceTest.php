<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Test\Integration\Service;

use Klevu\Configuration\Service\Provider\ScopeProviderInterface;
use Klevu\Indexing\Exception\InvalidEntityIndexerServiceException;
use Klevu\Indexing\Model\IndexingEntity;
use Klevu\Indexing\Service\EntitySyncOrchestratorService;
use Klevu\Indexing\Test\Integration\Traits\IndexingEntitiesTrait;
use Klevu\IndexingApi\Api\Data\IndexerResultInterface;
use Klevu\IndexingApi\Model\Source\Actions;
use Klevu\IndexingApi\Service\EntitySyncOrchestratorServiceInterface;
use Klevu\PhpSDK\Model\Indexing\RecordIterator;
use Klevu\PhpSDKPipelines\Model\ApiPipelineResult;
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
use Klevu\TestFixtures\Traits\TestInterfacePreferenceTrait;
use Magento\Framework\DataObject;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use TddWizard\Fixtures\Catalog\CategoryFixturePool;
use TddWizard\Fixtures\Catalog\ProductFixturePool;

/**
 * @covers EntitySyncOrchestratorService
 * @method EntitySyncOrchestratorServiceInterface instantiateTestObject(?array $arguments = null)
 * @method EntitySyncOrchestratorServiceInterface instantiateTestObjectFromInterface(?array $arguments = null)
 */
class EntitySyncOrchestratorServiceTest extends TestCase
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
    use TestInterfacePreferenceTrait;

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

        $this->implementationFqcn = EntitySyncOrchestratorService::class;
        $this->interfaceFqcn = EntitySyncOrchestratorServiceInterface::class;
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

    public function testConstruct_ThrowsException_ForInvalidAttributeIndexerService(): void
    {
        $this->expectException(InvalidEntityIndexerServiceException::class);

        $this->instantiateTestObject([
            'entityIndexerServices' => [
                'KLEVU_PRODUCT' => [
                    'add' => new DataObject(),
                ],
            ],
        ]);
    }

    /**
     * @magentoAppIsolation enabled
     * @magentoDbIsolation disabled
     */
    public function testExecute_LogsError_ForInvalidAccountCredentials(): void
    {
        $apiKey = 'invalid-js-api-key';
        $authKey = 'invalid-rest-auth-key';

        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');
        $scopeProvider = $this->objectManager->get(ScopeProviderInterface::class);
        $scopeProvider->setCurrentScope(scope: $storeFixture->get());
        $this->setAuthKeys(
            scopeProvider: $scopeProvider,
            jsApiKey: $apiKey,
            restAuthKey: $authKey,
        );

        $this->createProduct();
        $productFixture = $this->productFixturePool->get('test_product');

        $this->cleanIndexingEntities($apiKey);

        $this->createIndexingEntity([
            IndexingEntity::TARGET_ID => $productFixture->getId(),
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::NEXT_ACTION => Actions::ADD,
            IndexingEntity::LAST_ACTION => Actions::NO_ACTION,
            IndexingEntity::IS_INDEXABLE => true,
        ]);

        $mockLogger = $this->getMockBuilder(LoggerInterface::class)
            ->getMock();
        $mockLogger->expects($this->once())
            ->method('warning')
            ->with(
                'Method: {method}, Warning: {message}',
                [
                    'method' => 'Klevu\Indexing\Service\EntitySyncOrchestratorService::getCredentialsArray',
                    'message' => 'No Account found for provided API Key. '
                        . 'Check the JS API Key (incorrect-key) provided.',
                ],
            );

        $service = $this->instantiateTestObject([
            'logger' => $mockLogger,
            'entityIndexerServices' => [],
        ]);
        $service->execute(apiKey: 'incorrect-key');

        $this->cleanIndexingEntities(apiKey: $apiKey);
    }

    /**
     * @magentoAppIsolation enabled
     * @magentoDbIsolation disabled
     */
    public function testExecute_SyncsNewEntity(): void
    {
        $apiKey = 'klevu-123456789';
        $authKey = 'SomeValidRestKey123';

        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');
        $scopeProvider = $this->objectManager->get(ScopeProviderInterface::class);
        $scopeProvider->setCurrentScope(scope: $storeFixture->get());
        $this->setAuthKeys(
            scopeProvider: $scopeProvider,
            jsApiKey: $apiKey,
            restAuthKey: $authKey,
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
            'name' => 'Klevu Simple Product Test',
            'sku' => 'KLEVU-SIMPLE-SKU-001',
            'price' => 9.99,
            'category_ids' => [
                $topCategoryFixture->getId(),
                $categoryFixture->getId(),
            ],
            'data' => [
                'short_description' => 'This is a short description',
                'description' => 'This is a longer description than the short description',
            ],
        ]);
        $productFixture = $this->productFixturePool->get('test_product');

        $this->cleanIndexingEntities(apiKey: $apiKey);
        $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ID => $productFixture->getId(),
            IndexingEntity::NEXT_ACTION => Actions::ADD,
        ]);

        $this->mockBatchServicePutApiCall(isCalled: true, isSuccessful: true);
        $this->mockBatchServiceDeleteApiCall(isCalled: false);

        $service = $this->instantiateTestObject();
        $result = $service->execute(
            entityType: 'KLEVU_PRODUCT',
            apiKey: $apiKey,
            via: 'CLI::klevu:indexing:entity-sync',
        );

        $this->assertCount(expectedCount: 1, haystack: $result);
        $this->assertArrayHasKey(key: $apiKey, array: $result);

        /** @var IndexerResultInterface $integration1 */
        $integration1 = $result[$apiKey];
        $pipelineResults = $integration1->getPipelineResult();
        $this->assertCount(expectedCount: 3, haystack: $pipelineResults);

        $this->assertArrayHasKey(key: 'KLEVU_PRODUCT::delete', array: $pipelineResults);
        $deleteResponses = $pipelineResults['KLEVU_PRODUCT::delete'];
        $this->assertIsArray(actual: $deleteResponses, message: 'Product Delete Response');
        $this->assertCount(expectedCount: 0, haystack: $deleteResponses);

        $this->assertArrayHasKey(key: 'KLEVU_PRODUCT::update', array: $pipelineResults);
        $updateResponses = $pipelineResults['KLEVU_PRODUCT::update'];
        $this->assertIsArray(actual: $updateResponses, message: 'Product Update Response');
        $this->assertCount(expectedCount: 0, haystack: $updateResponses);

        $this->assertArrayHasKey(key: 'KLEVU_PRODUCT::add', array: $pipelineResults);
        $addResponses = $pipelineResults['KLEVU_PRODUCT::add'];
        $this->assertIsArray(actual: $addResponses, message: 'Product Add Response');
        $this->assertCount(expectedCount: 1, haystack: $addResponses);

        /** @var ApiPipelineResult $pipelineResult */
        $pipelineResult = array_shift($addResponses);

        $this->assertTrue(condition: $pipelineResult->success);
        $this->assertCount(expectedCount: 1, haystack: $pipelineResult->messages);
        $this->assertContains(needle: 'Batch accepted successfully', haystack: $pipelineResult->messages);

        /** @var RecordIterator $payload */
        $payload = $pipelineResult->payload;
        $this->assertCount(expectedCount: 1, haystack: $payload);
        $record = $payload->current();

        $this->assertSame(
            expected: (string)$productFixture->getId(),
            actual: $record->getId(),
            message: 'Record ID: ' . $record->getId(),
        );
        $this->assertSame(
            expected: 'KLEVU_PRODUCT',
            actual: $record->getType(),
            message: 'Record Type: ' . $record->getType(),
        );

        $relations = $record->getRelations();
        $this->assertArrayHasKey(key: 'categories', array: $relations);
        $categories = $relations['categories'];
        $this->assertArrayHasKey(key: 'values', array: $categories);
        $this->assertContains(needle: 'categoryid_' . $topCategoryFixture->getId(), haystack: $categories['values']);
        $this->assertContains(needle: 'categoryid_' . $categoryFixture->getId(), haystack: $categories['values']);

        $attributes = $record->getAttributes();
        $this->assertArrayHasKey(key: 'sku', array: $attributes);
        $this->assertSame(
            expected: 'KLEVU-SIMPLE-SKU-001',
            actual: $attributes['sku'],
            message: 'SKU: ' . $attributes['sku'],
        );

        $this->assertArrayHasKey(key: 'name', array: $attributes);
        $this->assertArrayHasKey(key: 'default', array: $attributes['name']);
        $this->assertSame(
            expected: 'Klevu Simple Product Test',
            actual: $attributes['name']['default'],
            message: 'Name: ' . $attributes['name']['default'],
        );

        $this->assertArrayHasKey(key: 'shortDescription', array: $attributes);
        $this->assertArrayHasKey(key: 'default', array: $attributes['shortDescription']);
        $this->assertSame(
            expected: 'This is a short description',
            actual: $attributes['shortDescription']['default'],
            message: 'Short Description: ' . $attributes['shortDescription']['default'],
        );

        $this->assertArrayHasKey(key: 'description', array: $attributes);
        $this->assertArrayHasKey(key: 'default', array: $attributes['description']);
        $this->assertSame(
            expected: 'This is a longer description than the short description',
            actual: $attributes['description']['default'],
            message: 'Description: ' . $attributes['description']['default'],
        );

        $this->assertArrayHasKey(key: 'visibility', array: $attributes);
        $this->assertContains(needle: 'catalog', haystack: $attributes['visibility']);
        $this->assertContains(needle: 'search', haystack: $attributes['visibility']);

        $this->assertArrayHasKey(key: 'inStock', array: $attributes);
        $this->assertTrue(condition: $attributes['inStock'], message: 'In Stock');

        $this->assertArrayHasKey(key: 'url', array: $attributes);
        $this->assertStringContainsString(needle: '/klevu-simple-sku-001', haystack: $attributes['url']);

        $this->assertArrayHasKey(key: 'price', array: $attributes);
        $this->assertArrayHasKey(key: 'USD', array: $attributes['price']);
        $this->assertArrayHasKey(key: 'defaultPrice', array: $attributes['price']['USD']);
        $this->assertSame(expected: 9.99, actual: $attributes['price']['USD']['defaultPrice']);
        $this->assertArrayHasKey(key: 'salePrice', array: $attributes['price']['USD']);
        $this->assertSame(expected: 9.99, actual: $attributes['price']['USD']['salePrice']);

        $updatedIndexingEntity = $this->getIndexingEntityForEntity(
            apiKey: $apiKey,
            entity: $productFixture->getProduct(),
            type: 'KLEVU_PRODUCT',
        );
        $this->assertSame(expected: Actions::ADD, actual: $updatedIndexingEntity->getLastAction());
        $this->assertSame(expected: Actions::NO_ACTION, actual: $updatedIndexingEntity->getNextAction());
        $this->assertTrue(condition: $updatedIndexingEntity->getIsIndexable());
        $this->assertNotNull(actual: $updatedIndexingEntity->getLastActionTimestamp());

        $this->cleanIndexingEntities(apiKey: $apiKey);
    }

    /**
     * @magentoAppIsolation enabled
     * @magentoDbIsolation disabled
     */
    public function testExecute_SyncsEntityUpdate(): void
    {
        $apiKey = 'klevu-123456789';
        $authKey = 'SomeValidRestKey123';

        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');
        $scopeProvider = $this->objectManager->get(ScopeProviderInterface::class);
        $scopeProvider->setCurrentScope(scope: $storeFixture->get());
        $this->setAuthKeys(
            scopeProvider: $scopeProvider,
            jsApiKey: $apiKey,
            restAuthKey: $authKey,
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
            'name' => 'Klevu Simple Product Test',
            'sku' => 'KLEVU-SIMPLE-SKU-002',
            'price' => 99.99,
            'category_ids' => [
                $topCategoryFixture->getId(),
                $categoryFixture->getId(),
            ],
            'data' => [
                'short_description' => 'This is a short description',
                'description' => 'This is a longer description than the short description',
            ],
        ]);
        $productFixture = $this->productFixturePool->get('test_product');

        $this->cleanIndexingEntities(apiKey: $apiKey);
        $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ID => $productFixture->getId(),
            IndexingEntity::NEXT_ACTION => Actions::UPDATE,
        ]);

        $this->mockBatchServicePutApiCall(isCalled: true, isSuccessful: true);
        $this->mockBatchServiceDeleteApiCall(isCalled: false);

        $service = $this->instantiateTestObject();
        $result = $service->execute(
            entityType: 'KLEVU_PRODUCT',
            apiKey: $apiKey,
            via: 'CLI::klevu:indexing:entity-sync',
        );

        $this->assertCount(expectedCount: 1, haystack: $result);
        $this->assertArrayHasKey(key: $apiKey, array: $result);

        /** @var IndexerResultInterface $integration1 */
        $integration1 = $result[$apiKey];
        $pipelineResults = $integration1->getPipelineResult();
        $this->assertCount(expectedCount: 3, haystack: $pipelineResults);

        $this->assertArrayHasKey(key: 'KLEVU_PRODUCT::delete', array: $pipelineResults);
        $deleteResponses = $pipelineResults['KLEVU_PRODUCT::delete'];
        $this->assertCount(expectedCount: 0, haystack: $deleteResponses);

        $this->assertArrayHasKey(key: 'KLEVU_PRODUCT::add', array: $pipelineResults);
        $addResponses = $pipelineResults['KLEVU_PRODUCT::add'];
        $this->assertCount(expectedCount: 0, haystack: $addResponses);

        $this->assertArrayHasKey(key: 'KLEVU_PRODUCT::update', array: $pipelineResults);
        $updateResponses = $pipelineResults['KLEVU_PRODUCT::update'];
        $this->assertCount(expectedCount: 1, haystack: $updateResponses);

        /** @var ApiPipelineResult $pipelineResult */
        $pipelineResult = array_shift($updateResponses);

        $this->assertTrue(condition: $pipelineResult->success);
        $this->assertCount(expectedCount: 1, haystack: $pipelineResult->messages);
        $this->assertContains(needle: 'Batch accepted successfully', haystack: $pipelineResult->messages);

        /** @var RecordIterator $payload */
        $payload = $pipelineResult->payload;
        $this->assertCount(expectedCount: 1, haystack: $payload);
        $record = $payload->current();

        $this->assertSame(
            expected: (string)$productFixture->getId(),
            actual: $record->getId(),
            message: 'Record ID: ' . $record->getId(),
        );
        $this->assertSame(
            expected: 'KLEVU_PRODUCT',
            actual: $record->getType(),
            message: 'Record Type: ' . $record->getType(),
        );

        $relations = $record->getRelations();
        $this->assertArrayHasKey(key: 'categories', array: $relations);
        $categories = $relations['categories'];
        $this->assertArrayHasKey(key: 'values', array: $categories);
        $this->assertContains(needle: 'categoryid_' . $topCategoryFixture->getId(), haystack: $categories['values']);
        $this->assertContains(needle: 'categoryid_' . $categoryFixture->getId(), haystack: $categories['values']);

        $attributes = $record->getAttributes();
        $this->assertArrayHasKey(key: 'sku', array: $attributes);
        $this->assertSame(
            expected: 'KLEVU-SIMPLE-SKU-002',
            actual: $attributes['sku'],
            message: 'SKU: ' . $attributes['sku'],
        );

        $this->assertArrayHasKey(key: 'name', array: $attributes);
        $this->assertArrayHasKey(key: 'default', array: $attributes['name']);
        $this->assertSame(
            expected: 'Klevu Simple Product Test',
            actual: $attributes['name']['default'],
            message: 'Name: ' . $attributes['name']['default'],
        );

        $this->assertArrayHasKey(key: 'shortDescription', array: $attributes);
        $this->assertArrayHasKey(key: 'default', array: $attributes['shortDescription']);
        $this->assertSame(
            expected: 'This is a short description',
            actual: $attributes['shortDescription']['default'],
            message: 'Short Description: ' . $attributes['shortDescription']['default'],
        );

        $this->assertArrayHasKey(key: 'description', array: $attributes);
        $this->assertArrayHasKey(key: 'default', array: $attributes['description']);
        $this->assertSame(
            expected: 'This is a longer description than the short description',
            actual: $attributes['description']['default'],
            message: 'Description: ' . $attributes['description']['default'],
        );

        $this->assertArrayHasKey(key: 'visibility', array: $attributes);
        $this->assertContains(needle: 'catalog', haystack: $attributes['visibility']);
        $this->assertContains(needle: 'search', haystack: $attributes['visibility']);

        $this->assertArrayHasKey(key: 'inStock', array: $attributes);
        $this->assertTrue(condition: $attributes['inStock'], message: 'In Stock');

        $this->assertArrayHasKey(key: 'url', array: $attributes);
        $this->assertStringContainsString(needle: '/klevu-simple-sku-002', haystack: $attributes['url']);

        $this->assertArrayHasKey(key: 'price', array: $attributes);
        $this->assertArrayHasKey(key: 'USD', array: $attributes['price']);
        $this->assertArrayHasKey(key: 'defaultPrice', array: $attributes['price']['USD']);
        $this->assertSame(expected: 99.99, actual: $attributes['price']['USD']['defaultPrice']);
        $this->assertArrayHasKey(key: 'salePrice', array: $attributes['price']['USD']);
        $this->assertSame(expected: 99.99, actual: $attributes['price']['USD']['salePrice']);

        $updatedIndexingEntity = $this->getIndexingEntityForEntity(
            apiKey: $apiKey,
            entity: $productFixture->getProduct(),
            type: 'KLEVU_PRODUCT',
        );
        $this->assertSame(expected: Actions::UPDATE, actual: $updatedIndexingEntity->getLastAction());
        $this->assertSame(expected: Actions::NO_ACTION, actual: $updatedIndexingEntity->getNextAction());
        $this->assertTrue(condition: $updatedIndexingEntity->getIsIndexable());
        $this->assertNotNull(actual: $updatedIndexingEntity->getLastActionTimestamp());

        $this->cleanIndexingEntities(apiKey: $apiKey);
    }

    /**
     * @magentoAppIsolation enabled
     * @magentoDbIsolation disabled
     */
    public function testExecute_DeletesEntity(): void
    {
        $apiKey = 'klevu-123456789';
        $authKey = 'SomeValidRestKey123';

        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');
        $scopeProvider = $this->objectManager->get(ScopeProviderInterface::class);
        $scopeProvider->setCurrentScope(scope: $storeFixture->get());
        $this->setAuthKeys(
            scopeProvider: $scopeProvider,
            jsApiKey: $apiKey,
            restAuthKey: $authKey,
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
        $result = $service->execute(
            entityType: 'KLEVU_PRODUCT',
            apiKey: $apiKey,
            via: 'CLI::klevu:indexing:entity-sync',
        );

        $this->assertCount(expectedCount: 1, haystack: $result);
        $this->assertArrayHasKey(key: $apiKey, array: $result);

        /** @var IndexerResultInterface $integration1 */
        $integration1 = $result[$apiKey];
        $pipelineResults = $integration1->getPipelineResult();
        $this->assertCount(expectedCount: 3, haystack: $pipelineResults);

        $this->assertArrayHasKey(key: 'KLEVU_PRODUCT::add', array: $pipelineResults);
        $addResponses = $pipelineResults['KLEVU_PRODUCT::add'];
        $this->assertCount(expectedCount: 0, haystack: $addResponses);

        $this->assertArrayHasKey(key: 'KLEVU_PRODUCT::update', array: $pipelineResults);
        $addResponses = $pipelineResults['KLEVU_PRODUCT::update'];
        $this->assertCount(expectedCount: 0, haystack: $addResponses);

        $this->assertArrayHasKey(key: 'KLEVU_PRODUCT::delete', array: $pipelineResults);
        $updateResponses = $pipelineResults['KLEVU_PRODUCT::delete'];
        $this->assertCount(expectedCount: 1, haystack: $updateResponses);

        /** @var ApiPipelineResult $pipelineResult */
        $pipelineResult = array_shift($updateResponses);

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

        $updatedIndexingEntity = $this->getIndexingEntityForEntity(
            apiKey: $apiKey,
            entity: $productFixture->getProduct(),
            type: 'KLEVU_PRODUCT',
        );
        $this->assertSame(expected: Actions::DELETE, actual: $updatedIndexingEntity->getLastAction());
        $this->assertSame(expected: Actions::NO_ACTION, actual: $updatedIndexingEntity->getNextAction());
        $this->assertFalse(condition: $updatedIndexingEntity->getIsIndexable());
        $this->assertNotNull(actual: $updatedIndexingEntity->getLastActionTimestamp());

        $this->cleanIndexingEntities(apiKey: $apiKey);
    }
}
