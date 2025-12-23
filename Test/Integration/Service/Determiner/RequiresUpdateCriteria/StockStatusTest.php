<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Test\Integration\Service\Determiner\RequiresUpdateCriteria;

use Klevu\IndexingApi\Api\Data\IndexingEntityInterfaceFactory;
use Klevu\IndexingApi\Service\Determiner\RequiresUpdateCriteriaInterface;
use Klevu\IndexingProducts\Service\Determiner\RequiresUpdateCriteria\StockStatus as StockStatusCriteria;
use Klevu\IndexingProducts\Service\Provider\ProductStockStatusProviderInterface;
use Klevu\TestFixtures\Catalog\ProductTrait;
use Klevu\TestFixtures\Store\StoreFixturesPool;
use Klevu\TestFixtures\Store\StoreTrait;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\Product\Attribute\Source\Status as SourceStatus;
use Magento\Catalog\Model\Product\Type;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Framework\App\Config\Storage\Writer as ConfigWriter;
use Magento\Framework\ObjectManagerInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use TddWizard\Fixtures\Catalog\ProductFixturePool;

class StockStatusTest extends TestCase
{
    use ObjectInstantiationTrait;
    use ProductTrait;
    use StoreTrait;
    use TestImplementsInterfaceTrait;

    /**
     * @var ObjectManagerInterface|null
     */
    private ?ObjectManagerInterface $objectManager = null; // @phpstan-ignore-line
    /**
     * @var IndexingEntityInterfaceFactory|null
     */
    private ?IndexingEntityInterfaceFactory $indexingEntityFactory = null;
    /**
     * @var ConfigWriter|null
     */
    private ?ConfigWriter $configWriter = null;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->implementationFqcn = StockStatusCriteria::class;
        $this->interfaceFqcn = RequiresUpdateCriteriaInterface::class;
        $this->objectManager = Bootstrap::getObjectManager();
        $this->storeFixturesPool = $this->objectManager->get(StoreFixturesPool::class);
        $this->productFixturePool = $this->objectManager->get(ProductFixturePool::class);

