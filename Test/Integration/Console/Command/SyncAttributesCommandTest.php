<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Test\Integration\Console\Command;

use Klevu\Configuration\Service\Provider\ScopeProviderInterface;
use Klevu\Indexing\Console\Command\SyncAttributesCommand;
use Klevu\Indexing\Model\IndexingAttribute;
use Klevu\Indexing\Test\Integration\Traits\IndexingAttributesTrait;
use Klevu\IndexingApi\Model\Source\Actions;
use Klevu\PhpSDK\Service\Indexing\AttributesService;
use Klevu\TestFixtures\Catalog\Attribute\AttributeFixturePool;
use Klevu\TestFixtures\Catalog\AttributeTrait;
use Klevu\TestFixtures\Store\StoreFixturesPool;
use Klevu\TestFixtures\Store\StoreTrait;
use Klevu\TestFixtures\Traits\AttributeApiCallTrait;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\SetAuthKeysTrait;
use Magento\Catalog\Api\Data\CategoryAttributeInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @covers \Klevu\Indexing\Console\Command\SyncAttributesCommand::class
 * @method SyncAttributesCommand instantiateTestObject(?array $arguments = null)
 */
class SyncAttributesCommandTest extends TestCase
{
    use AttributeTrait;
    use IndexingAttributesTrait;
    use ObjectInstantiationTrait;
    use AttributeApiCallTrait;
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

        $this->implementationFqcn = SyncAttributesCommand::class;
        // newrelic-describe-commands globs onto Console commands
        $this->expectPlugins = true;

