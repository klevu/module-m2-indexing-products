<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Test\Integration\Console\Command;

use Klevu\Configuration\Service\Provider\ScopeProviderInterface;
use Klevu\Indexing\Console\Command\SyncEntitiesCommand;
use Klevu\Indexing\Model\IndexingEntity;
use Klevu\Indexing\Test\Integration\Traits\IndexingEntitiesTrait;
use Klevu\IndexingApi\Model\Source\Actions;
use Klevu\TestFixtures\Catalog\Attribute\AttributeFixturePool;
use Klevu\TestFixtures\Catalog\AttributeTrait;
use Klevu\TestFixtures\Catalog\ProductTrait;
use Klevu\TestFixtures\Store\StoreFixturesPool;
use Klevu\TestFixtures\Store\StoreTrait;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\PipelineEntityApiCallTrait;
use Klevu\TestFixtures\Traits\SetAuthKeysTrait;
use Magento\Catalog\Model\Product\Type;
use Magento\Framework\Console\Cli;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;
use TddWizard\Fixtures\Catalog\ProductFixturePool;

/**
 * @covers \Klevu\Indexing\Console\Command\SyncEntitiesCommand::class
 * @method SyncEntitiesCommand instantiateTestObject(?array $arguments = null)
 */
class SyncEntitiesCommandTest extends TestCase
{
    use AttributeTrait;
    use IndexingEntitiesTrait;
    use ObjectInstantiationTrait;
    use PipelineEntityApiCallTrait;
    use ProductTrait;
    use SetAuthKeysTrait;
    use StoreTrait;

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

