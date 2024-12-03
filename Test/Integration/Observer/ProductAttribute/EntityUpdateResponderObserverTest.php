<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Test\Integration\Observer\ProductAttribute;

use Klevu\Configuration\Service\Provider\ScopeProviderInterface;
use Klevu\Indexing\Model\IndexingEntity;
use Klevu\Indexing\Test\Integration\Traits\IndexingEntitiesTrait;
use Klevu\IndexingApi\Model\Source\Actions;
use Klevu\IndexingApi\Model\Source\IndexType;
use Klevu\IndexingProducts\Model\Source\Aspect;
use Klevu\IndexingProducts\Observer\ProductAttribute\EntityUpdateResponderObserver;
use Klevu\TestFixtures\Catalog\Attribute\AttributeFixturePool;
use Klevu\TestFixtures\Catalog\AttributeTrait;
use Klevu\TestFixtures\Catalog\ProductTrait;
use Klevu\TestFixtures\Store\StoreFixturesPool;
use Klevu\TestFixtures\Store\StoreTrait;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\SetAuthKeysTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Magento\Catalog\Model\ResourceModel\Attribute as AttributeResourceModel;
use Magento\Eav\Api\Data\AttributeInterface;
use Magento\Framework\DataObject;
use Magento\Framework\Event\ConfigInterface as EventConfig;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use TddWizard\Fixtures\Catalog\ProductFixturePool;

/**
 * @covers EntityUpdateResponderObserver::class
 * @method ObserverInterface instantiateTestObject(?array $arguments = null)
 * @method ObserverInterface instantiateTestObjectFromInterface(?array $arguments = null)
 */
class EntityUpdateResponderObserverTest extends TestCase
{
    use AttributeTrait;
    use IndexingEntitiesTrait;
    use ObjectInstantiationTrait;
    use ProductTrait;
    use SetAuthKeysTrait;
    use StoreTrait;
    use TestImplementsInterfaceTrait;

    private const EVENT_NAME_DELETE = 'catalog_entity_attribute_delete_commit_after';
    private const OBSERVER_NAME_DELETE = 'Klevu_IndexingProducts_ProductAttribute_EntityUpdateResponder_Delete';

    /**
     * @var ObjectManagerInterface|null
     */
    private ?ObjectManagerInterface $objectManager = null; // @phpstan-ignore-line
    /**
     * @var AttributeResourceModel|null
     */
    private ?AttributeResourceModel $resourceModel = null;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->implementationFqcn = EntityUpdateResponderObserver::class;
        $this->interfaceFqcn = ObserverInterface::class;
        $this->objectManager = Bootstrap::getObjectManager();

        $this->resourceModel = $this->objectManager->get(AttributeResourceModel::class);
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

    public function testDeleteObserver_IsConfigured(): void
    {
        $observerConfig = $this->objectManager->create(type: EventConfig::class);
        $observers = $observerConfig->getObservers(eventName: self::EVENT_NAME_DELETE);

        $this->assertArrayHasKey(key: self::OBSERVER_NAME_DELETE, array: $observers);
        $this->assertSame(
            expected: ltrim(string: EntityUpdateResponderObserver::class, characters: '\\'),
            actual: $observers[self::OBSERVER_NAME_DELETE]['instance'],
        );
    }

