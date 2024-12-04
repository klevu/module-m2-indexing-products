<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Test\Integration\Service;

use Klevu\Configuration\Service\Provider\ScopeProviderInterface;
use Klevu\Indexing\Exception\InvalidAccountCredentialsException;
use Klevu\Indexing\Model\IndexingAttribute;
use Klevu\Indexing\Service\AttributeIndexerService;
use Klevu\Indexing\Test\Integration\Traits\IndexingAttributesTrait;
use Klevu\IndexingApi\Model\Source\Actions;
use Klevu\IndexingApi\Service\AttributeIndexerServiceInterface;
use Klevu\IndexingProducts\Service\AttributeIndexerService\Add as AddAttributeIndexerServiceVirtualType;
use Klevu\PhpSDK\Model\AccountCredentials;
use Klevu\TestFixtures\Catalog\Attribute\AttributeFixturePool;
use Klevu\TestFixtures\Catalog\AttributeTrait;
use Klevu\TestFixtures\Store\StoreFixturesPool;
use Klevu\TestFixtures\Store\StoreTrait;
use Klevu\TestFixtures\Traits\AttributeApiCallTrait;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\SetAuthKeysTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Klevu\IndexingProducts\Service\AttributeIndexerService::class
 * @method AttributeIndexerServiceInterface instantiateTestObject(?array $arguments = null)
 * @method AttributeIndexerServiceInterface instantiateTestObjectFromInterface(?array $arguments = null)
 */
class AttributeIndexerServiceAddTest extends TestCase
{
    use AttributeApiCallTrait;
    use AttributeTrait;
    use IndexingAttributesTrait;
    use ObjectInstantiationTrait;
    use AttributeApiCallTrait;
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

        $this->implementationFqcn = AddAttributeIndexerServiceVirtualType::class; // @phpstan-ignore-line
        $this->interfaceFqcn = AttributeIndexerServiceInterface::class;
        $this->implementationForVirtualType = AttributeIndexerService::class;
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

