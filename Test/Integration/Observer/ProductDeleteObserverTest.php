<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Test\Integration\Observer;

use Klevu\Indexing\Model\IndexingEntity;
use Klevu\Indexing\Model\ResourceModel\IndexingEntity\Collection;
use Klevu\Indexing\Test\Integration\Traits\IndexingEntitiesTrait;
use Klevu\IndexingApi\Api\Data\IndexingEntityInterface;
use Klevu\IndexingApi\Model\Source\Actions;
use Klevu\IndexingApi\Service\EntityUpdateResponderServiceInterface;
use Klevu\IndexingProducts\Observer\ProductDeleteObserver;
use Klevu\TestFixtures\Catalog\Attribute\AttributeFixturePool;
use Klevu\TestFixtures\Catalog\AttributeTrait;
use Klevu\TestFixtures\Catalog\ProductTrait;
use Klevu\TestFixtures\Store\StoreFixturesPool;
use Klevu\TestFixtures\Store\StoreTrait;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\SetAreaTrait;
use Klevu\TestFixtures\Traits\SetAuthKeysTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Magento\Catalog\Api\Data\CategoryInterface;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\ResourceModel\Product as ProductResourceModel;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Eav\Model\Entity\Attribute\AbstractAttribute;
use Magento\Eav\Model\Entity\Attribute\AttributeInterface;
use Magento\Framework\App\Area;
use Magento\Framework\DataObject;
use Magento\Framework\Event\ConfigInterface as EventConfig;
use Magento\Framework\Event\ManagerInterface as EventManager;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use TddWizard\Fixtures\Catalog\ProductFixturePool;
use TddWizard\Fixtures\Core\ConfigFixture;

/**
 * @covers \Klevu\IndexingProducts\Observer\ProductDeleteObserver
 * @method ObserverInterface instantiateTestObject(?array $arguments = null)
 * @method ObserverInterface instantiateTestObjectFromInterface(?array $arguments = null)
 */
class ProductDeleteObserverTest extends TestCase
{
    use AttributeTrait;
    use IndexingEntitiesTrait;
    use ObjectInstantiationTrait;
    use ProductTrait;
    use SetAreaTrait;
    use SetAuthKeysTrait;
    use StoreTrait;
    use TestImplementsInterfaceTrait;

    private const OBSERVER_NAME = 'Klevu_IndexingProducts_ProductDelete';
    private const EVENT_NAME = 'catalog_product_delete_after_done';

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

        $this->implementationFqcn = ProductDeleteObserver::class;
        $this->interfaceFqcn = ObserverInterface::class;
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

    public function testInvalidateCustomerDataObserver_IsConfigured(): void
    {
        $observerConfig = $this->objectManager->create(type: EventConfig::class);
        $observers = $observerConfig->getObservers(eventName: self::EVENT_NAME);

        $this->assertArrayHasKey(key: self::OBSERVER_NAME, array: $observers);
        $this->assertSame(
            expected: ltrim(string: ProductDeleteObserver::class, characters: '\\'),
            actual: $observers[self::OBSERVER_NAME]['instance'],
        );
    }

