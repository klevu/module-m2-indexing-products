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
use Klevu\IndexingProducts\Service\EntityIndexerService\Add as EntityIndexerServiceVirtualType;
use Klevu\PhpSDK\Model\Indexing\RecordIterator;
use Klevu\PhpSDKPipelines\Model\ApiPipelineResult;
use Klevu\Pipelines\Exception\Pipeline\InvalidPipelineArgumentsException;
use Klevu\TestFixtures\Catalog\Attribute\AttributeFixturePool;
use Klevu\TestFixtures\Catalog\AttributeTrait;
use Klevu\TestFixtures\Catalog\CategoryTrait;
use Klevu\TestFixtures\Catalog\ProductTrait;
use Klevu\TestFixtures\Catalog\Review\ReviewFixturePool;
use Klevu\TestFixtures\Catalog\ReviewTrait;
use Klevu\TestFixtures\Store\StoreFixturesPool;
use Klevu\TestFixtures\Store\StoreTrait;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\PipelineEntityApiCallTrait;
use Klevu\TestFixtures\Traits\SetAuthKeysTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Type;
use Magento\Catalog\Model\Product\Visibility;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Downloadable\Model\Product\Type as DownloadableType;
use Magento\Framework\DataObject;
use Magento\Framework\ObjectManagerInterface;
use Magento\GroupedProduct\Model\Product\Type\Grouped;
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
class EntityIndexerServiceAddTest extends TestCase
{
    use AttributeTrait;
    use CategoryTrait;
    use IndexingEntitiesTrait;
    use ObjectInstantiationTrait;
    use PipelineEntityApiCallTrait;
    use ProductTrait;
    use ReviewTrait;
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
        $this->reviewFixturePool = $this->objectManager->get(ReviewFixturePool::class);
    }

    /**
     * @return void
     * @throws \Exception
     */
    protected function tearDown(): void
    {
        parent::tearDown();

        $this->reviewFixturePool->rollback();
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
            IndexingEntity::NEXT_ACTION => Actions::ADD,
        ]);

        $this->expectException(InvalidPipelineArgumentsException::class);
        $this->expectExceptionMessage(
            'Invalid arguments for pipeline "Klevu\PhpSDKPipelines\Pipeline\Stage\Indexing\SendBatchRequest". '
            . 'JS API Key argument (jsApiKey): Data is not valid',
        );

        $this->mockBatchServicePutApiCall(isCalled: false);

        $service = $this->instantiateTestObject();
        $service->execute(apiKey: $apiKey);

        $this->cleanIndexingEntities(apiKey: $apiKey);
    }

    /**
     * @magentoAppIsolation enabled
     * @magentoDbIsolation disabled
     */
    public function testExecute_ThrowsException_ForInvalidRestAuthKey(): void
    {
        $apiKey = 'klevu-123456789';

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
            IndexingEntity::NEXT_ACTION => Actions::ADD,
        ]);

        $this->expectException(InvalidPipelineArgumentsException::class);
        $this->expectExceptionMessage(
            'Invalid arguments for pipeline "Klevu\PhpSDKPipelines\Pipeline\Stage\Indexing\SendBatchRequest". '
            . 'REST AUTH Key argument (restAuthKey): Data is not valid',
        );

        $this->mockBatchServicePutApiCall(isCalled: false);

        $service = $this->instantiateTestObject();
        $service->execute(apiKey: $apiKey);

        $this->cleanIndexingEntities(apiKey: $apiKey);
    }

    public function testExecute_ReturnsNoop_WhenNoProductsToAdd(): void
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
            path: 'klevu/indexing/enable_product_sync',
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
            IndexingEntity::NEXT_ACTION => Actions::ADD,
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
            IndexingEntity::NEXT_ACTION => Actions::ADD,
        ]);

        $this->mockBatchServicePutApiCall(isCalled: true, isSuccessful: false);

        $service = $this->instantiateTestObject();
        $result = $service->execute(apiKey: $apiKey);

        $this->assertSame(
            expected: IndexerResultStatuses::PARTIAL,
            actual: $result->getStatus(),
            message: 'Status: ' . $result->getStatus()->name,
        );

        $pipelineResults = $result->getPipelineResult();
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
    public function testExecute_ReturnsSuccess_WhenSimpleProductAdded(): void
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
        ConfigFixture::setForStore(
            path: 'klevu/indexing/image_width_product',
            value: 800,
            storeCode: $storeFixture->getCode(),
        );
        ConfigFixture::setForStore(
            path: 'klevu/indexing/image_height_product',
            value: 800,
            storeCode: $storeFixture->getCode(),
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
            'in_stock' => false,
            'qty' => -3,
            'category_ids' => [
                $topCategoryFixture->getId(),
                $categoryFixture->getId(),
            ],
            'data' => [
                'short_description' => 'This is a short description',
                'description' => 'This is a longer description than the short description',
            ],
            'images' => [
                'klevu_image' => 'klevu_test_image_name.jpg',
                'image' => 'klevu_test_image_symbol.jpg',
            ],
        ]);
        $productFixture = $this->productFixturePool->get('test_product');
        $ratingIds = $this->getRatingIds();
        $ratings = [];
        $value = 3;
        foreach ($ratingIds as $ratingId) {
            $ratings[$ratingId] = $value;
            $value++;
        }
        $this->createReview([
            'product_id' => $productFixture->getId(),
            'customer_id' => null,
            'store_id' => $storeFixture->getId(),
            'ratings' => $ratings,
        ]);

        $this->cleanIndexingEntities(apiKey: $apiKey);
        $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ID => $productFixture->getId(),
            IndexingEntity::NEXT_ACTION => Actions::ADD,
        ]);

        $this->mockBatchServicePutApiCall();

        $service = $this->instantiateTestObject();
        $result = $service->execute(apiKey: $apiKey);

        $this->assertSame(
            expected: IndexerResultStatuses::SUCCESS,
            actual: $result->getStatus(),
            message: 'Status: ' . $result->getStatus()->name,
        );
        $pipelineResults = $result->getPipelineResult();
        $this->assertCount(expectedCount: 1, haystack: $pipelineResults);
        /** @var ApiPipelineResult $pipelineResult */
        $pipelineResult = array_shift($pipelineResults);

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
        $this->assertFalse(condition: $attributes['inStock'], message: 'In Stock');

        $this->assertArrayHasKey(key: 'url', array: $attributes);
        $this->assertStringContainsString(needle: 'klevu-simple-sku-001.html', haystack: $attributes['url']);

        $this->assertArrayHasKey(key: 'prices', array: $attributes);
        $defaultPriceArray = array_filter(
            array: $attributes['prices'],
            callback: static fn (array $price) => ($price['type'] ?? null) === 'defaultPrice',
        );
        $defaultPrice = array_shift($defaultPriceArray);
        $this->assertSame(expected: 9.99, actual: $defaultPrice['amount']);
        $this->assertSame(expected: 'USD', actual: $defaultPrice['currency']);
        $salePriceArray = array_filter(
            array: $attributes['prices'],
            callback: static fn (array $price) => ($price['type'] ?? null) === 'salePrice',
        );
        $salePrice = array_shift($salePriceArray);
        $this->assertSame(expected: 9.99, actual: $salePrice['amount']);
        $this->assertSame(expected: 'USD', actual: $salePrice['currency']);

        $this->assertArrayHasKey(key: 'images', array: $attributes);
        $images = $attributes['images'];
        $image = array_shift($images);
        $this->assertArrayHasKey(key: 'height', array: $image);
        $this->assertSame(expected: 800, actual: $image['height']);
        $this->assertArrayHasKey(key: 'width', array: $image);
        $this->assertSame(expected: 800, actual: $image['width']);
        $this->assertArrayHasKey(key: 'url', array: $image);
        $this->assertMatchesRegularExpression(
            pattern: '#/media/catalog/product/cache/.*/k/l/klevu_test_image_name(_.*)?\.jpg#',
            string: $image['url'],
            message: 'Image URL: ' . $image['url'],
        );
        $this->assertArrayHasKey(key: 'type', array: $image);
        $this->assertSame(
            expected: 'default',
            actual: $image['type'],
            message: 'Image Type: ' . $image['type'],
        );

        $this->assertArrayHasKey(key: 'rating', array: $attributes);
        $this->assertSame(expected: 4.0, actual: $attributes['rating']);

        $this->assertArrayHasKey(key: 'ratingCount', array: $attributes);
        $this->assertSame(expected: 1, actual: $attributes['ratingCount']);

        $this->cleanIndexingEntities(apiKey: $apiKey);
    }

    /**
     * @magentoAppIsolation enabled
     * @magentoDbIsolation disabled
     */
    public function testExecute_ReturnsSuccess_WhenVirtualProductAdded(): void
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
        ConfigFixture::setForStore(
            path: 'klevu/indexing/image_width_product',
            value: 800,
            storeCode: $storeFixture->getCode(),
        );
        ConfigFixture::setForStore(
            path: 'klevu/indexing/image_height_product',
            value: 800,
            storeCode: $storeFixture->getCode(),
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
            'type_id' => Type::TYPE_VIRTUAL,
            'name' => 'Klevu Virtual Product Test',
            'sku' => 'KLEVU-VIRTUAL-SKU-001',
            'price' => 19.99,
            'visibility' => Visibility::VISIBILITY_IN_CATALOG,
            'category_ids' => [
                $topCategoryFixture->getId(),
                $categoryFixture->getId(),
            ],
            'data' => [
                'short_description' => 'This is a Virtual product short description',
                'description' => 'This is a Virtual product longer description than the short description',
            ],
            'images' => [
                'image' => 'klevu_test_image_symbol.jpg',
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

        $this->mockBatchServicePutApiCall();

        $service = $this->instantiateTestObject();
        $result = $service->execute(apiKey: $apiKey);

        $this->assertSame(
            expected: IndexerResultStatuses::SUCCESS,
            actual: $result->getStatus(),
            message: 'Status: ' . $result->getStatus()->name,
        );
        $pipelineResults = $result->getPipelineResult();
        $this->assertCount(expectedCount: 1, haystack: $pipelineResults);
        /** @var ApiPipelineResult $pipelineResult */
        $pipelineResult = array_shift($pipelineResults);

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
            expected: 'KLEVU-VIRTUAL-SKU-001',
            actual: $attributes['sku'],
            message: 'SKU: ' . $attributes['sku'],
        );

        $this->assertArrayHasKey(key: 'name', array: $attributes);
        $this->assertArrayHasKey(key: 'default', array: $attributes['name']);
        $this->assertSame(
            expected: 'Klevu Virtual Product Test',
            actual: $attributes['name']['default'],
            message: 'Name: ' . $attributes['name']['default'],
        );

        $this->assertArrayHasKey(key: 'shortDescription', array: $attributes);
        $this->assertArrayHasKey(key: 'default', array: $attributes['shortDescription']);
        $this->assertSame(
            expected: 'This is a Virtual product short description',
            actual: $attributes['shortDescription']['default'],
            message: 'Short Description: ' . $attributes['shortDescription']['default'],
        );

        $this->assertArrayHasKey(key: 'description', array: $attributes);
        $this->assertArrayHasKey(key: 'default', array: $attributes['description']);
        $this->assertSame(
            expected: 'This is a Virtual product longer description than the short description',
            actual: $attributes['description']['default'],
            message: 'Description: ' . $attributes['description']['default'],
        );

        $this->assertArrayHasKey(key: 'visibility', array: $attributes);
        $this->assertContains(needle: 'catalog', haystack: $attributes['visibility']);
        $this->assertNotContains(needle: 'search', haystack: $attributes['visibility']);

        $this->assertArrayHasKey(key: 'inStock', array: $attributes);
        $this->assertTrue(condition: $attributes['inStock'], message: 'In Stock');

        $this->assertArrayHasKey(key: 'url', array: $attributes);
        $this->assertStringContainsString(needle: 'klevu-virtual-sku-001.html', haystack: $attributes['url']);

        $this->assertArrayHasKey(key: 'prices', array: $attributes);
        $defaultPriceArray = array_filter(
            array: $attributes['prices'],
            callback: static fn (array $price) => ($price['type'] ?? null) === 'defaultPrice',
        );
        $defaultPrice = array_shift($defaultPriceArray);
        $this->assertSame(expected: 19.99, actual: $defaultPrice['amount']);
        $this->assertSame(expected: 'USD', actual: $defaultPrice['currency']);
        $salePriceArray = array_filter(
            array: $attributes['prices'],
            callback: static fn (array $price) => ($price['type'] ?? null) === 'salePrice',
        );
        $salePrice = array_shift($salePriceArray);
        $this->assertSame(expected: 19.99, actual: $salePrice['amount']);
        $this->assertSame(expected: 'USD', actual: $salePrice['currency']);

        $this->assertArrayHasKey(key: 'images', array: $attributes);
        $images = $attributes['images'];
        $image = array_shift($images);
        $this->assertArrayHasKey(key: 'height', array: $image);
        $this->assertSame(expected: 800, actual: $image['height']);
        $this->assertArrayHasKey(key: 'width', array: $image);
        $this->assertSame(expected: 800, actual: $image['width']);
        $this->assertArrayHasKey(key: 'url', array: $image);
        $this->assertMatchesRegularExpression(
            pattern: '#/media/catalog/product/cache/.*/k/l/klevu_test_image_symbol(_.*)?\.jpg#',
            string: $image['url'],
            message: 'Image URL: ' . $image['url'],
        );
        $this->assertArrayHasKey(key: 'type', array: $image);
        $this->assertSame(
            expected: 'default',
            actual: $image['type'],
            message: 'Image Type: ' . $image['type'],
        );

        $this->assertArrayHasKey(key: 'rating', array: $attributes);
        $this->assertSame(expected: 0.0, actual: $attributes['rating']);

        $this->assertArrayHasKey(key: 'ratingCount', array: $attributes);
        $this->assertSame(expected: 0, actual: $attributes['ratingCount']);

        $this->cleanIndexingEntities(apiKey: $apiKey);
    }

    /**
     * @magentoAppIsolation enabled
     * @magentoDbIsolation disabled
     */
    public function testExecute_ReturnsSuccess_WhenDownloadableProductAdded(): void
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
            'is_anchor' => false,
        ]);
        $topCategoryFixture = $this->categoryFixturePool->get('top_cat');
        $this->createCategory([
            'key' => 'test_category',
            'parent' => $topCategoryFixture,
        ]);
        $categoryFixture = $this->categoryFixturePool->get('test_category');

        $this->createProduct([
            'type_id' => DownloadableType::TYPE_DOWNLOADABLE,
            'name' => 'Klevu Downloadable Product Test',
            'sku' => 'KLEVU-DOWNLOADABLE-SKU-001',
            'price' => 99.99,
            'visibility' => Visibility::VISIBILITY_IN_SEARCH,
            'category_ids' => [
                $categoryFixture->getId(),
            ],
            'data' => [
                'short_description' => 'This is a Downloadable product short description',
                'description' => 'This is a Downloadable product longer description than the short description',
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
            IndexingEntity::NEXT_ACTION => Actions::ADD,
        ]);

        $this->mockBatchServicePutApiCall();

        $service = $this->instantiateTestObject();
        $result = $service->execute(apiKey: $apiKey);

        $this->assertSame(
            expected: IndexerResultStatuses::SUCCESS,
            actual: $result->getStatus(),
            message: 'Status: ' . $result->getStatus()->name,
        );
        $pipelineResults = $result->getPipelineResult();
        $this->assertCount(expectedCount: 1, haystack: $pipelineResults);
        /** @var ApiPipelineResult $pipelineResult */
        $pipelineResult = array_shift($pipelineResults);

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
        $this->assertNotContains(needle: 'categoryid_' . $topCategoryFixture->getId(), haystack: $categories['values']);
        $this->assertContains(needle: 'categoryid_' . $categoryFixture->getId(), haystack: $categories['values']);

        $attributes = $record->getAttributes();
        $this->assertArrayHasKey(key: 'sku', array: $attributes);
        $this->assertSame(
            expected: 'KLEVU-DOWNLOADABLE-SKU-001',
            actual: $attributes['sku'],
            message: 'SKU: ' . $attributes['sku'],
        );

        $this->assertArrayHasKey(key: 'name', array: $attributes);
        $this->assertArrayHasKey(key: 'default', array: $attributes['name']);
        $this->assertSame(
            expected: 'Klevu Downloadable Product Test',
            actual: $attributes['name']['default'],
            message: 'Name: ' . $attributes['name']['default'],
        );

        $this->assertArrayHasKey(key: 'shortDescription', array: $attributes);
        $this->assertArrayHasKey(key: 'default', array: $attributes['shortDescription']);
        $this->assertSame(
            expected: 'This is a Downloadable product short description',
            actual: $attributes['shortDescription']['default'],
            message: 'Short Description: ' . $attributes['shortDescription']['default'],
        );

        $this->assertArrayHasKey(key: 'description', array: $attributes);
        $this->assertArrayHasKey(key: 'default', array: $attributes['description']);
        $this->assertSame(
            expected: 'This is a Downloadable product longer description than the short description',
            actual: $attributes['description']['default'],
            message: 'Description: ' . $attributes['description']['default'],
        );

        $this->assertArrayHasKey(key: 'visibility', array: $attributes);
        $this->assertNotContains(needle: 'catalog', haystack: $attributes['visibility']);
        $this->assertContains(needle: 'search', haystack: $attributes['visibility']);

        $this->assertArrayHasKey(key: 'inStock', array: $attributes);
        $this->assertTrue(condition: $attributes['inStock'], message: 'In Stock');

        $this->assertArrayHasKey(key: 'url', array: $attributes);
        $this->assertStringContainsString(needle: 'klevu-downloadable-sku-001.html', haystack: $attributes['url']);

        $this->assertArrayHasKey(key: 'prices', array: $attributes);
        $defaultPriceArray = array_filter(
            array: $attributes['prices'],
            callback: static fn (array $price) => ($price['type'] ?? null) === 'defaultPrice',
        );
        $defaultPrice = array_shift($defaultPriceArray);
        $this->assertSame(expected: 99.99, actual: $defaultPrice['amount']);
        $this->assertSame(expected: 'USD', actual: $defaultPrice['currency']);
        $salePriceArray = array_filter(
            array: $attributes['prices'],
            callback: static fn (array $price) => ($price['type'] ?? null) === 'salePrice',
        );
        $salePrice = array_shift($salePriceArray);
        $this->assertSame(expected: 54.99, actual: $salePrice['amount']);
        $this->assertSame(expected: 'USD', actual: $salePrice['currency']);

        $this->assertArrayHasKey(key: 'images', array: $attributes);
        $images = $attributes['images'];
        $this->assertNull($images);

        $this->assertArrayHasKey(key: 'rating', array: $attributes);
        $this->assertSame(expected: 0.0, actual: $attributes['rating']);

        $this->assertArrayHasKey(key: 'ratingCount', array: $attributes);
        $this->assertSame(expected: 0, actual: $attributes['ratingCount']);

        $this->cleanIndexingEntities(apiKey: $apiKey);
    }

    /**
     * @magentoAppIsolation enabled
     * @magentoDbIsolation disabled
     */
    public function testExecute_ReturnsSuccess_WhenConfigurableProductAdded(): void
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
        ConfigFixture::setForStore(
            path: 'klevu/indexing/image_width_product',
            value: 800,
            storeCode: $storeFixture->getCode(),
        );
        ConfigFixture::setForStore(
            path: 'klevu/indexing/image_height_product',
            value: 800,
            storeCode: $storeFixture->getCode(),
        );

        $this->createCategory([
            'key' => 'top_cat',
            'is_anchor' => false,
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
            'in_stock' => true,
            'qty' => 3,
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
            'in_stock' => true,
            'qty' => 3,
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
            'images' => [
                'klevu_image' => 'klevu_test_image_name.jpg',
                'image' => 'klevu_test_image_symbol.jpg',
            ],
        ]);
        $productFixture = $this->productFixturePool->get('test_product');
        $ratingIds = $this->getRatingIds();
        $ratings = [];
        $value = 3;
        foreach ($ratingIds as $ratingId) {
            $ratings[$ratingId] = $value;
            $value++;
        }
        $this->createReview([
            'product_id' => $productFixture->getId(),
            'customer_id' => null,
            'store_id' => $storeFixture->getId(),
            'ratings' => $ratings,
        ]);

        $this->cleanIndexingEntities(apiKey: $apiKey);
        $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ID => $productFixture->getId(),
            IndexingEntity::NEXT_ACTION => Actions::ADD,
        ]);

        $this->mockBatchServicePutApiCall();

        $service = $this->instantiateTestObject();
        $result = $service->execute(apiKey: $apiKey);

        $this->assertSame(
            expected: IndexerResultStatuses::SUCCESS,
            actual: $result->getStatus(),
            message: 'Status: ' . $result->getStatus()->name,
        );
        $pipelineResults = $result->getPipelineResult();
        $this->assertCount(expectedCount: 1, haystack: $pipelineResults);
        /** @var ApiPipelineResult $pipelineResult */
        $pipelineResult = array_shift($pipelineResults);

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
            expected: 'KLEVU_PARENT_PRODUCT',
            actual: $record->getType(),
            message: 'Record Type: ' . $record->getType(),
        );

        $relations = $record->getRelations();
        $this->assertArrayHasKey(key: 'categories', array: $relations);
        $categories = $relations['categories'];
        $this->assertArrayHasKey(key: 'values', array: $categories);
        $this->assertNotContains(needle: 'categoryid_' . $topCategoryFixture->getId(), haystack: $categories['values']);
        $this->assertContains(needle: 'categoryid_' . $categoryFixture->getId(), haystack: $categories['values']);

        $attributes = $record->getAttributes();
        $this->assertArrayHasKey(key: 'sku', array: $attributes);
        $this->assertSame(
            expected: 'KLEVU-CONFIGURABLE-SKU-001',
            actual: $attributes['sku'],
            message: 'SKU: ' . $attributes['sku'],
        );

        $this->assertArrayHasKey(key: 'name', array: $attributes);
        $this->assertArrayHasKey(key: 'default', array: $attributes['name']);
        $this->assertSame(
            expected: 'Klevu Configurable Product Test',
            actual: $attributes['name']['default'],
            message: 'Name: ' . $attributes['name']['default'],
        );

        $this->assertArrayHasKey(key: 'shortDescription', array: $attributes);
        $this->assertArrayHasKey(key: 'default', array: $attributes['shortDescription']);
        $this->assertSame(
            expected: 'This is a Configurable product short description',
            actual: $attributes['shortDescription']['default'],
            message: 'Short Description: ' . $attributes['shortDescription']['default'],
        );

        $this->assertArrayHasKey(key: 'description', array: $attributes);
        $this->assertArrayHasKey(key: 'default', array: $attributes['description']);
        $this->assertSame(
            expected: 'This is a Configurable product longer description than the short description',
            actual: $attributes['description']['default'],
            message: 'Description: ' . $attributes['description']['default'],
        );

        $this->assertArrayHasKey(key: 'visibility', array: $attributes);
        $this->assertNotContains(needle: 'catalog', haystack: $attributes['visibility']);
        $this->assertContains(needle: 'search', haystack: $attributes['visibility']);

        $this->assertArrayHasKey(key: 'inStock', array: $attributes);
        $this->assertTrue(condition: $attributes['inStock'], message: 'In Stock');

        $this->assertArrayHasKey(key: 'url', array: $attributes);
        $this->assertStringContainsString(needle: '/klevu-configurable-sku-001', haystack: $attributes['url']);

        $this->assertArrayHasKey(key: 'prices', array: $attributes);
        $defaultPriceArray = array_filter(
            array: $attributes['prices'],
            callback: static fn (array $price) => ($price['type'] ?? null) === 'defaultPrice',
        );
        $this->assertCount(expectedCount: 0, haystack: $defaultPriceArray);

        $salePriceArray = array_filter(
            array: $attributes['prices'],
            callback: static fn (array $price) => ($price['type'] ?? null) === 'salePrice',
        );
        $salePrice = array_shift($salePriceArray);
        $this->assertSame(expected: 39.99, actual: $salePrice['amount']);
        $this->assertSame(expected: 'USD', actual: $salePrice['currency']);

        $this->assertArrayHasKey(key: 'images', array: $attributes);
        $images = $attributes['images'];
        $image = array_shift($images);
        $this->assertArrayHasKey(key: 'height', array: $image);
        $this->assertSame(expected: 800, actual: $image['height']);
        $this->assertArrayHasKey(key: 'width', array: $image);
        $this->assertSame(expected: 800, actual: $image['width']);
        $this->assertArrayHasKey(key: 'url', array: $image);
        $this->assertMatchesRegularExpression(
            pattern: '#/media/catalog/product/cache/.*/k/l/klevu_test_image_name(_.*)?\.jpg#',
            string: $image['url'],
            message: 'Image URL: ' . $image['url'],
        );
        $this->assertArrayHasKey(key: 'type', array: $image);
        $this->assertSame(
            expected: 'default',
            actual: $image['type'],
            message: 'Image Type: ' . $image['type'],
        );

        $this->assertArrayHasKey(key: 'rating', array: $attributes);
        $this->assertSame(expected: 4.0, actual: $attributes['rating']);

        $this->assertArrayHasKey(key: 'ratingCount', array: $attributes);
        $this->assertSame(expected: 1, actual: $attributes['ratingCount']);

        $this->cleanIndexingEntities(apiKey: $apiKey);
    }

    /**
     * @magentoAppIsolation enabled
     * @magentoDbIsolation disabled
     */
    public function testExecute_ReturnsSuccess_WhenVariantProductAdded(): void
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
        ConfigFixture::setForStore(
            path: 'klevu/indexing/image_width_product',
            value: 800,
            storeCode: $storeFixture->getCode(),
        );
        ConfigFixture::setForStore(
            path: 'klevu/indexing/image_height_product',
            value: 800,
            storeCode: $storeFixture->getCode(),
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
            'images' => [
                'klevu_image' => 'klevu_test_image_name.jpg',
                'image' => 'klevu_test_image_symbol.jpg',
            ],
        ]);
        $variantProductFixture1 = $this->productFixturePool->get('test_product_variant_1');
        /** @var DataObject|ProductInterface $variantProduct1 */
        $variantProduct1 = $variantProductFixture1->getProduct();

        $ratingIds = $this->getRatingIds();
        $ratings = [];
        $value = 2;
        foreach ($ratingIds as $ratingId) {
            $ratings[$ratingId] = $value;
            $value++;
        }
        $this->createReview([
            'product_id' => $variantProductFixture1->getId(),
            'customer_id' => null,
            'store_id' => $storeFixture->getId(),
            'ratings' => $ratings,
        ]);

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
            IndexingEntity::NEXT_ACTION => Actions::ADD,
        ]);

        $this->mockBatchServicePutApiCall();

        $service = $this->instantiateTestObject();
        $result = $service->execute(apiKey: $apiKey);

        $this->assertSame(
            expected: IndexerResultStatuses::SUCCESS,
            actual: $result->getStatus(),
            message: 'Status: ' . $result->getStatus()->name,
        );
        $pipelineResults = $result->getPipelineResult();
        $this->assertCount(expectedCount: 1, haystack: $pipelineResults);
        /** @var ApiPipelineResult $pipelineResult */
        $pipelineResult = array_shift($pipelineResults);

        $this->assertTrue(condition: $pipelineResult->success);
        $this->assertCount(expectedCount: 1, haystack: $pipelineResult->messages);
        $this->assertContains(needle: 'Batch accepted successfully', haystack: $pipelineResult->messages);

        /** @var RecordIterator $payload */
        $payload = $pipelineResult->payload;
        $this->assertCount(expectedCount: 1, haystack: $payload);
        $record = $payload->current();

        $this->assertSame(
            expected: $productFixture->getId() . '-' . $variantProductFixture1->getId(),
            actual: $record->getId(),
            message: 'Record ID: ' . $record->getId(),
        );
        $this->assertSame(
            expected: 'KLEVU_PRODUCT',
            actual: $record->getType(),
            message: 'Record Type: ' . $record->getType(),
        );

        $relations = $record->getRelations();
        $this->assertArrayNotHasKey(key: 'categories', array: $relations);

        $this->assertArrayHasKey(key: 'parentProduct', array: $relations);
        $parentProductRelation = $relations['parentProduct'];
        $this->assertArrayHasKey(key: 'values', array: $parentProductRelation);
        $this->assertSame(expected: [(string)$productFixture->getId()], actual: $parentProductRelation['values']);

        $attributes = $record->getAttributes();
        $this->assertArrayHasKey(key: 'sku', array: $attributes);
        $this->assertSame(expected: $variantProduct1->getSku(), actual: $attributes['sku']);

        $this->assertArrayHasKey(key: 'shortDescription', array: $attributes);
        $this->assertArrayHasKey(key: 'default', array: $attributes['shortDescription']);
        $this->assertSame(
            expected: $variantProduct1->getData('short_description'),
            actual: $attributes['shortDescription']['default'],
        );

        $this->assertArrayHasKey(key: 'description', array: $attributes);
        $this->assertArrayHasKey(key: 'default', array: $attributes['description']);
        $this->assertSame(
            expected: $variantProduct1->getData('description'),
            actual: $attributes['description']['default'],
        );

        $this->assertArrayNotHasKey(key: 'name', array: $attributes);
        $this->assertArrayNotHasKey(key: 'visibility', array: $attributes);

        $this->assertArrayHasKey(key: 'inStock', array: $attributes);
        $this->assertFalse(condition: $attributes['inStock'], message: 'In Stock');

        $this->assertArrayHasKey(key: 'url', array: $attributes);
        $this->assertStringContainsString(needle: '/klevu-configurable-sku-001', haystack: $attributes['url']);

        $this->assertArrayHasKey(key: 'prices', array: $attributes);
        $defaultPriceArray = array_filter(
            array: $attributes['prices'],
            callback: static fn (array $price) => ($price['type'] ?? null) === 'defaultPrice',
        );
        $defaultPrice = array_shift($defaultPriceArray);
        $this->assertSame(expected: 99.99, actual: $defaultPrice['amount']);
        $this->assertSame(expected: 'USD', actual: $defaultPrice['currency']);
        $salePriceArray = array_filter(
            array: $attributes['prices'],
            callback: static fn (array $price) => ($price['type'] ?? null) === 'salePrice',
        );
        $salePrice = array_shift($salePriceArray);
        $this->assertSame(expected: 49.99, actual: $salePrice['amount']);
        $this->assertSame(expected: 'USD', actual: $salePrice['currency']);

        $this->assertArrayHasKey(key: 'images', array: $attributes);
        $images = $attributes['images'];
        $image = array_shift($images);
        $this->assertArrayHasKey(key: 'height', array: $image);
        $this->assertSame(expected: 800, actual: $image['height']);
        $this->assertArrayHasKey(key: 'width', array: $image);
        $this->assertSame(expected: 800, actual: $image['width']);
        $this->assertArrayHasKey(key: 'url', array: $image);
        $this->assertMatchesRegularExpression(
            pattern: '#/media/catalog/product/cache/.*/k/l/klevu_test_image_name(_.*)?\.jpg#',
            string: $image['url'],
            message: 'Image URL: ' . $image['url'],
        );
        $this->assertArrayHasKey(key: 'type', array: $image);
        $this->assertSame(
            expected: 'default',
            actual: $image['type'],
            message: 'Image Type: ' . $image['type'],
        );

        $this->assertArrayHasKey(key: 'rating', array: $attributes);
        $this->assertSame(expected: 3.0, actual: $attributes['rating']);

        $this->assertArrayHasKey(key: 'ratingCount', array: $attributes);
        $this->assertSame(expected: 1, actual: $attributes['ratingCount']);

        $this->cleanIndexingEntities(apiKey: $apiKey);
    }

    /**
     * @magentoAppIsolation enabled
     * @magentoDbIsolation disabled
     */
    public function testExecute_ReturnsSuccess_WhenGroupedProductAdded_MinPriceChildDisabled(): void
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
        ConfigFixture::setForStore(
            path: 'klevu/indexing/image_width_product',
            value: 800,
            storeCode: $storeFixture->getCode(),
        );
        ConfigFixture::setForStore(
            path: 'klevu/indexing/image_height_product',
            value: 800,
            storeCode: $storeFixture->getCode(),
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
            'status' => Status::STATUS_DISABLED,
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
            'key' => 'test_product_simple_3',
            'name' => 'Klevu Simple Product Test',
            'sku' => 'KLEVU-SIMPLE-SKU-003',
            'price' => 89.99,
            'category_ids' => [
                $topCategoryFixture->getId(),
                $categoryFixture->getId(),
            ],
            'data' => [
                'short_description' => 'This is a short description variant 3',
                'description' => 'This is a longer description than the short description variant 3',
                'special_price' => 59.99,
                'special_price_from' => '1970-01-01',
                'special_price_to' => '2099-12-31',
            ],
        ]);
        $simpleProductFixture3 = $this->productFixturePool->get('test_product_simple_3');
        $this->createProduct([
            'key' => 'test_product_simple_4',
            'name' => 'Klevu Simple Product Test',
            'sku' => 'KLEVU-SIMPLE-SKU-004',
            'price' => 74.99,
            'in_stock' => false,
            'qty' => 0,
            'category_ids' => [
                $topCategoryFixture->getId(),
                $categoryFixture->getId(),
            ],
            'data' => [
                'short_description' => 'This is a short description variant 4',
                'description' => 'This is a longer description than the short description variant 4',
                'special_price' => 46.99,
                'special_price_from' => '1970-01-01',
                'special_price_to' => '2099-12-31',
            ],
        ]);
        $simpleProductFixture4 = $this->productFixturePool->get('test_product_simple_4');

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
                $simpleProductFixture3,
                $simpleProductFixture4,
            ],
            'data' => [
                'short_description' => 'This is a Grouped product short description',
                'description' => 'This is a Grouped product longer description than the short description',
                'special_price' => 54.99,
                'special_price_from' => '1970-01-01',
                'special_price_to' => '2099-12-31',
            ],
            'images' => [
                'klevu_image' => 'klevu_test_image_name.jpg',
                'image' => 'klevu_test_image_symbol.jpg',
            ],
        ]);
        $productFixture = $this->productFixturePool->get('test_product');
        $ratingIds = $this->getRatingIds();
        $ratings = [];
        $value = 2;
        foreach ($ratingIds as $ratingId) {
            $ratings[$ratingId] = $value;
            $value++;
        }
        $this->createReview([
            'product_id' => $productFixture->getId(),
            'customer_id' => null,
            'store_id' => $storeFixture->getId(),
            'ratings' => $ratings,
        ]);

        $this->cleanIndexingEntities(apiKey: $apiKey);
        $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ID => $productFixture->getId(),
            IndexingEntity::NEXT_ACTION => Actions::ADD,
        ]);

        $this->mockBatchServicePutApiCall();

        $service = $this->instantiateTestObject();
        $result = $service->execute(apiKey: $apiKey);

        $this->assertSame(
            expected: IndexerResultStatuses::SUCCESS,
            actual: $result->getStatus(),
            message: 'Status: ' . $result->getStatus()->name,
        );
        $pipelineResults = $result->getPipelineResult();
        $this->assertCount(expectedCount: 1, haystack: $pipelineResults);
        /** @var ApiPipelineResult $pipelineResult */
        $pipelineResult = array_shift($pipelineResults);

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
            expected: 'KLEVU-GROUPED-SKU-001',
            actual: $attributes['sku'],
            message: 'SKU: ' . $attributes['sku'],
        );

        $this->assertArrayHasKey(key: 'name', array: $attributes);
        $this->assertArrayHasKey(key: 'default', array: $attributes['name']);
        $this->assertSame(
            expected: 'Klevu Grouped Product Test',
            actual: $attributes['name']['default'],
            message: 'Name: ' . $attributes['name']['default'],
        );

        $this->assertArrayHasKey(key: 'shortDescription', array: $attributes);
        $this->assertArrayHasKey(key: 'default', array: $attributes['shortDescription']);
        $this->assertSame(
            expected: 'This is a Grouped product short description',
            actual: $attributes['shortDescription']['default'],
            message: 'Short Description: ' . $attributes['shortDescription']['default'],
        );

        $this->assertArrayHasKey(key: 'description', array: $attributes);
        $this->assertArrayHasKey(key: 'default', array: $attributes['description']);
        $this->assertSame(
            expected: 'This is a Grouped product longer description than the short description',
            actual: $attributes['description']['default'],
            message: 'Description: ' . $attributes['description']['default'],
        );

        $this->assertArrayHasKey(key: 'visibility', array: $attributes);
        $this->assertContains(needle: 'catalog', haystack: $attributes['visibility']);
        $this->assertContains(needle: 'search', haystack: $attributes['visibility']);

        $this->assertArrayHasKey(key: 'inStock', array: $attributes);
        $this->assertTrue(condition: $attributes['inStock'], message: 'In Stock');

        $this->assertArrayHasKey(key: 'url', array: $attributes);
        $this->assertStringContainsString(needle: '/klevu-grouped-sku-001', haystack: $attributes['url']);

        $this->assertArrayHasKey(key: 'prices', array: $attributes);
        $defaultPriceArray = array_filter(
            array: $attributes['prices'],
            callback: static fn (array $price) => ($price['type'] ?? null) === 'defaultPrice',
        );
        $defaultPrice = array_shift($defaultPriceArray);
        $this->assertSame(expected: 74.99, actual: $defaultPrice['amount']);
        $this->assertSame(expected: 'USD', actual: $defaultPrice['currency']);
        $salePriceArray = array_filter(
            array: $attributes['prices'],
            callback: static fn (array $price) => ($price['type'] ?? null) === 'salePrice',
        );
        $salePrice = array_shift($salePriceArray);
        $this->assertSame(expected: 46.99, actual: $salePrice['amount']);
        $this->assertSame(expected: 'USD', actual: $salePrice['currency']);

        $this->assertArrayHasKey(key: 'images', array: $attributes);
        $images = $attributes['images'];
        $image = array_shift($images);
        $this->assertArrayHasKey(key: 'height', array: $image);
        $this->assertSame(expected: 800, actual: $image['height']);
        $this->assertArrayHasKey(key: 'width', array: $image);
        $this->assertSame(expected: 800, actual: $image['width']);
        $this->assertArrayHasKey(key: 'url', array: $image);
        $this->assertMatchesRegularExpression(
            pattern: '#/media/catalog/product/cache/.*/k/l/klevu_test_image_name(_.*)?\.jpg#',
            string: $image['url'],
            message: 'Image URL: ' . $image['url'],
        );
        $this->assertArrayHasKey(key: 'type', array: $image);
        $this->assertSame(
            expected: 'default',
            actual: $image['type'],
            message: 'Image Type: ' . $image['type'],
        );

        $this->assertArrayHasKey(key: 'rating', array: $attributes);
        $this->assertSame(expected: 3.0, actual: $attributes['rating']);

        $this->assertArrayHasKey(key: 'ratingCount', array: $attributes);
        $this->assertSame(expected: 1, actual: $attributes['ratingCount']);

        $this->cleanIndexingEntities(apiKey: $apiKey);
    }

    /**
     * @magentoAppIsolation enabled
     * @magentoDbIsolation disabled
     */
    public function testExecute_ReturnsSuccess_WhenGroupedProductAdded_withAllChildrenDisabled(): void
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
            'status' => Status::STATUS_DISABLED,
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
            'status' => Status::STATUS_DISABLED,
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
            'visibility' => Visibility::VISIBILITY_IN_SEARCH,
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
            IndexingEntity::NEXT_ACTION => Actions::ADD,
        ]);

        $this->mockBatchServicePutApiCall();

        $service = $this->instantiateTestObject();
        $result = $service->execute(apiKey: $apiKey);

        $this->assertSame(
            expected: IndexerResultStatuses::SUCCESS,
            actual: $result->getStatus(),
            message: 'Status: ' . $result->getStatus()->name,
        );
        $pipelineResults = $result->getPipelineResult();
        $this->assertCount(expectedCount: 1, haystack: $pipelineResults);
        /** @var ApiPipelineResult $pipelineResult */
        $pipelineResult = array_shift($pipelineResults);

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
            expected: 'KLEVU-GROUPED-SKU-001',
            actual: $attributes['sku'],
            message: 'SKU: ' . $attributes['sku'],
        );

        $this->assertArrayHasKey(key: 'name', array: $attributes);
        $this->assertArrayHasKey(key: 'default', array: $attributes['name']);
        $this->assertSame(
            expected: 'Klevu Grouped Product Test',
            actual: $attributes['name']['default'],
            message: 'Name: ' . $attributes['name']['default'],
        );

        $this->assertArrayHasKey(key: 'shortDescription', array: $attributes);
        $this->assertArrayHasKey(key: 'default', array: $attributes['shortDescription']);
        $this->assertSame(
            expected: 'This is a Grouped product short description',
            actual: $attributes['shortDescription']['default'],
            message: 'Short Description: ' . $attributes['shortDescription']['default'],
        );

        $this->assertArrayHasKey(key: 'description', array: $attributes);
        $this->assertArrayHasKey(key: 'default', array: $attributes['description']);
        $this->assertSame(
            expected: 'This is a Grouped product longer description than the short description',
            actual: $attributes['description']['default'],
            message: 'Description: ' . $attributes['description']['default'],
        );

        $this->assertArrayHasKey(key: 'visibility', array: $attributes);
        $this->assertNotContains(needle: 'catalog', haystack: $attributes['visibility']);
        $this->assertContains(needle: 'search', haystack: $attributes['visibility']);

        $this->assertArrayHasKey(key: 'inStock', array: $attributes);
        $this->assertFalse(condition: $attributes['inStock'], message: 'In Stock');

        $this->assertArrayHasKey(key: 'url', array: $attributes);
        $this->assertStringContainsString(needle: '/klevu-grouped-sku-001', haystack: $attributes['url']);

        $this->assertArrayHasKey(key: 'prices', array: $attributes);
        $defaultPriceArray = array_filter(
            array: $attributes['prices'],
            callback: static fn (array $price) => ($price['type'] ?? null) === 'defaultPrice',
        );
        $defaultPrice = array_shift($defaultPriceArray);
        $this->assertSame(expected: 0.00, actual: $defaultPrice['amount']);
        $this->assertSame(expected: 'USD', actual: $defaultPrice['currency']);
        $salePriceArray = array_filter(
            array: $attributes['prices'],
            callback: static fn (array $price) => ($price['type'] ?? null) === 'salePrice',
        );
        $salePrice = array_shift($salePriceArray);
        $this->assertSame(expected: 0.00, actual: $salePrice['amount']);
        $this->assertSame(expected: 'USD', actual: $salePrice['currency']);

        $this->assertArrayHasKey(key: 'rating', array: $attributes);
        $this->assertSame(expected: 0.0, actual: $attributes['rating']);

        $this->assertArrayHasKey(key: 'ratingCount', array: $attributes);
        $this->assertSame(expected: 0, actual: $attributes['ratingCount']);

        $this->cleanIndexingEntities(apiKey: $apiKey);
    }

    /**
     * @magentoAppIsolation enabled
     * @magentoDbIsolation disabled
     * @magentoConfigFixture klevu_test_store_1_store klevu/indexing/image_width_product 800
     * @magentoConfigFixture klevu_test_store_1_store klevu/indexing/image_height_product 800
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

        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');
        $scopeProvider = $this->objectManager->get(ScopeProviderInterface::class);
        $scopeProvider->setCurrentScope($storeFixture->get());
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
            'images' => [
                'klevu_image' => 'klevu_test_image_name.jpg',
                'image' => 'klevu_test_image_symbol.jpg',
            ],
        ]);
        $productFixture = $this->productFixturePool->get('test_product');
        $ratingIds = $this->getRatingIds();
        $ratings = [];
        $value = 3;
        foreach ($ratingIds as $ratingId) {
            $ratings[$ratingId] = $value;
            $value++;
        }
        $this->createReview([
            'product_id' => $productFixture->getId(),
            'customer_id' => null,
            'store_id' => $storeFixture->getId(),
            'ratings' => $ratings,
        ]);

        $this->cleanIndexingEntities(apiKey: $jsApiKey);
        $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::API_KEY => $jsApiKey,
            IndexingEntity::TARGET_ID => $productFixture->getId(),
            IndexingEntity::NEXT_ACTION => Actions::ADD,
        ]);

        $service = $this->instantiateTestObject();
        $result = $service->execute(apiKey: $jsApiKey);

        $this->assertSame(
            expected: IndexerResultStatuses::SUCCESS,
            actual: $result->getStatus(),
            message: 'Status: ' . $result->getStatus()->name,
        );
        $pipelineResults = $result->getPipelineResult();
        $this->assertCount(expectedCount: 1, haystack: $pipelineResults);
        /** @var ApiPipelineResult $pipelineResult */
        $pipelineResult = array_shift($pipelineResults);

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
        $this->assertTrue(condition: $attributes['inStock']);

        $this->assertArrayHasKey(key: 'url', array: $attributes);
        $this->assertStringContainsString(needle: 'klevu-simple-sku-001.html', haystack: $attributes['url']);

        $this->assertArrayHasKey(key: 'prices', array: $attributes);
        $defaultPriceArray = array_filter(
            array: $attributes['prices'],
            callback: static fn (array $price) => ($price['type'] ?? null) === 'defaultPrice',
        );
        $defaultPrice = array_shift($defaultPriceArray);
        $this->assertSame(expected: 9.99, actual: $defaultPrice['amount']);
        $this->assertSame(expected: 'USD', actual: $defaultPrice['currency']);
        $salePriceArray = array_filter(
            array: $attributes['prices'],
            callback: static fn (array $price) => ($price['type'] ?? null) === 'salePrice',
        );
        $salePrice = array_shift($salePriceArray);
        $this->assertSame(expected: 9.99, actual: $salePrice['amount']);
        $this->assertSame(expected: 'USD', actual: $salePrice['currency']);

        $this->assertArrayHasKey(key: 'images', array: $attributes);
        $images = $attributes['images'];
        $image = array_shift($images);
        $this->assertArrayHasKey(key: 'height', array: $image);
        $this->assertSame(expected: 800, actual: $image['height']);
        $this->assertArrayHasKey(key: 'width', array: $image);
        $this->assertSame(expected: 800, actual: $image['width']);
        $this->assertArrayHasKey(key: 'url', array: $image);
        $this->assertMatchesRegularExpression(
            pattern: '#/media/catalog/product/cache/.*/k/l/klevu_test_image_name(_.*)?\.jpg#',
            string: $image['url'],
            message: 'Image URL: ' . $image['url'],
        );
        $this->assertArrayHasKey(key: 'type', array: $image);
        $this->assertSame(
            expected: 'default',
            actual: $image['type'],
            message: 'Image Type: ' . $image['type'],
        );

        $this->assertArrayHasKey(key: 'rating', array: $attributes);
        $this->assertSame(expected: 4.0, actual: $attributes['rating']);

        $this->assertArrayHasKey(key: 'ratingCount', array: $attributes);
        $this->assertSame(expected: 1, actual: $attributes['ratingCount']);

        $this->cleanIndexingEntities(apiKey: $jsApiKey);
    }
}
