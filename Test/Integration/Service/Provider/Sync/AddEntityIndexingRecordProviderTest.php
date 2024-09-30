<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Test\Integration\Service\Provider\Sync;

use Klevu\Configuration\Service\Provider\ScopeProviderInterface;
use Klevu\Indexing\Model\IndexingEntity;
use Klevu\Indexing\Service\Provider\Sync\EntityIndexingRecordProvider;
use Klevu\Indexing\Test\Integration\Traits\IndexingEntitiesTrait;
use Klevu\IndexingApi\Model\EntityIndexingRecordInterface;
use Klevu\IndexingApi\Model\Source\Actions;
use Klevu\IndexingApi\Service\Provider\Sync\EntityIndexingRecordProviderInterface;
use Klevu\IndexingProducts\Service\Provider\Sync\EntityIndexingRecordProvider\Add as AddEntityIndexingRecordProviderVirtualType; //phpcs:ignore Generic.Files.LineLength.TooLong
use Klevu\TestFixtures\Catalog\Attribute\AttributeFixturePool;
use Klevu\TestFixtures\Catalog\AttributeTrait;
use Klevu\TestFixtures\Catalog\ProductTrait;
use Klevu\TestFixtures\Store\StoreFixturesPool;
use Klevu\TestFixtures\Store\StoreTrait;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\SetAuthKeysTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Magento\Eav\Model\Entity\Attribute\AbstractAttribute;
use Magento\Eav\Model\Entity\Attribute\AttributeInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use TddWizard\Fixtures\Catalog\ProductFixturePool;

/**
 * @covers EntityIndexingRecordProvider::class
 * @method EntityIndexingRecordProviderInterface instantiateTestObject(?array $arguments = null)
 * @method EntityIndexingRecordProviderInterface instantiateTestObjectFromInterface(?array $arguments = null)
 */
class AddEntityIndexingRecordProviderTest extends TestCase
{
    use AttributeTrait;
    use IndexingEntitiesTrait;
    use ObjectInstantiationTrait;
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