        $this->attributeFixturePool->rollback();
        $this->storeFixturesPool->rollback();
    }

    /**
     * @magentoAppIsolation enabled
     */
    public function testExecute_CallsSdkPutAndUpdatesIndexingAttribute(): void
    {
        $apiKey1 = 'klevu-test-js-api-key-1';
        $apiKey2 = 'klevu-test-js-api-key-2';

        $this->createStore([
            'key' => 'test_store_1',
            'code' => 'klevu_test_store_1',
        ]);
        $storeFixture1 = $this->storeFixturesPool->get('test_store_1');
        $scopeProvider1 = $this->objectManager->get(ScopeProviderInterface::class);
        $scopeProvider1->setCurrentScope($storeFixture1->get());
        $this->setAuthKeys(
            scopeProvider: $scopeProvider1,
            jsApiKey: $apiKey1,
            restAuthKey: 'klevu-rest-key',
        );
        $this->createStore([
            'key' => 'test_store_2',
            'code' => 'klevu_test_store_2',
        ]);
        $storeFixture2 = $this->storeFixturesPool->get('test_store_2');
        $scopeProvider2 = $this->objectManager->get(ScopeProviderInterface::class);
        $scopeProvider2->setCurrentScope($storeFixture2->get());
        $this->setAuthKeys(
            scopeProvider: $scopeProvider2,
            jsApiKey: $apiKey2,
            restAuthKey: 'klevu-rest-key',
            removeApiKeys: false,
        );

        $this->createAttribute([
            'key' => 'test_attribute_1',
            'code' => 'klevu_test_attribute_1',
        ]);
        $attributeFixture = $this->attributeFixturePool->get('test_attribute_1');

        $this->createAttribute([
            'key' => 'test_attribute_2',
            'code' => 'klevu_test_attribute_2',
        ]);
        $attributeFixture2 = $this->attributeFixturePool->get('test_attribute_2');

        $this->cleanIndexingAttributes(apiKey: $apiKey1);
        $this->cleanIndexingAttributes(apiKey: $apiKey2);
        $this->createIndexingAttribute(data: [
            IndexingAttribute::TARGET_ID => $attributeFixture->getAttributeId(),
            IndexingAttribute::TARGET_CODE => $attributeFixture->getAttributeCode(),
            IndexingAttribute::API_KEY => $apiKey1,
            IndexingAttribute::NEXT_ACTION => Actions::ADD,
            IndexingAttribute::LAST_ACTION => Actions::NO_ACTION,
        ]);
        $this->createIndexingAttribute(data: [
            IndexingAttribute::TARGET_ID => $attributeFixture->getAttributeId(),
            IndexingAttribute::TARGET_CODE => $attributeFixture->getAttributeCode(),
            IndexingAttribute::API_KEY => $apiKey2,
            IndexingAttribute::NEXT_ACTION => Actions::ADD,
            IndexingAttribute::LAST_ACTION => Actions::NO_ACTION,
        ]);
        $this->createIndexingAttribute(data: [
            IndexingAttribute::TARGET_ID => $attributeFixture2->getAttributeId(),
            IndexingAttribute::TARGET_CODE => $attributeFixture2->getAttributeCode(),
            IndexingAttribute::API_KEY => $apiKey1,
            IndexingAttribute::NEXT_ACTION => Actions::UPDATE,
            IndexingAttribute::LAST_ACTION => Actions::NO_ACTION,
        ]);

        $accountCredentials = $this->objectManager->create(AccountCredentials::class, [
            'jsApiKey' => $apiKey1,
            'restAuthKey' => 'klevu-rest-auth-key',
        ]);

        $this->mockSdkAttributePutApiCall(
            isCalled: true,
            isSuccessful: true,
            message: 'Successfully added attribute klevu-test-attribute-1.',
        );

        $service = $this->instantiateTestObject();
        $responses = $service->execute($accountCredentials, 'KLEVU_PRODUCT');

        $this->assertCount(expectedCount: 1, haystack: $responses);
        $response = array_shift($responses);
        $this->assertTrue(condition: $response->isSuccess());
        $this->assertContains(
            needle: 'Successfully added attribute klevu-test-attribute-1.',
            haystack: $response->getMessages(),
        );

        $indexingAttribute1 = $this->getIndexingAttributeForAttribute(
            apiKey: $apiKey1,
            attribute: $attributeFixture->getAttribute(),
            type: 'KLEVU_PRODUCT',
        );
        $this->assertSame(expected: Actions::NO_ACTION, actual: $indexingAttribute1->getNextAction());
        $this->assertSame(expected: Actions::ADD, actual: $indexingAttribute1->getLastAction());
        $this->assertnotNull(actual: $indexingAttribute1->getLastActionTimestamp());

        $indexingAttribute2 = $this->getIndexingAttributeForAttribute(
            apiKey: $apiKey2,
            attribute: $attributeFixture->getAttribute(),
            type: 'KLEVU_PRODUCT',
        );
        $this->assertSame(expected: Actions::ADD, actual: $indexingAttribute2->getNextAction());
        $this->assertSame(expected: Actions::NO_ACTION, actual: $indexingAttribute2->getLastAction());
        $this->assertNull(actual: $indexingAttribute2->getLastActionTimestamp());

        $indexingAttribute3 = $this->getIndexingAttributeForAttribute(
            apiKey: $apiKey1,
            attribute: $attributeFixture2->getAttribute(),
            type: 'KLEVU_PRODUCT',
        );
        $this->assertSame(expected: Actions::UPDATE, actual: $indexingAttribute3->getNextAction());
        $this->assertSame(expected: Actions::NO_ACTION, actual: $indexingAttribute3->getLastAction());
        $this->assertNull(actual: $indexingAttribute3->getLastActionTimestamp());

        $this->cleanIndexingAttributes(apiKey: $apiKey1);
        $this->cleanIndexingAttributes(apiKey: $apiKey2);
    }

    /**
     * @magentoAppIsolation enabled
     */
    public function testExecute_ThrowsException_ForInvalidAccountCredentials(): void
    {
        $apiKey = 'klevu-test-js-api-key-1';

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
            'key' => 'test_attribute_1',
            'code' => 'klevu_test_attribute_1',
        ]);
        $attributeFixture = $this->attributeFixturePool->get('test_attribute_1');

        $this->cleanIndexingAttributes(apiKey: $apiKey);
        $this->createIndexingAttribute(data: [
            IndexingAttribute::TARGET_ID => $attributeFixture->getAttributeId(),
            IndexingAttribute::TARGET_CODE => $attributeFixture->getAttributeCode(),
            IndexingAttribute::API_KEY => $apiKey,
            IndexingAttribute::NEXT_ACTION => Actions::ADD,
            IndexingAttribute::LAST_ACTION => Actions::NO_ACTION,
        ]);

        $accountCredentials = $this->objectManager->create(AccountCredentials::class, [
            'jsApiKey' => $apiKey,
            'restAuthKey' => 'invalid-auth-key',
        ]);
        $this->expectException(InvalidAccountCredentialsException::class);
        $this->expectExceptionMessage(
            sprintf(
                'Invalid account credentials provided. '
                . 'Check the JS API Key (%s) and Rest Auth Key (%s).',
                $accountCredentials->jsApiKey,
                $accountCredentials->restAuthKey,
            ),
        );

        // call real SDK and let that throw the exception
        $this->removeSharedApiInstances();

        $service = $this->instantiateTestObject();
        $service->execute($accountCredentials, 'KLEVU_PRODUCT');
    }
}
