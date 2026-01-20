<?php


/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Test\Integration\Plugin\Catalog\Model\Product\Action;

use Klevu\Indexing\Model\IndexingEntity;
use Klevu\Indexing\Test\Integration\Traits\IndexingEntitiesTrait;
use Klevu\IndexingApi\Api\IndexingEntityRepositoryInterface;
use Klevu\IndexingApi\Model\Source\Actions;
use Klevu\TestFixtures\Catalog\ProductTrait;
use Klevu\TestFixtures\Store\StoreFixturesPool;
use Klevu\TestFixtures\Store\StoreTrait;
use Klevu\TestFixtures\Website\WebsiteFixturesPool;
use Klevu\TestFixtures\Website\WebsiteTrait;
use Magento\Catalog\Model\Product\Action as ProductAction;
use Magento\Catalog\Model\Product\Attribute\Source\Status as SourceStatus;
use Magento\Catalog\Model\Product\Type;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use TddWizard\Fixtures\Catalog\ProductFixturePool;
use TddWizard\Fixtures\Core\ConfigFixture;

class UpdateWebsitesPluginTest extends TestCase
{
    use IndexingEntitiesTrait;
    use ProductTrait;
    use StoreTrait;
    use WebsiteTrait;

    /**
     * @var ObjectManagerInterface|null
     */
    private ?ObjectManagerInterface $objectManager = null; // @phpstan-ignore-line
    /**
     * @var SearchCriteriaBuilder|null
     */
    private ?SearchCriteriaBuilder $searchCriteriaBuilder = null; // @phpstan-ignore-line
    /**
     * @var IndexingEntityRepositoryInterface|null
     */
    private ?IndexingEntityRepositoryInterface $indexingEntityRepository = null; // @phpstan-ignore-line

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->objectManager = Bootstrap::getObjectManager();

        $this->storeFixturesPool = $this->objectManager->get(StoreFixturesPool::class);
        $this->websiteFixturesPool = $this->objectManager->get(WebsiteFixturesPool::class);
        $this->productFixturePool = $this->objectManager->get(ProductFixturePool::class);