    /**
     * @magentoAppArea adminhtml
     * @magentoDbIsolation disabled
     * @magentoConfigFixture klevu_test_store_1_store klevu_configuration/auth_keys/js_api_key klevu-js-api-key
     * @magentoConfigFixture klevu_test_store_1_store klevu_configuration/auth_keys/rest_auth_key klevu-rest-auth-key
     */
    public function testDeletedProduct_newIndexingEntityIsSetToNotIndexable(): void
    {
        $apiKey = 'klevu-js-api-key';

        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');

        ConfigFixture::setForStore(
            path: 'klevu_configuration/auth_keys/js_api_key',
            value: $apiKey,
            storeCode: 'klevu_test_store_1',
        );
        ConfigFixture::setForStore(
            path: 'klevu_configuration/auth_keys/rest_auth_key',
            value: 'klevu-rest-auth-key',
            storeCode: 'klevu_test_store_1',
        );

        $this->setArea(Area::AREA_ADMINHTML);

        $this->cleanIndexingEntities($apiKey);

        $this->createProduct(
            productData: [
                'key' => 'test_product',
            ],
            storeId: (int)$storeFixture->getId(),
        );
        $productFixture = $this->productFixturePool->get('test_product');

        /** @var ProductInterface&DataObject $product */
        $product = $productFixture->getProduct();
        $productResourceModel = $this->objectManager->get(ProductResourceModel::class);
        $productResourceModel->delete($product);

        $collection = $this->objectManager->create(Collection::class);
        $collection->addFieldToFilter(IndexingEntity::TARGET_ID, ['eq' => $product->getId()]);
        $collection->addFieldToFilter(IndexingEntity::API_KEY, ['eq' => $apiKey]);
        $collection->addFieldToFilter(IndexingEntity::TARGET_ENTITY_TYPE, ['eq' => 'KLEVU_PRODUCT']);
        $indexingEntities = $collection->getItems();

        /** @var IndexingEntityInterface $indexingEntity */
        $indexingEntity = array_shift($indexingEntities);
        $this->assertInstanceOf(expected: IndexingEntityInterface::class, actual: $indexingEntity);
        $this->assertSame(
            expected: Actions::NO_ACTION,
            actual: $indexingEntity->getNextAction(),
            message:'Next Action: No Action',
        );
        $this->assertFalse(condition: $indexingEntity->getIsIndexable());
        $this->cleanIndexingEntities($apiKey);
    }

    /**
     * @magentoAppArea adminhtml
     * @magentoDbIsolation disabled
     * @magentoConfigFixture klevu_test_store_1_store klevu_configuration/auth_keys/js_api_key klevu-js-api-key
     * @magentoConfigFixture klevu_test_store_1_store klevu_configuration/auth_keys/rest_auth_key klevu-rest-auth-key
     */
    public function testDeletedProduct_IndexingEntityIsSetToDelete(): void
    {
        $apiKey = 'klevu-js-api-key';

        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');

        ConfigFixture::setForStore(
            path: 'klevu_configuration/auth_keys/js_api_key',
            value: $apiKey,
            storeCode: 'klevu_test_store_1',
        );
        ConfigFixture::setForStore(
            path: 'klevu_configuration/auth_keys/rest_auth_key',
            value: 'klevu-rest-auth-key',
            storeCode: 'klevu_test_store_1',
        );

        $this->setArea(Area::AREA_ADMINHTML);

        $this->cleanIndexingEntities($apiKey);

        $this->createProduct(
            productData: [
                'key' => 'test_product',
            ],
            storeId: (int)$storeFixture->getId(),
        );
        $productFixture = $this->productFixturePool->get('test_product');

        $this->cleanIndexingEntities($apiKey);

        $this->createIndexingEntity([
            IndexingEntity::TARGET_ID => $productFixture->getId(),
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::TARGET_ENTITY_SUBTYPE => 'simple',
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::NEXT_ACTION => Actions::NO_ACTION,
            IndexingEntity::LAST_ACTION => Actions::ADD,
            IndexingEntity::LAST_ACTION_TIMESTAMP => date('Y-m-d H:i:s'),
            IndexingEntity::IS_INDEXABLE => true,
        ]);

        /** @var ProductInterface&DataObject $product */
        $product = $productFixture->getProduct();
        $productResourceModel = $this->objectManager->get(ProductResourceModel::class);
        $productResourceModel->delete($product);

        $collection = $this->objectManager->create(Collection::class);
        $collection->addFieldToFilter(IndexingEntity::TARGET_ID, ['eq' => $product->getId()]);
        $collection->addFieldToFilter(IndexingEntity::API_KEY, ['eq' => $apiKey]);
        $collection->addFieldToFilter(IndexingEntity::TARGET_ENTITY_TYPE, ['eq' => 'KLEVU_PRODUCT']);
        $collection->addFieldToFilter(IndexingEntity::TARGET_PARENT_ID, ['null' => null]);
        $indexingEntities = $collection->getItems();

        /** @var IndexingEntityInterface $indexingEntity */
        $indexingEntity = array_shift($indexingEntities);
        $this->assertInstanceOf(expected: IndexingEntityInterface::class, actual: $indexingEntity);
        $this->assertSame(
            expected: Actions::DELETE,
            actual: $indexingEntity->getNextAction(),
            message: sprintf(
                'Expected Next Action: %s, Received: %s',
                Actions::DELETE->value,
                $indexingEntity->getNextAction()->value,
            ),
        );
        $this->assertTrue(condition: $indexingEntity->getIsIndexable());
        $this->cleanIndexingEntities($apiKey);
    }