        $this->implementationFqcn = SyncEntitiesCommand::class;
        // newrelic-describe-commands globs onto Console commands
        $this->expectPlugins = true;

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
     */
    public function testExecute_Failure_ForSimpleProductTypes_AddAndUpdate(): void
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
            restAuthKey: 'SomeValidRestKey123',
        );

        $this->createProduct();
        $simpleProductFixture = $this->productFixturePool->get('test_product');

        $this->createProduct([
            'key' => 'test_virtual_product',
            'type_id' => Type::TYPE_VIRTUAL,
        ]);
        $virtualProductFixture = $this->productFixturePool->get('test_virtual_product');

        $this->cleanIndexingEntities(apiKey: $apiKey);
        $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ID => $simpleProductFixture->getId(),
            IndexingEntity::NEXT_ACTION => Actions::ADD,
        ]);
        $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ID => $virtualProductFixture->getId(),
            IndexingEntity::NEXT_ACTION => Actions::UPDATE,
        ]);

        $this->mockBatchServicePutApiCall(isCalled: true, isSuccessful: false);
        $this->mockBatchServiceDeleteApiCall(isCalled: false);

        $syncEntitiesCommand = $this->instantiateTestObject();
        $tester = new CommandTester(
            command: $syncEntitiesCommand,
        );
        $isFailure = $tester->execute(
            input: [
                '--entity-types' => 'KLEVU_PRODUCT',
                '--api-keys' => $apiKey,
            ],
            options: [
                'verbosity' => OutputInterface::VERBOSITY_DEBUG,
            ],
        );

        $this->assertSame(expected: Cli::RETURN_SUCCESS, actual: $isFailure, message: 'Entity Sync Succeeds');

        $display = $tester->getDisplay();
        $this->assertStringContainsString(
            needle: sprintf('Begin Entity Sync with filters: Entity Types = KLEVU_PRODUCT, API Keys = %s', $apiKey),
            haystack: $display,
        );
        $this->assertStringContainsString(
            needle: sprintf('Entity Sync for API Key: %s.', $apiKey),
            haystack: $display,
        );

        $pattern = '#'
            . 'Action  : KLEVU_PRODUCT::add'
            . '\s*Batches : 1'
            . '\s*Batch        : 0'
            . '\s*Success      : False'
            . '\s*API Response : .*'
            . '\s*Job ID       : n/a'
            . '\s*Record Count : 1'
            . '\s*There has been an ERROR'
            . '\s*--'
            . '#';
        $matches = [];
        preg_match(
            pattern: $pattern,
            subject: $display,
            matches: $matches,
        );
        $this->assertCount(1, $matches, 'KLEVU_PRODUCT::add batches');

        $pattern = '#'
            . 'Action  : KLEVU_PRODUCT::delete'
            . '\s*Batches : 0'
            . '\s*--'
            . '#';
        $matches = [];
        preg_match(
            pattern: $pattern,
            subject: $display,
            matches: $matches,
        );
        $this->assertCount(1, $matches, 'KLEVU_PRODUCT::delete batches');

        $pattern = '#'
            . 'Action  : KLEVU_PRODUCT::update'
            . '\s*Batches : 1'
            . '\s*Batch        : 0'
            . '\s*Success      : False'
            . '\s*API Response : .*'
            . '\s*Job ID       : n/a'
            . '\s*Record Count : 1'
            . '\s*There has been an ERROR'
            . '\s*--'
            . '#';
        $matches = [];
        preg_match(
            pattern: $pattern,
            subject: $display,
            matches: $matches,
        );
        $this->assertCount(1, $matches, 'KLEVU_PRODUCT::update batches');

        $this->assertStringContainsString(
            needle: 'All or part of Entity Sync Failed. See Logs for more details.',
            haystack: $display,
        );

        $matches = [];
        preg_match(
            pattern: '#Sync operations complete in .* seconds.#',
            subject: $display,
            matches: $matches,
        );
        $this->assertCount(1, $matches, 'Time taken is displayed');
    }

    /**
     * @magentoAppIsolation enabled
     * @magentoDbIsolation disabled
     */
    public function testExecute_Succeeds_ForSimpleProductTypes_AddAndUpdate(): void
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
            restAuthKey: 'SomeValidRestKey123',
        );

        $this->createProduct();
        $simpleProductFixture = $this->productFixturePool->get('test_product');

        $this->createProduct([
            'key' => 'test_virtual_product',
            'type_id' => Type::TYPE_VIRTUAL,
        ]);
        $virtualProductFixture = $this->productFixturePool->get('test_virtual_product');

        $this->cleanIndexingEntities(apiKey: $apiKey);
        $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ID => $simpleProductFixture->getId(),
            IndexingEntity::NEXT_ACTION => Actions::ADD,
        ]);
        $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ID => $virtualProductFixture->getId(),
            IndexingEntity::NEXT_ACTION => Actions::UPDATE,
        ]);

        $this->mockBatchServicePutApiCall(isCalled: true, isSuccessful: true);
        $this->mockBatchServiceDeleteApiCall(isCalled: false);

        $syncEntitiesCommand = $this->instantiateTestObject();
        $tester = new CommandTester(
            command: $syncEntitiesCommand,
        );
        $isFailure = $tester->execute(
            input: [
                '--entity-types' => 'KLEVU_PRODUCT',
                '--api-keys' => $apiKey,
            ],
            options: [
                'verbosity' => OutputInterface::VERBOSITY_DEBUG,
            ],
        );

        $this->assertSame(expected: Cli::RETURN_SUCCESS, actual: $isFailure, message: 'Entity Sync Successful');

        $display = $tester->getDisplay();
        $this->assertStringContainsString(
            needle: sprintf('Begin Entity Sync with filters: Entity Types = KLEVU_PRODUCT, API Keys = %s', $apiKey),
            haystack: $display,
        );
        $this->assertStringContainsString(
            needle: sprintf('Entity Sync for API Key: %s.', $apiKey),
            haystack: $display,
        );

        $pattern = '#'
            . 'Action  : KLEVU_PRODUCT::add'
            . '\s*Batches : 1'
            . '\s*Batch        : 0'
            . '\s*Success      : True'
            . '\s*API Response : 0'
            . '\s*Job ID       : n/a'
            . '\s*Record Count : 1'
            . '\s*Batch accepted successfully'
            . '\s*--'
            . '#';
        $matches = [];
        preg_match(
            pattern: $pattern,
            subject: $display,
            matches: $matches,
        );
        $this->assertCount(1, $matches, 'KLEVU_PRODUCT::add batches');

        $pattern = '#'
            . 'Action  : KLEVU_PRODUCT::delete'
            . '\s*Batches : 0'
            . '\s*--'
            . '#';
        $matches = [];
        preg_match(
            pattern: $pattern,
            subject: $display,
            matches: $matches,
        );
        $this->assertCount(1, $matches, 'KLEVU_PRODUCT::delete batches');

        $pattern = '#'
            . 'Action  : KLEVU_PRODUCT::update'
            . '\s*Batches : 1'
            . '\s*Batch        : 0'
            . '\s*Success      : True'
            . '\s*API Response : 0'
            . '\s*Job ID       : n/a'
            . '\s*Record Count : 1'
            . '\s*Batch accepted successfully'
            . '\s*--'
            . '#';
        $matches = [];
        preg_match(
            pattern: $pattern,
            subject: $display,
            matches: $matches,
        );
        $this->assertCount(1, $matches, 'KLEVU_PRODUCT::update batches');

        $this->assertStringContainsString(
            needle: 'Entity sync command completed successfully.',
            haystack: $display,
        );

        $matches = [];
        preg_match(
            pattern: '#Sync operations complete in .* seconds.#',
            subject: $display,
            matches: $matches,
        );
        $this->assertCount(1, $matches, 'Time taken is displayed');
    }

    /**
     * @magentoAppIsolation enabled
     * @magentoDbIsolation disabled
     */
    public function testExecute_Succeeds_ForSimpleProductTypes_Delete(): void
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
            restAuthKey: 'SomeValidRestKey123',
        );

        $this->createProduct();
        $simpleProductFixture = $this->productFixturePool->get('test_product');

        $this->createProduct([
            'key' => 'test_virtual_product',
            'type_id' => Type::TYPE_VIRTUAL,
        ]);
        $virtualProductFixture = $this->productFixturePool->get('test_virtual_product');

        $this->cleanIndexingEntities(apiKey: $apiKey);
        $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ID => $simpleProductFixture->getId(),
            IndexingEntity::NEXT_ACTION => Actions::DELETE,
        ]);
        $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ID => $virtualProductFixture->getId(),
            IndexingEntity::NEXT_ACTION => Actions::DELETE,
        ]);

        $this->mockBatchServicePutApiCall(isCalled: false);
        $this->mockBatchServiceDeleteApiCall(isCalled: true, isSuccessful: true);

        $syncEntitiesCommand = $this->instantiateTestObject();
        $tester = new CommandTester(
            command: $syncEntitiesCommand,
        );
        $isFailure = $tester->execute(
            input: [
                '--entity-types' => 'KLEVU_PRODUCT',
                '--api-keys' => $apiKey,
            ],
            options: [
                'verbosity' => OutputInterface::VERBOSITY_DEBUG,
            ],
        );

        $this->assertSame(expected: Cli::RETURN_SUCCESS, actual: $isFailure, message: 'Entity Sync Successful');

        $display = $tester->getDisplay();

        $this->assertStringContainsString(
            needle: sprintf('Begin Entity Sync with filters: Entity Types = KLEVU_PRODUCT, API Keys = %s', $apiKey),
            haystack: $display,
        );
        $this->assertStringContainsString(
            needle: sprintf('Entity Sync for API Key: %s.', $apiKey),
            haystack: $display,
        );

        $pattern = '#'
            . 'Action  : KLEVU_PRODUCT::add'
            . '\s*Batches : 0'
            . '\s*--'
            . '#';
        $matches = [];
        preg_match(
            pattern: $pattern,
            subject: $display,
            matches: $matches,
        );
        $this->assertCount(1, $matches, 'KLEVU_PRODUCT::add batches');

        $pattern = '#'
            . 'Action  : KLEVU_PRODUCT::delete'
            . '\s*Batches : 1'
            . '\s*Batch        : 0'
            . '\s*Success      : True'
            . '\s*API Response : 0'
            . '\s*Job ID       : n/a'
            . '\s*Record Count : 2'
            . '\s*Batch accepted successfully'
            . '\s*--'
            . '#';
        $matches = [];
        preg_match(
            pattern: $pattern,
            subject: $display,
            matches: $matches,
        );
        $this->assertCount(1, $matches, 'KLEVU_PRODUCT::delete batches');

        $pattern = '#'
            . 'Action  : KLEVU_PRODUCT::update'
            . '\s*Batches : 0'
            . '\s*--'
            . '#';
        $matches = [];
        preg_match(
            pattern: $pattern,
            subject: $display,
            matches: $matches,
        );
        $this->assertCount(1, $matches, 'KLEVU_PRODUCT::update batches');

        $this->assertStringContainsString(
            needle: 'Entity sync command completed successfully.',
            haystack: $display,
        );

        $matches = [];
        preg_match(
            pattern: '#Sync operations complete in .* seconds.#',
            subject: $display,
            matches: $matches,
        );
        $this->assertCount(1, $matches, 'Time taken is displayed');
    }
}