        $this->objectManager = Bootstrap::getObjectManager();
        $this->storeFixturesPool = $this->objectManager->get(StoreFixturesPool::class);
        $this->attributeFixturePool = $this->objectManager->get(AttributeFixturePool::class);
    }

    /**
     * @return void
     * @throws \Exception
     */
    protected function tearDown(): void
    {
        parent::tearDown();

        $this->objectManager->removeSharedInstance( // @phpstan-ignore-line
            className: AttributesService::class,
        );

        $this->attributeFixturePool->rollback();
        $this->storeFixturesPool->rollback();
    }

    /**
     * @magentoAppIsolation enabled
     */
    public function testExecute_Succeeds_WithApiKeyFilter(): void
    {
        $apiKey1 = 'klevu-js-api-key-1';
        $authKey1 = 'klevu-rest-auth-key-1';
        $apiKey2 = 'klevu-js-api-key-2';
        $authKey2 = 'klevu-rest-auth-key-2';

        $this->createStore([
            'key' => "test_store_1",
            'code' => 'klevu_test_store_1',
        ]);
        $storeFixture1 = $this->storeFixturesPool->get('test_store_1');
        $scopeProvider1 = $this->objectManager->get(ScopeProviderInterface::class);
        $scopeProvider1->setCurrentScope(scope: $storeFixture1->get());
        $this->setAuthKeys(
            scopeProvider: $scopeProvider1,
            jsApiKey: $apiKey1,
            restAuthKey: $authKey1,
        );
        $scopeProvider1->unsetCurrentScope();

        $this->createStore([
            'key' => "test_store_2",
            'code' => 'klevu_test_store_2',
        ]);
        $storeFixture2 = $this->storeFixturesPool->get('test_store_2');
        $scopeProvider2 = $this->objectManager->get(ScopeProviderInterface::class);
        $scopeProvider2->setCurrentScope(scope: $storeFixture2->get());
        $this->setAuthKeys(
            scopeProvider: $scopeProvider2,
            jsApiKey: $apiKey2,
            restAuthKey: $authKey2,
            removeApiKeys: false,
        );
        $scopeProvider2->unsetCurrentScope();

        $this->createAttribute([
            'key' => 'test_attribute_1',
            'code' => 'klevu_test_attribute_1',
        ]);
        $attributeFixture1 = $this->attributeFixturePool->get('test_attribute_1');
        $this->createAttribute([
            'key' => 'test_attribute_2',
            'code' => 'klevu_test_attribute_2',
        ]);
        $attributeFixture2 = $this->attributeFixturePool->get('test_attribute_2');

        $this->cleanIndexingAttributes($apiKey1);
        $this->cleanIndexingAttributes($apiKey2);
        $this->createIndexingAttribute([
            IndexingAttribute::TARGET_ID => $attributeFixture1->getAttributeId(),
            IndexingAttribute::TARGET_CODE => $attributeFixture1->getAttributeCode(),
            IndexingAttribute::API_KEY => $apiKey1,
            IndexingAttribute::NEXT_ACTION => Actions::ADD,
            IndexingAttribute::LAST_ACTION => Actions::NO_ACTION,
            IndexingAttribute::IS_INDEXABLE => true,
        ]);
        $this->createIndexingAttribute([
            IndexingAttribute::TARGET_ID => $attributeFixture2->getAttributeId(),
            IndexingAttribute::TARGET_CODE => $attributeFixture2->getAttributeCode(),
            IndexingAttribute::API_KEY => $apiKey2,
            IndexingAttribute::NEXT_ACTION => Actions::ADD,
            IndexingAttribute::LAST_ACTION => Actions::NO_ACTION,
            IndexingAttribute::IS_INDEXABLE => true,
        ]);

        $this->mockSdkAttributePutApiCall(
            isCalled: true,
            isSuccessful: true,
        );

        $syncAttributesCommand = $this->instantiateTestObject();

        $tester = new CommandTester(
            command: $syncAttributesCommand,
        );

        $isFailure = $tester->execute(
            input: [
                '--api-keys' => $apiKey1,
            ],
            options: [
                'verbosity' => OutputInterface::VERBOSITY_DEBUG,
            ],
        );

        $this->assertSame(expected: 0, actual: $isFailure, message: 'Sync Failed');
        $output = $tester->getDisplay();
        $this->assertStringContainsString(
            needle: 'Begin Attribute Sync',
            haystack: $output,
        );
        $this->assertStringContainsString(
            needle: sprintf('Attribute Sync for API Key: %s.', $apiKey1),
            haystack: $output,
        );
        $this->assertStringNotContainsString(
            needle: sprintf('Attribute Sync for API Key: %s.', $apiKey2),
            haystack: $output,
        );
        $this->assertStringContainsString(
            needle: 'Attribute Sync for Action: KLEVU_PRODUCT::add.',
            haystack: $output,
        );
        $this->assertStringContainsString(
            needle: sprintf(
                'Attribute Sync for Attribute: "%s" Completed Successfully.',
                $attributeFixture1->getAttributeCode(),
            ),
            haystack: $output,
        );
        $this->assertStringNotContainsString(
            needle: sprintf(
                'Attribute Sync for Attribute: "%s" Completed Successfully.',
                $attributeFixture2->getAttributeCode(),
            ),
            haystack: $output,
        );

        $this->assertStringContainsString(
            needle: 'Attribute Sync Completed Successfully.',
            haystack: $output,
        );

        $this->cleanIndexingAttributes($apiKey1);
        $this->cleanIndexingAttributes($apiKey2);
    }

    /**
     * @magentoAppIsolation enabled
     */
    public function testExecute_Succeeds_WithAttributeTypeFilter(): void
    {
        $apiKey = 'klevu-js-api-key';
        $authKey = 'klevu-rest-auth-key';

        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');
        $scopeProvider = $this->objectManager->get(ScopeProviderInterface::class);
        $scopeProvider->setCurrentScope(scope: $storeFixture->get());
        $this->setAuthKeys(
            scopeProvider: $scopeProvider,
            jsApiKey: $apiKey,
            restAuthKey: $authKey,
        );
        $scopeProvider->unsetCurrentScope();

        $this->createAttribute([
            'key' => 'test_attribute_1',
            'code' => 'klevu_test_attribute_1',
        ]);
        $attributeFixture1 = $this->attributeFixturePool->get('test_attribute_1');
        $this->createAttribute([
            'key' => 'test_attribute_2',
            'code' => 'klevu_test_attribute_2',
            'entity_type' => CategoryAttributeInterface::ENTITY_TYPE_CODE,
        ]);
        $attributeFixture2 = $this->attributeFixturePool->get('test_attribute_2');

        $this->cleanIndexingAttributes($apiKey);
        $this->createIndexingAttribute([
            IndexingAttribute::TARGET_ID => $attributeFixture1->getAttributeId(),
            IndexingAttribute::TARGET_CODE => $attributeFixture1->getAttributeCode(),
            IndexingAttribute::API_KEY => $apiKey,
            IndexingAttribute::NEXT_ACTION => Actions::UPDATE,
            IndexingAttribute::LAST_ACTION => Actions::NO_ACTION,
            IndexingAttribute::IS_INDEXABLE => true,
        ]);
        $this->createIndexingAttribute([
            IndexingAttribute::TARGET_ID => $attributeFixture2->getAttributeId(),
            IndexingAttribute::TARGET_CODE => $attributeFixture2->getAttributeCode(),
            IndexingAttribute::API_KEY => $apiKey,
            IndexingAttribute::NEXT_ACTION => Actions::UPDATE,
            IndexingAttribute::LAST_ACTION => Actions::NO_ACTION,
            IndexingAttribute::IS_INDEXABLE => true,
        ]);

        $this->mockSdkAttributePutApiCall(
            isCalled: true,
            isSuccessful: true,
        );

        $syncAttributesCommand = $this->instantiateTestObject();

        $tester = new CommandTester(
            command: $syncAttributesCommand,
        );

        $isFailure = $tester->execute(
            input: [
                '--attribute-types' => 'KLEVU_PRODUCT',
            ],
            options: [
                'verbosity' => OutputInterface::VERBOSITY_DEBUG,
            ],
        );

        $this->assertSame(expected: 0, actual: $isFailure, message: 'Sync Failed');
        $output = $tester->getDisplay();
        $this->assertStringContainsString(
            needle: 'Begin Attribute Sync',
            haystack: $output,
        );
        $this->assertStringContainsString(
            needle: sprintf('Attribute Sync for API Key: %s.', $apiKey),
            haystack: $output,
        );
        $this->assertStringContainsString(
            needle: 'Attribute Sync for Action: KLEVU_PRODUCT::update.',
            haystack: $output,
        );
        $this->assertStringNotContainsString(
            needle: 'Attribute Sync for Action: KLEVU_CATEGORY::update.',
            haystack: $output,
        );
        $this->assertStringContainsString(
            needle: sprintf(
                'Attribute Sync for Attribute: "%s" Completed Successfully.',
                $attributeFixture1->getAttributeCode(),
            ),
            haystack: $output,
        );
        $this->assertStringNotContainsString(
            needle: sprintf(
                'Attribute Sync for Attribute: "%s" Completed Successfully.',
                $attributeFixture2->getAttributeCode(),
            ),
            haystack: $output,
        );

        $this->assertStringContainsString(
            needle: 'Attribute Sync Completed Successfully.',
            haystack: $output,
        );

        $this->cleanIndexingAttributes($apiKey);
    }

    /**
     * @magentoAppIsolation enabled
     */
    public function testExecute_Failure_WithApiKeyFilter(): void
    {
        $apiKey1 = 'klevu-js-api-key-1';
        $authKey1 = 'klevu-rest-auth-key-1';
        $apiKey2 = 'klevu-js-api-key-2';
        $authKey2 = 'klevu-rest-auth-key-2';

        $this->createStore([
            'key' => "test_store_1",
            'code' => 'klevu_test_store_1',
        ]);
        $storeFixture1 = $this->storeFixturesPool->get('test_store_1');
        $scopeProvider1 = $this->objectManager->get(ScopeProviderInterface::class);
        $scopeProvider1->setCurrentScope(scope: $storeFixture1->get());
        $this->setAuthKeys(
            scopeProvider: $scopeProvider1,
            jsApiKey: $apiKey1,
            restAuthKey: $authKey1,
        );
        $scopeProvider1->unsetCurrentScope();

        $this->createStore([
            'key' => "test_store_2",
            'code' => 'klevu_test_store_2',
        ]);
        $storeFixture2 = $this->storeFixturesPool->get('test_store_2');
        $scopeProvider2 = $this->objectManager->get(ScopeProviderInterface::class);
        $scopeProvider2->setCurrentScope(scope: $storeFixture2->get());
        $this->setAuthKeys(
            scopeProvider: $scopeProvider2,
            jsApiKey: $apiKey2,
            restAuthKey: $authKey2,
            removeApiKeys: false,
        );
        $scopeProvider2->unsetCurrentScope();

        $this->createAttribute([
            'key' => 'test_attribute_1',
            'code' => 'klevu_test_attribute_1',
        ]);
        $attributeFixture1 = $this->attributeFixturePool->get('test_attribute_1');
        $this->createAttribute([
            'key' => 'test_attribute_2',
            'code' => 'klevu_test_attribute_2',
        ]);
        $attributeFixture2 = $this->attributeFixturePool->get('test_attribute_2');

        $this->cleanIndexingAttributes($apiKey1);
        $this->cleanIndexingAttributes($apiKey2);
        $this->createIndexingAttribute([
            IndexingAttribute::TARGET_ID => $attributeFixture1->getAttributeId(),
            IndexingAttribute::TARGET_CODE => $attributeFixture1->getAttributeCode(),
            IndexingAttribute::API_KEY => $apiKey1,
            IndexingAttribute::NEXT_ACTION => Actions::DELETE,
            IndexingAttribute::LAST_ACTION => Actions::NO_ACTION,
            IndexingAttribute::IS_INDEXABLE => true,
        ]);
        $this->createIndexingAttribute([
            IndexingAttribute::TARGET_ID => $attributeFixture2->getAttributeId(),
            IndexingAttribute::TARGET_CODE => $attributeFixture2->getAttributeCode(),
            IndexingAttribute::API_KEY => $apiKey2,
            IndexingAttribute::NEXT_ACTION => Actions::DELETE,
            IndexingAttribute::LAST_ACTION => Actions::NO_ACTION,
            IndexingAttribute::IS_INDEXABLE => true,
        ]);

        $this->mockSdkAttributeDeleteApiCall(
            isCalled: true,
            isSuccessful: false,
            message: 'Something went wrong',
        );
        $syncAttributesCommand = $this->instantiateTestObject();

        $tester = new CommandTester(
            command: $syncAttributesCommand,
        );

        $isFailure = $tester->execute(
            input: [
                '--api-keys' => $apiKey1,
            ],
            options: [
                'verbosity' => OutputInterface::VERBOSITY_DEBUG,
            ],
        );

        $this->assertSame(expected: 1, actual: $isFailure, message: 'Sync Failed');
        $output = $tester->getDisplay();
        $this->assertStringContainsString(
            needle: 'Begin Attribute Sync',
            haystack: $output,
        );
        $this->assertStringContainsString(
            needle: sprintf('Attribute Sync for API Key: %s.', $apiKey1),
            haystack: $output,
        );
        $this->assertStringNotContainsString(
            needle: sprintf('Attribute Sync for API Key: %s.', $apiKey2),
            haystack: $output,
        );
        $this->assertStringContainsString(
            needle: 'Attribute Sync for Action: KLEVU_PRODUCT::delete.',
            haystack: $output,
        );
        $this->assertStringContainsString(
            needle: sprintf(
                'Attribute Sync for Attribute: "%s" Failed. Errors: Something went wrong',
                $attributeFixture1->getAttributeCode(),
            ),
            haystack: $output,
        );
        $this->assertStringNotContainsString(
            needle: sprintf(
                'Attribute Sync for Attribute: "%s" Failed. Errors: Something went wrong',
                $attributeFixture2->getAttributeCode(),
            ),
            haystack: $output,
        );

        $this->assertStringContainsString(
            needle: 'All or part of Attribute Sync Failed. See Logs for more details.',
            haystack: $output,
        );

        $this->cleanIndexingAttributes($apiKey1);
        $this->cleanIndexingAttributes($apiKey2);
    }

    /**
     * @magentoAppIsolation enabled
     */
    public function testExecute_Succeeds_MultiStore(): void
    {
        $apiKey1 = 'klevu-js-api-key-1';
        $authKey1 = 'klevu-rest-auth-key-1';
        $apiKey2 = 'klevu-js-api-key-2';
        $authKey2 = 'klevu-rest-auth-key-2';

        $this->createStore([
            'key' => "test_store_1",
            'code' => 'klevu_test_store_1',
        ]);
        $storeFixture1 = $this->storeFixturesPool->get('test_store_1');
        $scopeProvider1 = $this->objectManager->get(ScopeProviderInterface::class);
        $scopeProvider1->setCurrentScope(scope: $storeFixture1->get());
        $this->setAuthKeys(
            scopeProvider: $scopeProvider1,
            jsApiKey: $apiKey1,
            restAuthKey: $authKey1,
        );
        $scopeProvider1->unsetCurrentScope();

        $this->createStore([
            'key' => "test_store_2",
            'code' => 'klevu_test_store_2',
        ]);
        $storeFixture2 = $this->storeFixturesPool->get('test_store_2');
        $scopeProvider2 = $this->objectManager->get(ScopeProviderInterface::class);
        $scopeProvider2->setCurrentScope(scope: $storeFixture2->get());
        $this->setAuthKeys(
            scopeProvider: $scopeProvider2,
            jsApiKey: $apiKey2,
            restAuthKey: $authKey2,
            removeApiKeys: false,
        );
        $scopeProvider2->unsetCurrentScope();

        $this->createAttribute([
            'key' => 'test_attribute_1',
            'code' => 'klevu_test_attribute_1',
        ]);
        $attributeFixture1 = $this->attributeFixturePool->get('test_attribute_1');
        $this->createAttribute([
            'key' => 'test_attribute_2',
            'code' => 'klevu_test_attribute_2',
        ]);
        $attributeFixture2 = $this->attributeFixturePool->get('test_attribute_2');
        $this->createAttribute([
            'key' => 'test_attribute_3',
            'code' => 'klevu_test_attribute_3',
        ]);
        $attributeFixture3 = $this->attributeFixturePool->get('test_attribute_3');
        $this->createAttribute([
            'key' => 'test_attribute_4',
            'code' => 'klevu_test_attribute_4',
        ]);
        $attributeFixture4 = $this->attributeFixturePool->get('test_attribute_4');

        $this->cleanIndexingAttributes($apiKey1);
        $this->cleanIndexingAttributes($apiKey2);
        $this->createIndexingAttribute([
            IndexingAttribute::TARGET_ID => $attributeFixture1->getAttributeId(),
            IndexingAttribute::TARGET_CODE => $attributeFixture1->getAttributeCode(),
            IndexingAttribute::API_KEY => $apiKey1,
            IndexingAttribute::NEXT_ACTION => Actions::DELETE,
            IndexingAttribute::LAST_ACTION => Actions::NO_ACTION,
            IndexingAttribute::IS_INDEXABLE => true,
        ]);
        $this->createIndexingAttribute([
            IndexingAttribute::TARGET_ID => $attributeFixture1->getAttributeId(),
            IndexingAttribute::TARGET_CODE => $attributeFixture1->getAttributeCode(),
            IndexingAttribute::API_KEY => $apiKey2,
            IndexingAttribute::NEXT_ACTION => Actions::UPDATE,
            IndexingAttribute::LAST_ACTION => Actions::NO_ACTION,
            IndexingAttribute::IS_INDEXABLE => true,
        ]);
        $this->createIndexingAttribute([
            IndexingAttribute::TARGET_ID => $attributeFixture2->getAttributeId(),
            IndexingAttribute::TARGET_CODE => $attributeFixture2->getAttributeCode(),
            IndexingAttribute::API_KEY => $apiKey1,
            IndexingAttribute::NEXT_ACTION => Actions::ADD,
            IndexingAttribute::LAST_ACTION => Actions::NO_ACTION,
            IndexingAttribute::IS_INDEXABLE => true,
        ]);
        $this->createIndexingAttribute([
            IndexingAttribute::TARGET_ID => $attributeFixture2->getAttributeId(),
            IndexingAttribute::API_KEY => $apiKey2,
            IndexingAttribute::NEXT_ACTION => Actions::UPDATE,
            IndexingAttribute::LAST_ACTION => Actions::NO_ACTION,
            IndexingAttribute::IS_INDEXABLE => true,
        ]);
        $this->createIndexingAttribute([
            IndexingAttribute::TARGET_ID => $attributeFixture3->getAttributeId(),
            IndexingAttribute::TARGET_CODE => $attributeFixture3->getAttributeCode(),
            IndexingAttribute::API_KEY => $apiKey1,
            IndexingAttribute::NEXT_ACTION => Actions::NO_ACTION,
            IndexingAttribute::LAST_ACTION => Actions::NO_ACTION,
            IndexingAttribute::IS_INDEXABLE => false,
        ]);
        $this->createIndexingAttribute([
            IndexingAttribute::TARGET_ID => $attributeFixture3->getAttributeId(),
            IndexingAttribute::TARGET_CODE => $attributeFixture3->getAttributeCode(),
            IndexingAttribute::API_KEY => $apiKey2,
            IndexingAttribute::NEXT_ACTION => Actions::ADD,
            IndexingAttribute::LAST_ACTION => Actions::NO_ACTION,
            IndexingAttribute::IS_INDEXABLE => true,
        ]);
        $this->createIndexingAttribute([
            IndexingAttribute::TARGET_ID => $attributeFixture4->getAttributeId(),
            IndexingAttribute::TARGET_CODE => $attributeFixture4->getAttributeCode(),
            IndexingAttribute::API_KEY => $apiKey1,
            IndexingAttribute::NEXT_ACTION => Actions::UPDATE,
            IndexingAttribute::LAST_ACTION => Actions::NO_ACTION,
            IndexingAttribute::IS_INDEXABLE => true,
        ]);
        $this->createIndexingAttribute([
            IndexingAttribute::TARGET_ID => $attributeFixture4->getAttributeId(),
            IndexingAttribute::TARGET_CODE => $attributeFixture4->getAttributeCode(),
            IndexingAttribute::API_KEY => $apiKey2,
            IndexingAttribute::NEXT_ACTION => Actions::ADD,
            IndexingAttribute::LAST_ACTION => Actions::NO_ACTION,
            IndexingAttribute::IS_INDEXABLE => false,
        ]);

        $this->mockSdkAttributeAllApiCall(
            isCalled: true,
            isSuccessful: true,
        );

        $syncAttributesCommand = $this->instantiateTestObject();

        $tester = new CommandTester(
            command: $syncAttributesCommand,
        );

        $isFailure = $tester->execute(
            input: [],
            options: [
                'verbosity' => OutputInterface::VERBOSITY_DEBUG,
            ],
        );

        $this->assertSame(expected: 0, actual: $isFailure, message: 'Sync Failed');
        $output = $tester->getDisplay();
        $this->assertStringContainsString(
            needle: 'Begin Attribute Sync',
            haystack: $output,
        );

        $patternApiKey1 = '#'
            . sprintf('Attribute Sync for API Key: %s.', $apiKey1)
            . '\s*'
            . 'Attribute Sync for Action: KLEVU_PRODUCT::delete\.'
            . '\s*'
            . sprintf(
                'Attribute Sync for Attribute: "%s" Completed Successfully\.',
                $attributeFixture1->getAttributeCode(),
            )
            . '\s*'
            . 'Attribute Sync for Action: KLEVU_PRODUCT::update\.'
            . '\s*'
            . sprintf(
                'Attribute Sync for Attribute: "%s" Completed Successfully\.',
                $attributeFixture4->getAttributeCode(),
            )
            . '\s*'
            . 'Attribute Sync for Action: KLEVU_PRODUCT::add\.'
            . '\s*'
            . sprintf(
                'Attribute Sync for Attribute: "%s" Completed Successfully\.',
                $attributeFixture2->getAttributeCode(),
            )
            . '#';

        $matches = [];
        preg_match(
            pattern: $patternApiKey1,
            subject: $output,
            matches: $matches,
        );
        $this->assertCount(
            expectedCount: 1,
            haystack: $matches,
            message: 'Output for API Key 1',
        );

        $patternApiKey2 = '#'
            . sprintf('Attribute Sync for API Key: %s.', $apiKey2)
            . '\s*'
            . 'Attribute Sync for Action: KLEVU_PRODUCT::update\.'
            . '\s*'
            . sprintf(
                'Attribute Sync for Attribute: "%s" Completed Successfully\.',
                $attributeFixture1->getAttributeCode(),
            )
            . '\s*'
            . sprintf(
                'Attribute Sync for Attribute: "%s" Completed Successfully\.',
                $attributeFixture2->getAttributeCode(),
            )
            . '\s*'
            . 'Attribute Sync for Action: KLEVU_PRODUCT::add\.'
            . '\s*'
            . sprintf(
                'Attribute Sync for Attribute: "%s" Completed Successfully\.',
                $attributeFixture3->getAttributeCode(),
            )
            . '#';

        $matches = [];
        preg_match(
            pattern: $patternApiKey2,
            subject: $output,
            matches: $matches,
        );
        $this->assertCount(
            expectedCount: 1,
            haystack: $matches,
            message: 'Output for API Key 2',
        );

        $this->assertStringContainsString(
            needle: 'Attribute Sync Completed Successfully.',
            haystack: $output,
        );

        $this->cleanIndexingAttributes($apiKey1);
        $this->cleanIndexingAttributes($apiKey2);
    }
}