    /**
     * @magentoAppArea adminhtml
     * @magentoDbIsolation disabled
     * @magentoConfigFixture klevu_test_store_1_store klevu_configuration/auth_keys/js_api_key klevu-js-api-key
     * @magentoConfigFixture klevu_test_store_1_store klevu_configuration/auth_keys/rest_auth_key klevu-rest-auth-key
     * @group wipm
     */
    public function testDeletedConfigurableProduct_IndexingEntityIsSetToDelete(): void
    {
        $apiKey = 'klevu-js-api-key';

        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');

        ConfigFixture::setForStore(
            path: 'klevu_configuration/auth_keys/js_api_key',
            value: $apiKey,
            storeCode: 'klevu_test_store_1',
        );
        ConfigFixture::setForStore(
            path: 'klevu_configuration/auth_keys/rest_auth_key',
            value: 'klevu-rest-auth-key',
            storeCode: 'klevu_test_store_1',
        );

        $this->setArea(Area::AREA_ADMINHTML);

        $this->createAttribute(
            attributeData: [
                'key' => 'klevu_prod_del_attr',
                'attribute_type' => 'configurable',
                'code' => 'klevu_prod_del_attr',
            ],
        );
        $attributeFixture = $this->attributeFixturePool->get('klevu_prod_del_attr');
        /** @var AbstractAttribute&AttributeInterface $attribute */
        $attribute = $attributeFixture->getAttribute();
        $attributeSource = $attribute->getSource();
        $this->createProduct(
            productData: [
                'key' => 'klevu_prod_del_variant',
                'sku' => 'klevu_prod_del_variant',
                'data' => [
                    $attributeFixture->getAttributeCode() => $attributeSource->getOptionId('Option 1'),
                ],
            ],
            storeId: (int)$storeFixture->getId(),
        );
        $variantProductFixture = $this->productFixturePool->get('klevu_prod_del_variant');

        $this->createProduct(
            productData: [
                'key' => 'klevu_prod_del_configurable',
                'sku' => 'klevu_prod_del_configurable',
                'type_id' => Configurable::TYPE_CODE,
                'configurable_attributes' => [
                    $attributeFixture->getAttribute(),
                ],
                'variants' => [
                    $variantProductFixture->getProduct(),
                ],
            ],
            storeId: (int)$storeFixture->getId(),
        );
        $configurableProductFixture = $this->productFixturePool->get('klevu_prod_del_configurable');

        $this->cleanIndexingEntities($apiKey);

        $this->createIndexingEntity(
            data: [
                IndexingEntity::TARGET_ID => $variantProductFixture->getId(),
                IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
                IndexingEntity::TARGET_ENTITY_SUBTYPE => 'simple',
                IndexingEntity::API_KEY => $apiKey,
                IndexingEntity::NEXT_ACTION => Actions::NO_ACTION,
                IndexingEntity::LAST_ACTION => Actions::ADD,
                IndexingEntity::LAST_ACTION_TIMESTAMP => date('Y-m-d H:i:s'),
                IndexingEntity::IS_INDEXABLE => true,
            ],
        );
        $this->createIndexingEntity(
            data: [
                IndexingEntity::TARGET_ID => $variantProductFixture->getId(),
                IndexingEntity::TARGET_PARENT_ID => $configurableProductFixture->getId(),
                IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
                IndexingEntity::TARGET_ENTITY_SUBTYPE => 'configurable_variants',
                IndexingEntity::API_KEY => $apiKey,
                IndexingEntity::NEXT_ACTION => Actions::NO_ACTION,
                IndexingEntity::LAST_ACTION => Actions::ADD,
                IndexingEntity::LAST_ACTION_TIMESTAMP => date('Y-m-d H:i:s'),
                IndexingEntity::IS_INDEXABLE => true,
            ],
        );
        $this->createIndexingEntity(
            data: [
                IndexingEntity::TARGET_ID => $configurableProductFixture->getId(),
                IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
                IndexingEntity::TARGET_ENTITY_SUBTYPE => 'configurable',
                IndexingEntity::API_KEY => $apiKey,
                IndexingEntity::NEXT_ACTION => Actions::NO_ACTION,
                IndexingEntity::LAST_ACTION => Actions::ADD,
                IndexingEntity::LAST_ACTION_TIMESTAMP => date('Y-m-d H:i:s'),
                IndexingEntity::IS_INDEXABLE => true,
            ],
        );

        $configurableProduct = $configurableProductFixture->getProduct();
        $productResourceModel = $this->objectManager->get(ProductResourceModel::class);
        $productResourceModel->delete($configurableProduct);

        /** @var Collection $collection */
        $collection = $this->objectManager->create(Collection::class);
        $collection->addFieldToFilter(
            field: IndexingEntity::TARGET_ID,
            condition: [
                'in' => [
                    (int)$configurableProductFixture->getId(),
                    (int)$variantProductFixture->getId(),
                ],
            ],
        );
        $collection->addFieldToFilter(IndexingEntity::API_KEY, ['eq' => $apiKey]);
        $collection->addFieldToFilter(IndexingEntity::TARGET_ENTITY_TYPE, ['eq' => 'KLEVU_PRODUCT']);

        $indexingEntities = $collection->getItems();
        $this->assertCount(
            expectedCount: 3,
            haystack: $indexingEntities,
        );

        foreach ($indexingEntities as $indexingEntity) {
            switch (true) {
                case $indexingEntity->getTargetId() === (int)$configurableProductFixture->getId():
                    $this->assertSame(
                        expected: Actions::DELETE,
                        actual: $indexingEntity->getNextAction(),
                        message: 'Next Action: Delete for Configurable Product',
                    );
                    break;

                case $indexingEntity->getTargetId() === (int)$variantProductFixture->getId()
                    && $indexingEntity->getTargetParentId() === (int)$configurableProductFixture->getId():
                    $this->assertSame(
                        expected: Actions::DELETE,
                        actual: $indexingEntity->getNextAction(),
                        message: 'Next Action: Delete for Variant Product',
                    );
                    break;

                case $indexingEntity->getTargetId() === (int)$variantProductFixture->getId()
                    && null === $indexingEntity->getTargetParentId():
                    $this->assertSame(
                        expected: Actions::UPDATE, // Because we trigger for the variant as an independent entity
                        actual: $indexingEntity->getNextAction(),
                        message: 'Next Action: Update for Simple Product',
                    );
                    break;

                default:
                    $this->fail(message: 'Unexpected Indexing Entity Target ID: ' . $indexingEntity->getTargetId());
                    break;
            }
        }
    }

    public function testResponderServiceNotCalled_ForNonCategoryInterface(): void
    {
        $mockResponderService = $this->getMockBuilder(EntityUpdateResponderServiceInterface::class)
            ->getMock();
        $mockResponderService->expects($this->never())
            ->method('execute');

        $cmsPageDeleteObserver = $this->objectManager->create(ProductDeleteObserver::class, [
            'responderService' => $mockResponderService,
        ]);
        $this->objectManager->addSharedInstance(
            instance: $cmsPageDeleteObserver,
            className: ProductDeleteObserver::class,
        );

        $category = $this->objectManager->get(CategoryInterface::class);
        $this->dispatchEvent(
            event: self::EVENT_NAME,
            entity: $category,
        );
    }

    /**
     * @param string $event
     * @param mixed $entity
     *
     * @return void
     */
    private function dispatchEvent(
        string $event,
        mixed $entity,
    ): void {
        /** @var EventManager $eventManager */
        $eventManager = $this->objectManager->get(type: EventManager::class);
        $eventManager->dispatch(
            $event,
            [
                'entity' => $entity,
            ],
        );
    }
}
