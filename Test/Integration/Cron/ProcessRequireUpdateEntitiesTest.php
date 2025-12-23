<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Test\Integration\Cron;

use Klevu\Indexing\Cron\ProcessRequireUpdateEntities;
use Klevu\Indexing\Model\IndexingEntity;
use Klevu\Indexing\Test\Integration\Traits\IndexingEntitiesTrait;
use Klevu\IndexingApi\Model\Source\Actions;
use Klevu\IndexingApi\Service\Provider\IndexingEntityProviderInterface;
use Klevu\IndexingProducts\Service\Determiner\RequiresUpdateCriteria\Status as StatusCriteria;
use Klevu\IndexingProducts\Service\Determiner\RequiresUpdateCriteria\StockStatus as StockStatusCriteria;
use Klevu\TestFixtures\Catalog\ProductTrait;
use Klevu\TestFixtures\Store\StoreFixturesPool;
use Klevu\TestFixtures\Store\StoreTrait;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Magento\Catalog\Model\Product\Attribute\Source\Status as SourceStatus;
use Magento\Catalog\Model\Product\Type;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Cron\Model\Config as CronConfig;
use Magento\Framework\App\Config\Storage\Writer as ConfigWriter;
use Magento\Framework\ObjectManagerInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use TddWizard\Fixtures\Catalog\ProductFixturePool;
use TddWizard\Fixtures\Core\ConfigFixture;

class ProcessRequireUpdateEntitiesTest extends TestCase
{
    use IndexingEntitiesTrait;
    use ObjectInstantiationTrait;
    use ProductTrait;
    use StoreTrait;

    /**
     * @var ObjectManagerInterface|null
     */
    private ?ObjectManagerInterface $objectManager = null; // @phpstan-ignore-line
    /**
     * @var CronConfig 
     */
    private CronConfig $cronConfig;
    /**
     * @var ConfigWriter|null
     */
    private ?ConfigWriter $configWriter = null;
    /**
     * @var IndexingEntityProviderInterface|null
     */
    private ?IndexingEntityProviderInterface $indexingEntityProvider;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->implementationFqcn = ProcessRequireUpdateEntities::class;

        $this->objectManager = Bootstrap::getObjectManager();
        $this->storeFixturesPool = $this->objectManager->get(StoreFixturesPool::class);
        $this->productFixturePool = $this->objectManager->get(ProductFixturePool::class);