        $this->indexingEntityFactory = $this->objectManager->get(IndexingEntityInterfaceFactory::class);
        $this->configWriter = $this->objectManager->get(ConfigWriter::class);
    }

    /**
     * @return void
     * @throws \Exception
     */
    protected function tearDown(): void
    {
        parent::tearDown();

        $this->productFixturePool->rollback();
        $this->storeFixturesPool->rollback();
    }

    public function testExecute_WithoutOrigValue(): void
    {
        $indexingEntity = $this->indexingEntityFactory->create();
        $indexingEntity->setTargetEntityType('KLEVU_PRODUCT');
        $indexingEntity->setTargetId(1);
        $indexingEntity->setApiKey('klevu-1234567890');
        $indexingEntity->setIsIndexable(true);
        $indexingEntity->setRequiresUpdate(true);
        $indexingEntity->addRequiresUpdateOrigValue(
            criteria: 'foo',
            value: true,
        );

        $productStockStatusProviderMock = $this->getMockProductStatusProvider();
        $productStockStatusProviderMock->expects($this->never())
            ->method('get');

        /** @var StockStatusCriteria $statusCriteria */
        $statusCriteria = $this->instantiateTestObject([
            'productStockStatusProvider' => $productStockStatusProviderMock,
        ]);

        $result = $statusCriteria->execute(
            indexingEntity: $indexingEntity,
        );
        $this->assertFalse($result);
    }

    public function testExecute_SingleStore_DifferentApiKey_NoParent(): void
    {
        $this->createStore(
            storeData: [
                'key' => 'klevu_test_stock_status_criteria_1',
                'code' => 'klevu_test_stock_status_criteria_1',
                'name' => 'Klevu Test: Status Criteria (1)',
                'is_active' => true,
            ],
        );
        $storeFixture1 = $this->storeFixturesPool->get('klevu_test_stock_status_criteria_1');

        $this->configWriter->save(
            path: 'klevu_configuration/auth_keys/js_api_key',
            value: 'klevu-1234567890',
            scope: ScopeInterface::SCOPE_STORES,
            scopeId: $storeFixture1->getId(),
        );
        $this->configWriter->save(
            path: 'klevu_configuration/auth_keys/rest_auth_key',
            value: 'ABCDE1234567890',
            scope: ScopeInterface::SCOPE_STORES,
            scopeId: $storeFixture1->getId(),
        );

        $this->createProduct(
            productData: [
                'key' => 'klevu_test_stock_status_criteria_1',
                'sku' => 'klevu_test_stock_status_criteria_1',
                'name' => 'Klevu Test: Status Criteria (1)',
                'status' => SourceStatus::STATUS_ENABLED,
                'visibility' => Visibility::VISIBILITY_BOTH,
                'in_stock' => true,
                'qty' => 100,
                'price' => 100.00,
                'website_ids' => array_unique([
                    $storeFixture1->getWebsiteId(),
                ]),
                'type_id' => Type::TYPE_SIMPLE,
            ],
        );
        $productFixture1 = $this->productFixturePool->get('klevu_test_stock_status_criteria_1');

        $indexingEntity = $this->indexingEntityFactory->create();
        $indexingEntity->setTargetEntityType('KLEVU_PRODUCT');
        $indexingEntity->setTargetId((int)$productFixture1->getId());
        $indexingEntity->setApiKey('klevu-9876543210');
        $indexingEntity->setIsIndexable(true);
        $indexingEntity->setRequiresUpdate(true);
        $indexingEntity->addRequiresUpdateOrigValue(
            criteria: StockStatusCriteria::CRITERIA_IDENTIFIER,
            value: true,
        );

        $productStockStatusProviderMock = $this->getMockProductStatusProvider();
        $productStockStatusProviderMock->expects($this->never())
            ->method('get');

        /** @var StockStatusCriteria $statusCriteria */
        $statusCriteria = $this->instantiateTestObject([
            'productStockStatusProvider' => $productStockStatusProviderMock,
        ]);

        $result = $statusCriteria->execute(
            indexingEntity: $indexingEntity,
        );

        $this->productFixturePool->rollback();
        $this->storeFixturesPool->rollback();

        $this->assertFalse($result);
    }

    /**
     * @testWith [false, false, false]
     *           [false, true, true]
     *           [true, false, true]
     *           [true, true, false]
     * @runInSeparateProcess
     */
    public function testExecute_SingleStore_ApiKeyMatches_NoParent(
        bool $indexingEntityOrigValue,
        bool $providerReturnValue,
        bool $expectedResult,
    ): void {
        $this->createStore(
            storeData: [
                'key' => 'klevu_test_stock_status_criteria_1',
                'code' => 'klevu_test_stock_status_criteria_1',
                'name' => 'Klevu Test: Status Criteria (1)',
                'is_active' => true,
            ],
        );
        $storeFixture1 = $this->storeFixturesPool->get('klevu_test_stock_status_criteria_1');

        $this->configWriter->save(
            path: 'klevu_configuration/auth_keys/js_api_key',
            value: 'klevu-1234567890',
            scope: ScopeInterface::SCOPE_STORES,
            scopeId: $storeFixture1->getId(),
        );
        $this->configWriter->save(
            path: 'klevu_configuration/auth_keys/rest_auth_key',
            value: 'ABCDE1234567890',
            scope: ScopeInterface::SCOPE_STORES,
            scopeId: $storeFixture1->getId(),
        );

        $this->createProduct(
            productData: [
                'key' => 'klevu_test_stock_status_criteria_1',
                'sku' => 'klevu_test_stock_status_criteria_1',
                'name' => 'Klevu Test: Status Criteria (1)',
                'status' => SourceStatus::STATUS_ENABLED,
                'visibility' => Visibility::VISIBILITY_BOTH,
                'in_stock' => true,
                'qty' => 100,
                'price' => 100.00,
                'website_ids' => array_unique([
                    $storeFixture1->getWebsiteId(),
                ]),
                'type_id' => Type::TYPE_SIMPLE,
            ],
        );
        $productFixture1 = $this->productFixturePool->get('klevu_test_stock_status_criteria_1');

        $indexingEntity = $this->indexingEntityFactory->create();
        $indexingEntity->setTargetEntityType('KLEVU_PRODUCT');
        $indexingEntity->setTargetId((int)$productFixture1->getId());
        $indexingEntity->setApiKey('klevu-1234567890');
        $indexingEntity->setIsIndexable(true);
        $indexingEntity->setRequiresUpdate(true);
        $indexingEntity->addRequiresUpdateOrigValue(
            criteria: StockStatusCriteria::CRITERIA_IDENTIFIER,
            value: $indexingEntityOrigValue,
        );

        $productStockStatusProviderMock = $this->getMockProductStatusProvider();
        $productStockStatusProviderMock->expects($this->once())
            ->method('get')
            ->willReturnCallback(
                callback: function (
                    ProductInterface $product,
                    StoreInterface $store,
                    ?ProductInterface $parentProduct,
                ) use ($productFixture1, $storeFixture1, $providerReturnValue): bool {
                    $this->assertSame(
                        expected: $productFixture1->getSku(),
                        actual: $product->getSku(),
                    );
                    $this->assertSame(
                        expected: (int)$productFixture1->getId(),
                        actual: (int)$product->getId(),
                    );

                    $this->assertSame(
                        expected: (int)$storeFixture1->getId(),
                        actual: (int)$store->getId(),
                    );
                    $this->assertNull($parentProduct);

                    return $providerReturnValue;
                },
            );

        /** @var StockStatusCriteria $statusCriteria */
        $statusCriteria = $this->instantiateTestObject([
            'productStockStatusProvider' => $productStockStatusProviderMock,
        ]);

        $result = $statusCriteria->execute(
            indexingEntity: $indexingEntity,
        );

        $this->productFixturePool->rollback();
        $this->storeFixturesPool->rollback();

        $this->assertSame(
            expected: $expectedResult,
            actual: $result,
        );
    }

    /**
     * @testWith [false, [false, false, false], false]
     *           [true, [false, false, false], true]
     *           [false, [false, true, false], false]
     *           [false, [false, false, true], true]
     *           [true, [true, false, true], false]
     *           [true, [true, false, false], true]
     * @runInSeparateProcess
     *
     * @param bool $indexingEntityOrigValue
     * @param bool[] $providerReturnValueByStore
     * @param bool $expectedResult
     *
     * @return void
     * @throws \Exception
     */
    public function testExecute_MultipleStores_NoParent(
        bool $indexingEntityOrigValue,
        array $providerReturnValueByStore,
        bool $expectedResult,
    ): void {
        $storeFixtures = [];

        $this->createStore(
            storeData: [
                'key' => 'klevu_test_stock_status_criteria_1',
                'code' => 'klevu_test_stock_status_criteria_1',
                'name' => 'Klevu Test: Status Criteria (1)',
                'is_active' => true,
            ],
        );
        $storeFixtures[0] = $this->storeFixturesPool->get('klevu_test_stock_status_criteria_1');

        $this->configWriter->save(
            path: 'klevu_configuration/auth_keys/js_api_key',
            value: 'klevu-1234567890',
            scope: ScopeInterface::SCOPE_STORES,
            scopeId: $storeFixtures[0]->getId(),
        );
        $this->configWriter->save(
            path: 'klevu_configuration/auth_keys/rest_auth_key',
            value: 'ABCDE1234567890',
            scope: ScopeInterface::SCOPE_STORES,
            scopeId: $storeFixtures[0]->getId(),
        );

        $this->createStore(
            storeData: [
                'key' => 'klevu_test_stock_status_criteria_2',
                'code' => 'klevu_test_stock_status_criteria_2',
                'name' => 'Klevu Test: Status Criteria (2)',
                'is_active' => true,
            ],
        );
        $storeFixtures[1] = $this->storeFixturesPool->get('klevu_test_stock_status_criteria_2');

        $this->configWriter->save(
            path: 'klevu_configuration/auth_keys/js_api_key',
            value: 'klevu-9876543210',
            scope: ScopeInterface::SCOPE_STORES,
            scopeId: $storeFixtures[1]->getId(),
        );
        $this->configWriter->save(
            path: 'klevu_configuration/auth_keys/rest_auth_key',
            value: 'ABCDE1234567890',
            scope: ScopeInterface::SCOPE_STORES,
            scopeId: $storeFixtures[1]->getId(),
        );

        $this->createStore(
            storeData: [
                'key' => 'klevu_test_stock_status_criteria_3',
                'code' => 'klevu_test_stock_status_criteria_3',
                'name' => 'Klevu Test: Status Criteria (3)',
                'is_active' => true,
            ],
        );
        $storeFixtures[2] = $this->storeFixturesPool->get('klevu_test_stock_status_criteria_3');

        $this->configWriter->save(
            path: 'klevu_configuration/auth_keys/js_api_key',
            value: 'klevu-1234567890',
            scope: ScopeInterface::SCOPE_STORES,
            scopeId: $storeFixtures[2]->getId(),
        );
        $this->configWriter->save(
            path: 'klevu_configuration/auth_keys/rest_auth_key',
            value: 'ABCDE1234567890',
            scope: ScopeInterface::SCOPE_STORES,
            scopeId: $storeFixtures[2]->getId(),
        );

        $this->createProduct(
            productData: [
                'key' => 'klevu_test_stock_status_criteria_1',
                'sku' => 'klevu_test_stock_status_criteria_1',
                'name' => 'Klevu Test: Status Criteria (1)',
                'status' => SourceStatus::STATUS_ENABLED,
                'visibility' => Visibility::VISIBILITY_BOTH,
                'in_stock' => true,
                'qty' => 100,
                'price' => 100.00,
                'website_ids' => array_unique([
                    $storeFixtures[0]->getWebsiteId(),
                    $storeFixtures[1]->getWebsiteId(),
                    $storeFixtures[2]->getWebsiteId(),
                ]),
                'type_id' => Type::TYPE_SIMPLE,
            ],
        );
        $productFixture1 = $this->productFixturePool->get('klevu_test_stock_status_criteria_1');

        $indexingEntity = $this->indexingEntityFactory->create();
        $indexingEntity->setTargetEntityType('KLEVU_PRODUCT');
        $indexingEntity->setTargetId((int)$productFixture1->getId());
        $indexingEntity->setApiKey('klevu-1234567890');
        $indexingEntity->setIsIndexable(true);
        $indexingEntity->setRequiresUpdate(true);
        $indexingEntity->addRequiresUpdateOrigValue(
            criteria: StockStatusCriteria::CRITERIA_IDENTIFIER,
            value: $indexingEntityOrigValue,
        );

        $productStockStatusProviderMock = $this->getMockProductStatusProvider();
        $expectation = $this->atMost(2);
        $productStockStatusProviderMock->expects($expectation)
            ->method('get')
            ->willReturnCallback(
                callback: function (
                    ProductInterface $product,
                    StoreInterface $store,
                    ?ProductInterface $parentProduct,
                ) use ($expectation, $productFixture1, $storeFixtures, $providerReturnValueByStore): bool {
                    $invocationCount = match (true) {
                        method_exists($expectation, 'getInvocationCount') => $expectation->getInvocationCount(),
                        method_exists($expectation, 'numberOfInvocations') => $expectation->numberOfInvocations(),
                        default => throw new \RuntimeException('Cannot determine invocation count from matcher'),
                    };

                    $this->assertSame(
                        expected: $productFixture1->getSku(),
                        actual: $product->getSku(),
                    );
                    $this->assertSame(
                        expected: (int)$productFixture1->getId(),
                        actual: (int)$product->getId(),
                    );

                    $this->assertNull($parentProduct);
                    switch ($invocationCount) {
                        case 1:
                            $this->assertSame(
                                expected: (int)$storeFixtures[0]->getId(),
                                actual: (int)$store->getId(),
                            );
                            $return = $providerReturnValueByStore[0];
                            break;

                        case 2:
                            $this->assertSame(
                                expected: (int)$storeFixtures[2]->getId(),
                                actual: (int)$store->getId(),
                            );
                            $return = $providerReturnValueByStore[2];
                            break;

                        default:
                            $return = false;
                            break;
                    }

                    return $return;
                },
            );

        /** @var StockStatusCriteria $statusCriteria */
        $statusCriteria = $this->instantiateTestObject([
            'productStockStatusProvider' => $productStockStatusProviderMock,
        ]);

        $result = $statusCriteria->execute(
            indexingEntity: $indexingEntity,
        );

        $this->productFixturePool->rollback();
        $this->storeFixturesPool->rollback();

        $this->assertSame(
            expected: $expectedResult,
            actual: $result,
        );
    }

    /**
     * @testWith [false, true, true]
     * @runInSeparateProcess
     */
    public function testExecute_SingleStore_WithParent(
        bool $indexingEntityOrigValue,
        bool $providerReturnValue,
        bool $expectedResult,
    ): void {
        $this->createStore(
            storeData: [
                'key' => 'klevu_test_stock_status_criteria_1',
                'code' => 'klevu_test_stock_status_criteria_1',
                'name' => 'Klevu Test: Status Criteria (1)',
                'is_active' => true,
            ],
        );
        $storeFixture1 = $this->storeFixturesPool->get('klevu_test_stock_status_criteria_1');

        $this->configWriter->save(
            path: 'klevu_configuration/auth_keys/js_api_key',
            value: 'klevu-1234567890',
            scope: ScopeInterface::SCOPE_STORES,
            scopeId: $storeFixture1->getId(),
        );
        $this->configWriter->save(
            path: 'klevu_configuration/auth_keys/rest_auth_key',
            value: 'ABCDE1234567890',
            scope: ScopeInterface::SCOPE_STORES,
            scopeId: $storeFixture1->getId(),
        );

        $this->createProduct(
            productData: [
                'key' => 'klevu_test_stock_status_criteria_1',
                'sku' => 'klevu_test_stock_status_criteria_1',
                'name' => 'Klevu Test: Status Criteria (1)',
                'status' => SourceStatus::STATUS_ENABLED,
                'visibility' => Visibility::VISIBILITY_BOTH,
                'in_stock' => true,
                'qty' => 100,
                'price' => 100.00,
                'website_ids' => array_unique([
                    $storeFixture1->getWebsiteId(),
                ]),
                'type_id' => Type::TYPE_SIMPLE,
            ],
        );
        $productFixture1 = $this->productFixturePool->get('klevu_test_stock_status_criteria_1');

        $this->createProduct(
            productData: [
                'key' => 'klevu_test_stock_status_criteria_2',
                'sku' => 'klevu_test_stock_status_criteria_2',
                'name' => 'Klevu Test: Status Criteria (2)',
                'status' => SourceStatus::STATUS_ENABLED,
                'visibility' => Visibility::VISIBILITY_BOTH,
                'in_stock' => true,
                'qty' => 100,
                'price' => 100.00,
                'website_ids' => array_unique([
                    $storeFixture1->getWebsiteId(),
                ]),
                'type_id' => Type::TYPE_VIRTUAL,
            ],
        );
        $productFixture2 = $this->productFixturePool->get('klevu_test_stock_status_criteria_2');

        $indexingEntity = $this->indexingEntityFactory->create();
        $indexingEntity->setTargetEntityType('KLEVU_PRODUCT');
        $indexingEntity->setTargetId((int)$productFixture1->getId());
        $indexingEntity->setTargetParentId((int)$productFixture2->getId());
        $indexingEntity->setApiKey('klevu-1234567890');
        $indexingEntity->setIsIndexable(true);
        $indexingEntity->setRequiresUpdate(true);
        $indexingEntity->addRequiresUpdateOrigValue(
            criteria: StockStatusCriteria::CRITERIA_IDENTIFIER,
            value: $indexingEntityOrigValue,
        );

        $productStockStatusProviderMock = $this->getMockProductStatusProvider();
        $productStockStatusProviderMock->expects($this->once())
            ->method('get')
            ->willReturnCallback(
                callback: function (
                    ProductInterface $product,
                    StoreInterface $store,
                    ?ProductInterface $parentProduct,
                ) use ($productFixture1, $productFixture2, $storeFixture1, $providerReturnValue): bool {
                    $this->assertSame(
                        expected: $productFixture1->getSku(),
                        actual: $product->getSku(),
                    );
                    $this->assertSame(
                        expected: (int)$productFixture1->getId(),
                        actual: (int)$product->getId(),
                    );

                    $this->assertSame(
                        expected: (int)$storeFixture1->getId(),
                        actual: (int)$store->getId(),
                    );

                    $this->assertNotNull($parentProduct);
                    $this->assertSame(
                        expected: $productFixture2->getSku(),
                        actual: $parentProduct->getSku(),
                    );
                    $this->assertSame(
                        expected: (int)$productFixture2->getId(),
                        actual: (int)$parentProduct->getId(),
                    );

                    return $providerReturnValue;
                },
            );

        /** @var StockStatusCriteria $statusCriteria */
        $statusCriteria = $this->instantiateTestObject([
            'productStockStatusProvider' => $productStockStatusProviderMock,
        ]);

        $result = $statusCriteria->execute(
            indexingEntity: $indexingEntity,
        );

        $this->productFixturePool->rollback();
        $this->storeFixturesPool->rollback();

        $this->assertSame(
            expected: $expectedResult,
            actual: $result,
        );
    }

    /**
     * @testWith [true, [true, false, false], true]
     * @runInSeparateProcess
     *
     * @param bool $indexingEntityOrigValue
     * @param bool[] $providerReturnValueByStore
     * @param bool $expectedResult
     *
     * @return void
     * @throws \Exception
     */
    public function testExecute_MultipleStores_WithParent(
        bool $indexingEntityOrigValue,
        array $providerReturnValueByStore,
        bool $expectedResult,
    ): void {
        $storeFixtures = [];

        $this->createStore(
            storeData: [
                'key' => 'klevu_test_stock_status_criteria_1',
                'code' => 'klevu_test_stock_status_criteria_1',
                'name' => 'Klevu Test: Status Criteria (1)',
                'is_active' => true,
            ],
        );
        $storeFixtures[0] = $this->storeFixturesPool->get('klevu_test_stock_status_criteria_1');

        $this->configWriter->save(
            path: 'klevu_configuration/auth_keys/js_api_key',
            value: 'klevu-1234567890',
            scope: ScopeInterface::SCOPE_STORES,
            scopeId: $storeFixtures[0]->getId(),
        );
        $this->configWriter->save(
            path: 'klevu_configuration/auth_keys/rest_auth_key',
            value: 'ABCDE1234567890',
            scope: ScopeInterface::SCOPE_STORES,
            scopeId: $storeFixtures[0]->getId(),
        );

        $this->createStore(
            storeData: [
                'key' => 'klevu_test_stock_status_criteria_2',
                'code' => 'klevu_test_stock_status_criteria_2',
                'name' => 'Klevu Test: Status Criteria (2)',
                'is_active' => true,
            ],
        );
        $storeFixtures[1] = $this->storeFixturesPool->get('klevu_test_stock_status_criteria_2');

        $this->configWriter->save(
            path: 'klevu_configuration/auth_keys/js_api_key',
            value: 'klevu-9876543210',
            scope: ScopeInterface::SCOPE_STORES,
            scopeId: $storeFixtures[1]->getId(),
        );
        $this->configWriter->save(
            path: 'klevu_configuration/auth_keys/rest_auth_key',
            value: 'ABCDE1234567890',
            scope: ScopeInterface::SCOPE_STORES,
            scopeId: $storeFixtures[1]->getId(),
        );

        $this->createStore(
            storeData: [
                'key' => 'klevu_test_stock_status_criteria_3',
                'code' => 'klevu_test_stock_status_criteria_3',
                'name' => 'Klevu Test: Status Criteria (3)',
                'is_active' => true,
            ],
        );
        $storeFixtures[2] = $this->storeFixturesPool->get('klevu_test_stock_status_criteria_3');

        $this->configWriter->save(
            path: 'klevu_configuration/auth_keys/js_api_key',
            value: 'klevu-1234567890',
            scope: ScopeInterface::SCOPE_STORES,
            scopeId: $storeFixtures[2]->getId(),
        );
        $this->configWriter->save(
            path: 'klevu_configuration/auth_keys/rest_auth_key',
            value: 'ABCDE1234567890',
            scope: ScopeInterface::SCOPE_STORES,
            scopeId: $storeFixtures[2]->getId(),
        );

        $this->createProduct(
            productData: [
                'key' => 'klevu_test_stock_status_criteria_1',
                'sku' => 'klevu_test_stock_status_criteria_1',
                'name' => 'Klevu Test: Status Criteria (1)',
                'status' => SourceStatus::STATUS_ENABLED,
                'visibility' => Visibility::VISIBILITY_BOTH,
                'in_stock' => true,
                'qty' => 100,
                'price' => 100.00,
                'website_ids' => array_unique([
                    $storeFixtures[0]->getWebsiteId(),
                    $storeFixtures[1]->getWebsiteId(),
                    $storeFixtures[2]->getWebsiteId(),
                ]),
                'type_id' => Type::TYPE_SIMPLE,
            ],
        );
        $productFixture1 = $this->productFixturePool->get('klevu_test_stock_status_criteria_1');

        $this->createProduct(
            productData: [
                'key' => 'klevu_test_stock_status_criteria_2',
                'sku' => 'klevu_test_stock_status_criteria_2',
                'name' => 'Klevu Test: Status Criteria (2)',
                'status' => SourceStatus::STATUS_ENABLED,
                'visibility' => Visibility::VISIBILITY_BOTH,
                'in_stock' => true,
                'qty' => 100,
                'price' => 100.00,
                'website_ids' => array_unique([
                    $storeFixtures[0]->getWebsiteId(),
                    $storeFixtures[1]->getWebsiteId(),
                    $storeFixtures[2]->getWebsiteId(),
                ]),
                'type_id' => Type::TYPE_VIRTUAL,
            ],
        );
        $productFixture2 = $this->productFixturePool->get('klevu_test_stock_status_criteria_2');

        $indexingEntity = $this->indexingEntityFactory->create();
        $indexingEntity->setTargetEntityType('KLEVU_PRODUCT');
        $indexingEntity->setTargetId((int)$productFixture1->getId());
        $indexingEntity->setTargetParentId((int)$productFixture2->getId());
        $indexingEntity->setApiKey('klevu-1234567890');
        $indexingEntity->setIsIndexable(true);
        $indexingEntity->setRequiresUpdate(true);
        $indexingEntity->addRequiresUpdateOrigValue(
            criteria: StockStatusCriteria::CRITERIA_IDENTIFIER,
            value: $indexingEntityOrigValue,
        );

        $productStockStatusProviderMock = $this->getMockProductStatusProvider();
        $expectation = $this->atMost(2);
        $productStockStatusProviderMock->expects($expectation)
            ->method('get')
            ->willReturnCallback(
                callback: function (
                    ProductInterface $product,
                    StoreInterface $store,
                    ?ProductInterface $parentProduct,
                ) use ($expectation, $productFixture1, $productFixture2, $storeFixtures, $providerReturnValueByStore): bool {
                    $invocationCount = match (true) {
                        method_exists($expectation, 'getInvocationCount') => $expectation->getInvocationCount(),
                        method_exists($expectation, 'numberOfInvocations') => $expectation->numberOfInvocations(),
                        default => throw new \RuntimeException('Cannot determine invocation count from matcher'),
                    };

                    $this->assertSame(
                        expected: $productFixture1->getSku(),
                        actual: $product->getSku(),
                    );
                    $this->assertSame(
                        expected: (int)$productFixture1->getId(),
                        actual: (int)$product->getId(),
                    );

                    switch ($invocationCount) {
                        case 1:
                            $this->assertSame(
                                expected: (int)$storeFixtures[0]->getId(),
                                actual: (int)$store->getId(),
                            );
                            $return = $providerReturnValueByStore[0];
                            break;

                        case 2:
                            $this->assertSame(
                                expected: (int)$storeFixtures[2]->getId(),
                                actual: (int)$store->getId(),
                            );
                            $return = $providerReturnValueByStore[2];
                            break;

                        default:
                            $return = false;
                            break;
                    }

                    $this->assertNotNull($parentProduct);
                    $this->assertSame(
                        expected: $productFixture2->getSku(),
                        actual: $parentProduct->getSku(),
                    );
                    $this->assertSame(
                        expected: (int)$productFixture2->getId(),
                        actual: (int)$parentProduct->getId(),
                    );

                    return $return;
                },
            );

        /** @var StockStatusCriteria $statusCriteria */
        $statusCriteria = $this->instantiateTestObject([
            'productStockStatusProvider' => $productStockStatusProviderMock,
        ]);

        $result = $statusCriteria->execute(
            indexingEntity: $indexingEntity,
        );

        $this->productFixturePool->rollback();
        $this->storeFixturesPool->rollback();

        $this->assertSame(
            expected: $expectedResult,
            actual: $result,
        );
    }

    /**
     * @return MockObject&ProductStockStatusProviderInterface
     */
    private function getMockProductStatusProvider(): MockObject
    {
        return $this->getMockBuilder(ProductStockStatusProviderInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
    }
}