    /**
     * @magentoDbIsolation disabled
     */
    public function testAttributeDelete_UpdateSubTypes(): void
    {
        $apiKey = 'klevu-js-api-key';

        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');
        $scopeProvider = $this->objectManager->get(ScopeProviderInterface::class);
        $scopeProvider->setCurrentScope($storeFixture->get());
        $this->setAuthKeys(
            scopeProvider: $scopeProvider,
            jsApiKey: $apiKey,
            restAuthKey: 'klevu-rest-key',
        );

        $this->createAttribute([
            'index_as' => IndexType::INDEX,
            'generate_config_for' => [
                'simple',
                'virtual',
                'grouped',
            ],
            'aspect' => Aspect::ATTRIBUTES,
        ]);
        $attributeFixture = $this->attributeFixturePool->get('test_attribute');
        /** @var AttributeInterface&DataObject $attribute */
        $attribute = $attributeFixture->getAttribute();

        $this->createProduct([
            'data' => [
                $attribute->getAttributeCode() => 'Some Value',
            ],
        ]);
        $productFixture1 = $this->productFixturePool->get('test_product');

        $this->createProduct([
            'key' => 'test_product_2',
            'type_id' => 'virtual',
            'data' => [
                $attribute->getAttributeCode() => 'Some Other Value',
            ],
        ]);
        $productFixture2 = $this->productFixturePool->get('test_product_2');

        $this->createProduct([
            'key' => 'test_product_3',
            'type_id' => 'grouped',
            'data' => [
                $attribute->getAttributeCode() => 'Another Value',
            ],
        ]);
        $productFixture3 = $this->productFixturePool->get('test_product_3');

        $this->cleanIndexingEntities($apiKey);
        $this->createIndexingEntity([
            IndexingEntity::TARGET_ID => $productFixture1->getId(),
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::TARGET_ENTITY_SUBTYPE => 'simple',
            IndexingEntity::NEXT_ACTION => Actions::NO_ACTION,
            IndexingEntity::LAST_ACTION => Actions::ADD,
            IndexingEntity::LAST_ACTION_TIMESTAMP => date('Y-m-d H:i:s'),
            IndexingEntity::IS_INDEXABLE => true,
        ]);
        $this->createIndexingEntity([
            IndexingEntity::TARGET_ID => $productFixture2->getId(),
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::TARGET_ENTITY_SUBTYPE => 'virtual',
            IndexingEntity::NEXT_ACTION => Actions::NO_ACTION,
            IndexingEntity::LAST_ACTION => Actions::ADD,
            IndexingEntity::LAST_ACTION_TIMESTAMP => date('Y-m-d H:i:s'),
            IndexingEntity::IS_INDEXABLE => true,
        ]);
        $this->createIndexingEntity([
            IndexingEntity::TARGET_ID => $productFixture3->getId(),
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::TARGET_ENTITY_SUBTYPE => 'grouped',
            IndexingEntity::NEXT_ACTION => Actions::NO_ACTION,
            IndexingEntity::LAST_ACTION => Actions::ADD,
            IndexingEntity::LAST_ACTION_TIMESTAMP => date('Y-m-d H:i:s'),
            IndexingEntity::IS_INDEXABLE => true,
        ]);

        $this->resourceModel->delete($attribute); // @phpstan-ignore-line

        $indexingEntity1 = $this->getIndexingEntityForEntity(
            apiKey: $apiKey,
            entity: $productFixture1->getProduct(),
            type: 'KLEVU_PRODUCT',
        );
        $this->assertSame(
            expected: Actions::UPDATE,
            actual: $indexingEntity1->getNextAction(),
            message: 'Expected ' . Actions::UPDATE->value . ', received ' . $indexingEntity1->getNextAction()->value,
        );

        $indexingEntity2 = $this->getIndexingEntityForEntity(
            apiKey: $apiKey,
            entity: $productFixture2->getProduct(),
            type: 'KLEVU_PRODUCT',
        );
        $this->assertSame(
            expected: Actions::UPDATE,
            actual: $indexingEntity2->getNextAction(),
            message: 'Expected ' . Actions::UPDATE->value . ', received ' . $indexingEntity2->getNextAction()->value,
        );

        $indexingEntity3 = $this->getIndexingEntityForEntity(
            apiKey: $apiKey,
            entity: $productFixture3->getProduct(),
            type: 'KLEVU_PRODUCT',
        );
        $this->assertSame(
            expected: Actions::UPDATE,
            actual: $indexingEntity3->getNextAction(),
            message: 'Expected ' . Actions::UPDATE->value . ', received ' . $indexingEntity3->getNextAction()->value,
        );
    }
}