        $this->implementationFqcn = AddEntityIndexingRecordProviderVirtualType::class; //@phpstan-ignore-line
        $this->interfaceFqcn = EntityIndexingRecordProviderInterface::class;
        $this->implementationForVirtualType = EntityIndexingRecordProvider::class;
        $this->objectManager = Bootstrap::getObjectManager();
        $this->storeFixturesPool = $this->objectManager->get(StoreFixturesPool::class);
        $this->attributeFixturePool = $this->objectManager->get(AttributeFixturePool::class);
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
        $this->attributeFixturePool->rollback();
        $this->storeFixturesPool->rollback();
    }

    /**
     * @magentoAppIsolation enabled
     * @magentoDbIsolation disabled
     * @testWith ["simple"]
     *           ["virtual"]
     *           ["downloadable"]
     *           ["bundle"]
     */
    public function testGet_ReturnsEntitiesToAdd_ForSimpleProduct_InOneStore(string $productType): void
    {
        $apiKey = 'klevu-test-api-key';
        $this->cleanIndexingEntities(apiKey: $apiKey);

        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');
        $scopeProvider = $this->objectManager->get(ScopeProviderInterface::class);
        $scopeProvider->setCurrentScope(scope: $storeFixture->get());
        $this->setAuthKeys(
            scopeProvider: $scopeProvider,
            jsApiKey: $apiKey,
            restAuthKey: 'klevu-test-auth-key',
        );

        $this->createProduct([
            'type_id' => $productType,
        ]);
        $productFixture = $this->productFixturePool->get('test_product');

        $provider = $this->instantiateTestObject();
        $generator = $provider->get(apiKey: $apiKey);

        /** @var EntityIndexingRecordInterface[] $result */
        $result = [];
        foreach ($generator as $indexingRecord) {
            $result[] = $indexingRecord;
        }
        $this->assertCount(expectedCount: 1, haystack: $result);
        $this->assertNotNull(actual: (int)$result[0]->getRecordId());
        $this->assertSame(
            expected: (int)$productFixture->getId(),
            actual: (int)$result[0]->getEntity()->getId(),
        );
        $this->assertNull(actual: $result[0]->getParent());

        $this->cleanIndexingEntities(apiKey: $apiKey);
    }

    /**
     * @magentoAppIsolation enabled
     * @magentoDbIsolation disabled
     */
    public function testGet_ReturnsEntitiesToAdd_ForConfigurableProduct_InTwoStores(): void
    {
        $apiKey = 'klevu-test-api-key';
        $authKey = 'klevu-test-auth-key';

        $this->createStore([
            'key' => 'test_store_1',
            'code' => 'klevu_test_store_1',
        ]);
        $storeFixture1 = $this->storeFixturesPool->get('test_store_1');
        $this->createStore([
            'key' => 'test_store_2',
            'code' => 'klevu_test_store_2',
        ]);
        $storeFixture2 = $this->storeFixturesPool->get('test_store_2');

        $scopeProvider = $this->objectManager->get(ScopeProviderInterface::class);
        foreach ([$storeFixture1, $storeFixture2] as $storeFixture) {
            $scopeProvider->setCurrentScope(scope: $storeFixture->get());
            $this->setAuthKeys(
                scopeProvider: $scopeProvider,
                jsApiKey: $apiKey,
                restAuthKey: $authKey,
                removeApiKeys: false,
            );
        }

        $this->createAttribute([
            'attribute_type' => 'configurable',
        ]);
        $attributeFixture = $this->attributeFixturePool->get('test_attribute');
        /** @var AbstractAttribute&AttributeInterface $attribute */
        $attribute = $attributeFixture->getAttribute();
        $attributeSource = $attribute->getSource();

        $this->createProduct([
            'data' => [
                $attributeFixture->getAttributeCode() => $attributeSource->getOptionId('Option 1'),
            ],
        ]);
        $productFixture = $this->productFixturePool->get('test_product');

        $this->createProduct([
            'key' => 'test_configurable_product',
            'type_id' => 'configurable',
            'configurable_attributes' => [
                $attributeFixture->getAttribute(),
            ],
            'variants' => [
                $productFixture->getProduct(),
            ],
        ]);
        $configurableProductFixture = $this->productFixturePool->get('test_configurable_product');

        $this->cleanIndexingEntities(apiKey: $apiKey);
        $variantRecord = $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::TARGET_ID => $productFixture->getId(),
            IndexingEntity::TARGET_PARENT_ID => $configurableProductFixture->getId(),
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::NEXT_ACTION => Actions::ADD,
        ]);
        $parentRecord = $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::TARGET_ID => $configurableProductFixture->getId(),
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::NEXT_ACTION => Actions::ADD,
        ]);

        $provider = $this->instantiateTestObject();
        $generator = $provider->get(apiKey: $apiKey);

        /** @var EntityIndexingRecordInterface[] $results */
        $results = [];
        foreach ($generator as $indexingRecord) {
            $results[] = $indexingRecord;
        }
        $this->assertCount(expectedCount: 2, haystack: $results);
        $result1Array = array_filter(
            array: $results,
            callback: static fn (EntityIndexingRecordInterface $record) => ($record->getParent() !== null),
        );
        $result1 = array_shift($result1Array);
        $this->assertSame(
            expected: $variantRecord->getId(),
            actual: $result1->getRecordId(),
        );
        $this->assertSame(
            expected: (int)$productFixture->getId(),
            actual: (int)$result1->getEntity()?->getId(),
        );
        $this->assertSame(
            expected: (int)$configurableProductFixture->getId(),
            actual: (int)$result1->getParent()?->getId(),
        );

        $result2Array = array_filter(
            array: $results,
            callback: static fn (EntityIndexingRecordInterface $record) => ($record->getParent() === null),
        );
        $result2 = array_shift($result2Array);
        $this->assertSame(
            expected: $parentRecord->getId(),
            actual: $result2->getRecordId(),
        );
        $this->assertSame(
            expected: (int)$configurableProductFixture->getId(),
            actual: (int)$result2->getEntity()?->getId(),
        );
        $this->assertNull(actual: $result2->getParent());

        $this->cleanIndexingEntities(apiKey: $apiKey);
    }

    /**
     * @magentoAppIsolation enabled
     * @magentoDbIsolation disabled
     */
    public function testGet_ReturnsEntitiesToAdd_ForGroupedProduct_InTwoStores(): void
    {
        $apiKey = 'klevu-test-api-key';
        $authKey = 'klevu-test-auth-key';

        $this->createStore([
            'key' => 'test_store_1',
            'code' => 'klevu_test_store_1',
        ]);
        $storeFixture1 = $this->storeFixturesPool->get('test_store_1');
        $this->createStore([
            'key' => 'test_store_2',
            'code' => 'klevu_test_store_2',
        ]);
        $storeFixture2 = $this->storeFixturesPool->get('test_store_2');

        $scopeProvider = $this->objectManager->get(ScopeProviderInterface::class);
        foreach ([$storeFixture1, $storeFixture2] as $storeFixture) {
            $scopeProvider->setCurrentScope(scope: $storeFixture->get());
            $this->setAuthKeys(
                scopeProvider: $scopeProvider,
                jsApiKey: $apiKey,
                restAuthKey: $authKey,
                removeApiKeys: false,
            );
        }

        $this->createProduct([
            'key' => 'test_simple_product_1',
        ]);
        $simpleProductFixture1 = $this->productFixturePool->get('test_simple_product_1');
        $this->createProduct([
            'key' => 'test_simple_product_2',
        ]);
        $simpleProductFixture2 = $this->productFixturePool->get('test_simple_product_2');

        $this->createProduct([
            'key' => 'test_grouped_product',
            'type_id' => 'grouped',
            'linked_products' => [
                $simpleProductFixture1,
                $simpleProductFixture2,
            ],
        ]);
        $groupedProductFixture = $this->productFixturePool->get('test_grouped_product');

        $this->cleanIndexingEntities(apiKey: $apiKey);
        $indexingEntity = $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::TARGET_ID => $groupedProductFixture->getId(),
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::NEXT_ACTION => Actions::ADD,
        ]);

        $provider = $this->instantiateTestObject();
        $generator = $provider->get(apiKey: $apiKey);

        /** @var EntityIndexingRecordInterface[] $results */
        $results = [];
        foreach ($generator as $indexingRecord) {
            $results[] = $indexingRecord;
        }
        $this->assertCount(expectedCount: 1, haystack: $results);
        $result1 = array_shift($results);
        $this->assertSame(
            expected: $indexingEntity->getId(),
            actual: $result1->getRecordId(),
        );
        $this->assertSame(
            expected: (int)$groupedProductFixture->getId(),
            actual: (int)$result1->getEntity()?->getId(),
        );
        $this->assertNull(actual: $result1->getParent());

        $this->cleanIndexingEntities(apiKey: $apiKey);
    }
}