        $this->searchCriteriaBuilder = $this->objectManager->get(SearchCriteriaBuilder::class);
        $this->indexingEntityRepository = $this->objectManager->get(IndexingEntityRepositoryInterface::class);
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
        $this->websiteFixturesPool->rollback();
    }

    public function testUpdateWebsites_AddIntegratedWebsite(): void
    {
        $apiKey = 'klevu-1234567890';

        $fixtures = $this->createFixtures(
            productInIntegratedWebsite: false,
            productInNotIntegratedWebsite: true,
            apiKey: $apiKey,
        );

        $this->cleanIndexingEntities($apiKey);
        $indexingEntities = $this->getIndexingEntities(
            type: 'KLEVU_PRODUCT',
            apiKey: $apiKey,
        );
        $this->assertEmpty($indexingEntities);

        $productAction = $this->objectManager->create(ProductAction::class);
        $productAction->updateWebsites(
            productIds: [
                $fixtures['productFixture']->getId(),
            ],
            websiteIds: [
                $fixtures['websiteFixtureIntegrated']->getId(),
            ],
            type: 'add',
        );

        $indexingEntities = $this->getIndexingEntities(
            type: 'KLEVU_PRODUCT',
            apiKey: $apiKey,
        );
        $this->assertCount(
            expectedCount: 1,
            haystack: $indexingEntities,
        );
        $indexingEntity = current($indexingEntities);
        $this->assertSame(
            expected: (int)$fixtures['productFixture']->getId(),
            actual: $indexingEntity->getTargetId(),
        );
        $this->assertSame(
            expected: Actions::ADD,
            actual: $indexingEntity->getNextAction(),
        );

        $this->cleanIndexingEntities($apiKey);
    }

    /**
     * @group wip
     */
    public function testUpdateWebsites_AddNonIntegratedWebsite(): void
    {
        $apiKey = 'klevu-1234567890';

        $fixtures = $this->createFixtures(
            productInIntegratedWebsite: true,
            productInNotIntegratedWebsite: false,
            apiKey: $apiKey,
        );

        $this->cleanIndexingEntities($apiKey);
        $indexingEntities = $this->getIndexingEntities(
            type: 'KLEVU_PRODUCT',
            apiKey: $apiKey,
        );
        $this->assertEmpty($indexingEntities);

        $productAction = $this->objectManager->create(ProductAction::class);
        $productAction->updateWebsites(
            productIds: [
                $fixtures['productFixture']->getId(),
            ],
            websiteIds: [
                $fixtures['websiteFixtureNotIntegrated']->getId(),
            ],
            type: 'add',
        );

        $indexingEntities = $this->getIndexingEntities(
            type: 'KLEVU_PRODUCT',
            apiKey: $apiKey,
        );
        $this->assertEmpty($indexingEntities);

        $this->cleanIndexingEntities($apiKey);
    }

    public function testUpdateWebsites_RemoveIntegratedWebsite(): void
    {
        $apiKey = 'klevu-1234567890';

        $fixtures = $this->createFixtures(
            productInIntegratedWebsite: true,
            productInNotIntegratedWebsite: true,
            apiKey: $apiKey,
        );

        $this->cleanIndexingEntities($apiKey);
        $this->createIndexingEntity(
            data: [
                IndexingEntity::TARGET_ID => $fixtures['productFixture']->getId(),
                IndexingEntity::TARGET_ENTITY_SUBTYPE => 'simple',
                IndexingEntity::API_KEY => $apiKey,
                IndexingEntity::NEXT_ACTION => Actions::NO_ACTION,
                IndexingEntity::LAST_ACTION => Actions::ADD,
                IndexingEntity::IS_INDEXABLE => true,
            ],
        );

        $indexingEntities = $this->getIndexingEntities(
            type: 'KLEVU_PRODUCT',
            apiKey: $apiKey,
        );
        $this->assertCount(
            expectedCount: 1,
            haystack: $indexingEntities,
        );
        $indexingEntity = current($indexingEntities);
        $this->assertSame(
            expected: (int)$fixtures['productFixture']->getId(),
            actual: $indexingEntity->getTargetId(),
        );
        $this->assertSame(
            expected: Actions::NO_ACTION,
            actual: $indexingEntity->getNextAction(),
        );

        $productAction = $this->objectManager->create(ProductAction::class);
        $productAction->updateWebsites(
            productIds: [
                $fixtures['productFixture']->getId(),
            ],
            websiteIds: [
                $fixtures['websiteFixtureIntegrated']->getId(),
            ],
            type: 'remove',
        );

        $indexingEntities = $this->getIndexingEntities(
            type: 'KLEVU_PRODUCT',
            apiKey: $apiKey,
        );
        $this->assertCount(
            expectedCount: 1,
            haystack: $indexingEntities,
        );
        $indexingEntity = current($indexingEntities);
        $this->assertSame(
            expected: (int)$fixtures['productFixture']->getId(),
            actual: $indexingEntity->getTargetId(),
        );
        $this->assertSame(
            expected: Actions::DELETE,
            actual: $indexingEntity->getNextAction(),
        );

        $this->cleanIndexingEntities($apiKey);
    }

    public function testUpdateWebsites_RemoveNonIntegratedWebsite(): void
    {
        $apiKey = 'klevu-1234567890';

        $fixtures = $this->createFixtures(
            productInIntegratedWebsite: true,
            productInNotIntegratedWebsite: true,
            apiKey: $apiKey,
        );

        $this->cleanIndexingEntities($apiKey);
        $this->createIndexingEntity(
            data: [
                IndexingEntity::TARGET_ID => $fixtures['productFixture']->getId(),
                IndexingEntity::TARGET_ENTITY_SUBTYPE => 'simple',
                IndexingEntity::API_KEY => $apiKey,
                IndexingEntity::NEXT_ACTION => Actions::NO_ACTION,
                IndexingEntity::LAST_ACTION => Actions::ADD,
                IndexingEntity::IS_INDEXABLE => true,
            ],
        );

        $indexingEntities = $this->getIndexingEntities(
            type: 'KLEVU_PRODUCT',
            apiKey: $apiKey,
        );
        $this->assertCount(
            expectedCount: 1,
            haystack: $indexingEntities,
        );
        $indexingEntity = current($indexingEntities);
        $this->assertSame(
            expected: (int)$fixtures['productFixture']->getId(),
            actual: $indexingEntity->getTargetId(),
        );
        $this->assertSame(
            expected: Actions::NO_ACTION,
            actual: $indexingEntity->getNextAction(),
        );

        $productAction = $this->objectManager->create(ProductAction::class);
        $productAction->updateWebsites(
            productIds: [
                $fixtures['productFixture']->getId(),
            ],
            websiteIds: [
                $fixtures['websiteFixtureNotIntegrated']->getId(),
            ],
            type: 'remove',
        );

        $indexingEntities = $this->getIndexingEntities(
            type: 'KLEVU_PRODUCT',
            apiKey: $apiKey,
        );
        $this->assertCount(
            expectedCount: 1,
            haystack: $indexingEntities,
        );
        $indexingEntity = current($indexingEntities);
        $this->assertSame(
            expected: (int)$fixtures['productFixture']->getId(),
            actual: $indexingEntity->getTargetId(),
        );
        $this->assertSame(
            expected: Actions::NO_ACTION,
            actual: $indexingEntity->getNextAction(),
        );

        $this->cleanIndexingEntities($apiKey);
    }

    /**
     * @param bool $productInIntegratedWebsite
     * @param bool $productInNotIntegratedWebsite
     * @param string $apiKey
     *
     * @return array<string, object>
     * @throws \Exception
     */
    private function createFixtures(
        bool $productInIntegratedWebsite,
        bool $productInNotIntegratedWebsite,
        string $apiKey,
    ): array {
        $this->createWebsite(
            websiteData: [
                'key' => 'klevu_test_updatewebsites_1',
                'code' => 'klevu_test_updatewebsites_1',
            ],
        );
        $websiteFixtureIntegrated = $this->websiteFixturesPool->get('klevu_test_updatewebsites_1');
        $this->createStore(
            storeData: [
                'key' => 'klevu_test_updatewebsites_1',
                'code' => 'klevu_test_updatewebsites_1',
                'name' => 'Klevu Test: Update Websites Plugin (1)',
                'is_active' => true,
                'website_id' => $websiteFixtureIntegrated->getId(),
            ],
        );
        $storeFixtureIntegrated = $this->storeFixturesPool->get('klevu_test_updatewebsites_1');

        $this->createWebsite(
            websiteData: [
                'key' => 'klevu_test_updatewebsites_2',
                'code' => 'klevu_test_updatewebsites_2',
                'name' => 'Klevu Test: Update Websites Plugin (2)',
                'is_active' => true,
            ],
        );
        $websiteFixtureNotIntegrated = $this->websiteFixturesPool->get('klevu_test_updatewebsites_2');
        $this->createStore(
            storeData: [
                'key' => 'klevu_test_updatewebsites_2',
                'code' => 'klevu_test_updatewebsites_2',
                'name' => 'Klevu Test: Update Websites Plugin (2)',
                'is_active' => true,
                'website_id' => (int)$websiteFixtureNotIntegrated->getId(),
            ],
        );
        $storeFixtureNotIntegrated = $this->storeFixturesPool->get('klevu_test_updatewebsites_2');

        $productWebsiteIds = [];
        if ($productInIntegratedWebsite) {
            $productWebsiteIds[] = $websiteFixtureIntegrated->getId();
        }
        if ($productInNotIntegratedWebsite) {
            $productWebsiteIds[] = $websiteFixtureNotIntegrated->getId();
        }

        $this->createProduct(
            productData: [
                'key' => 'klevu_test_updatewebsites',
                'sku' => 'klevu_test_updatewebsites',
                'name' => 'Klevu Test: Update Websites Plugin',
                'status' => SourceStatus::STATUS_ENABLED,
                'visibility' => Visibility::VISIBILITY_BOTH,
                'in_stock' => true,
                'qty' => 100,
                'price' => 100.00,
                'type_id' => Type::TYPE_SIMPLE,
                'website_ids' => $productWebsiteIds,
            ],
        );
        $productFixture = $this->productFixturePool->get('klevu_test_updatewebsites');

        ConfigFixture::setForStore(
            path: 'klevu_configuration/auth_keys/js_api_key',
            value: $apiKey,
            storeCode: $storeFixtureIntegrated->getCode(),
        );
        ConfigFixture::setForStore(
            path: 'klevu_configuration/auth_keys/rest_auth_key',
            value: 'ABCDE1234567890',
            storeCode: $storeFixtureIntegrated->getCode(),
        );

        return [
            'websiteFixtureIntegrated' => $websiteFixtureIntegrated,
            'storeFixtureIntegrated' => $storeFixtureIntegrated,
            'websiteFixtureNotIntegrated' => $websiteFixtureNotIntegrated,
            'storeFixtureNotIntegrated' => $storeFixtureNotIntegrated,
            'productFixture' => $productFixture,
        ];
    }
}