        $this->cronConfig = $this->objectManager->get(CronConfig::class);
        $this->configWriter = $this->objectManager->get(ConfigWriter::class);
        $this->indexingEntityProvider = $this->objectManager->get(IndexingEntityProviderInterface::class);
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
        $this->cleanIndexingEntities('klevu-1234567890');
    }

    public function testExecute(): void
    {
        $this->createStore(
            storeData: [
                'key' => 'klevu_test_process_req_upd',
                'code' => 'klevu_test_process_req_upd',
                'name' => 'Klevu Test: Process Require Update Entities Cron',
                'is_active' => true,
            ],
        );
        $storeFixture = $this->storeFixturesPool->get('klevu_test_process_req_upd');

        ConfigFixture::setForStore(
            path: 'klevu_configuration/auth_keys/js_api_key',
            value: 'klevu-1234567890',
            storeCode: 'klevu_test_process_req_upd',
        );
        $this->configWriter->save(
            path: 'klevu_configuration/auth_keys/js_api_key',
            value: 'klevu-1234567890',
            scope: ScopeInterface::SCOPE_STORES,
            scopeId: $storeFixture->getId(),
        );
        $this->configWriter->save(
            path: 'klevu_configuration/auth_keys/rest_auth_key',
            value: 'ABCDE1234567890',
            scope: ScopeInterface::SCOPE_STORES,
            scopeId: $storeFixture->getId(),
        );

        $this->createProduct(
            productData: [
                'key' => 'klevu_test_process_require_update_1',
                'sku' => 'klevu_test_process_require_update_1',
                'name' => 'Klevu Test: Process Require Update Entities Cron (No change)',
                'status' => SourceStatus::STATUS_ENABLED,
                'visibility' => Visibility::VISIBILITY_BOTH,
                'in_stock' => true,
                'qty' => 100,
                'price' => 100.00,
                'website_ids' => array_unique([
                    $storeFixture->getWebsiteId(),
                ]),
                'type_id' => Type::TYPE_SIMPLE,
            ],
        );
        $productFixture1 = $this->productFixturePool->get('klevu_test_process_require_update_1');

        $this->createProduct(
            productData: [
                'key' => 'klevu_test_process_require_update_2',
                'sku' => 'klevu_test_process_require_update_2',
                'name' => 'Klevu Test: Process Require Update Entities Cron (Status Change)',
                'status' => SourceStatus::STATUS_ENABLED,
                'visibility' => Visibility::VISIBILITY_BOTH,
                'in_stock' => true,
                'qty' => 100,
                'price' => 100.00,
                'website_ids' => array_unique([
                    $storeFixture->getWebsiteId(),
                ]),
                'type_id' => Type::TYPE_SIMPLE,
            ],
        );
        $productFixture2 = $this->productFixturePool->get('klevu_test_process_require_update_2');

        $this->createProduct(
            productData: [
                'key' => 'klevu_test_process_require_update_3',
                'sku' => 'klevu_test_process_require_update_3',
                'name' => 'Klevu Test: Process Require Update Entities Cron (Stock Status Change)',
                'status' => SourceStatus::STATUS_ENABLED,
                'visibility' => Visibility::VISIBILITY_BOTH,
                'in_stock' => true,
                'qty' => 100,
                'price' => 100.00,
                'website_ids' => array_unique([
                    $storeFixture->getWebsiteId(),
                ]),
                'type_id' => Type::TYPE_SIMPLE,
            ],
        );
        $productFixture3 = $this->productFixturePool->get('klevu_test_process_require_update_3');

        $this->createProduct(
            productData: [
                'key' => 'klevu_test_process_require_update_4',
                'sku' => 'klevu_test_process_require_update_4',
                'name' => 'Klevu Test: Process Require Update Entities Cron (Changed - Not Requires Update)',
                'status' => SourceStatus::STATUS_ENABLED,
                'visibility' => Visibility::VISIBILITY_BOTH,
                'in_stock' => true,
                'qty' => 100,
                'price' => 100.00,
                'website_ids' => array_unique([
                    $storeFixture->getWebsiteId(),
                ]),
                'type_id' => Type::TYPE_SIMPLE,
            ],
        );
        $productFixture4 = $this->productFixturePool->get('klevu_test_process_require_update_4');

        $this->cleanIndexingEntities('klevu-1234567890');

        $indexingEntity1 = $this->createIndexingEntity(
            data: [
                IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
                IndexingEntity::TARGET_ENTITY_SUBTYPE => 'simple',
                IndexingEntity::API_KEY => 'klevu-1234567890',
                IndexingEntity::TARGET_ID => (int)$productFixture1->getId(),
                IndexingEntity::IS_INDEXABLE => true,
                IndexingEntity::REQUIRES_UPDATE => true,
                IndexingEntity::REQUIRES_UPDATE_ORIG_VALUES => [
                    StatusCriteria::CRITERIA_IDENTIFIER => true,
                    StockStatusCriteria::CRITERIA_IDENTIFIER => true,
                ],
            ],
        );
        $indexingEntity2 = $this->createIndexingEntity(
            data: [
                IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
                IndexingEntity::TARGET_ENTITY_SUBTYPE => 'simple',
                IndexingEntity::API_KEY => 'klevu-1234567890',
                IndexingEntity::TARGET_ID => (int)$productFixture2->getId(),
                IndexingEntity::IS_INDEXABLE => true,
                IndexingEntity::REQUIRES_UPDATE => true,
                IndexingEntity::REQUIRES_UPDATE_ORIG_VALUES => [
                    StatusCriteria::CRITERIA_IDENTIFIER => false,
                    StockStatusCriteria::CRITERIA_IDENTIFIER => true,
                ],
            ],
        );
        $indexingEntity3 = $this->createIndexingEntity(
            data: [
                IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
                IndexingEntity::TARGET_ENTITY_SUBTYPE => 'simple',
                IndexingEntity::API_KEY => 'klevu-1234567890',
                IndexingEntity::TARGET_ID => (int)$productFixture3->getId(),
                IndexingEntity::IS_INDEXABLE => true,
                IndexingEntity::REQUIRES_UPDATE => true,
                IndexingEntity::REQUIRES_UPDATE_ORIG_VALUES => [
                    StatusCriteria::CRITERIA_IDENTIFIER => true,
                    StockStatusCriteria::CRITERIA_IDENTIFIER => false,
                ],
            ],
        );
        $indexingEntity4 = $this->createIndexingEntity(
            data: [
                IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
                IndexingEntity::TARGET_ENTITY_SUBTYPE => 'simple',
                IndexingEntity::API_KEY => 'klevu-1234567890',
                IndexingEntity::TARGET_ID => (int)$productFixture4->getId(),
                IndexingEntity::IS_INDEXABLE => true,
                IndexingEntity::REQUIRES_UPDATE => false,
                IndexingEntity::REQUIRES_UPDATE_ORIG_VALUES => [
                    StatusCriteria::CRITERIA_IDENTIFIER => false,
                    StockStatusCriteria::CRITERIA_IDENTIFIER => false,
                ],
            ],
        );

        $processRequireUpdateEntitiesCron = $this->instantiateTestObject();

        $processRequireUpdateEntitiesCron->execute();

        $indexingEntities = $this->indexingEntityProvider->get(
            entityType: 'KLEVU_PRODUCT',
            apiKeys: ['klevu-1234567890'],
            entityIds: [
                $productFixture1->getId(),
                $productFixture2->getId(),
                $productFixture3->getId(),
                $productFixture4->getId(),
            ],
        );
        $this->assertCount(4, $indexingEntities);
        foreach ($indexingEntities as $indexingEntity) {
            $this->assertTrue($indexingEntity->getIsIndexable());
            $this->assertFalse($indexingEntity->getRequiresUpdate());

            switch ($indexingEntity->getTargetId()) {
                case $productFixture1->getId():
                    $this->assertSame(
                        expected: Actions::NO_ACTION,
                        actual: $indexingEntity->getNextAction(),
                    );
                    $this->assertEmpty($indexingEntity->getRequiresUpdateOrigValues());
                    break;

                case $productFixture2->getId():
                case $productFixture3->getId():
                    $this->assertSame(
                        expected: Actions::UPDATE,
                        actual: $indexingEntity->getNextAction(),
                    );
                    $this->assertEmpty($indexingEntity->getRequiresUpdateOrigValues());
                    break;

                case $productFixture4->getId():
                    $this->assertSame(
                        expected: Actions::NO_ACTION,
                        actual: $indexingEntity->getNextAction(),
                    );
                    $this->assertNotEmpty($indexingEntity->getRequiresUpdateOrigValues());
                    break;

                default:
                    $this->fail(sprintf(
                        'Unexpected indexing target id: %s',
                        $indexingEntity->getTargetId(),
                    ));
                    break;
            }
        }
    }
}
