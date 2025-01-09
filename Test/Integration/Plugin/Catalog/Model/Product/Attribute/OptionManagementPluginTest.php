<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Test\Integration\Plugin\Catalog\Model\Product\Attribute;

use Klevu\Configuration\Service\Provider\ScopeProviderInterface;
use Klevu\Indexing\Model\IndexingAttribute;
use Klevu\Indexing\Test\Integration\Traits\IndexingAttributesTrait;
use Klevu\IndexingApi\Model\Source\Actions;
use Klevu\IndexingApi\Model\Source\IndexType;
use Klevu\IndexingProducts\Plugin\Catalog\Model\Product\Attribute\OptionManagementPlugin;
use Klevu\TestFixtures\Catalog\Attribute\AttributeFixturePool;
use Klevu\TestFixtures\Catalog\AttributeTrait;
use Klevu\TestFixtures\Store\StoreFixturesPool;
use Klevu\TestFixtures\Store\StoreTrait;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\SetAuthKeysTrait;
use Magento\Catalog\Api\Data\ProductAttributeInterface;
use Magento\Catalog\Api\ProductAttributeRepositoryInterface;
use Magento\Catalog\Model\Product\Attribute\OptionManagement as AttributeOptionManagement;
use Magento\Eav\Api\Data\AttributeInterface;
use Magento\Eav\Api\Data\AttributeOptionInterface;
use Magento\Eav\Api\Data\AttributeOptionInterfaceFactory;
use Magento\Eav\Model\Entity\Attribute\Source\TableFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Interception\PluginList\PluginList;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * @covers \Klevu\IndexingProducts\Plugin\Catalog\Model\Product\Attribute\OptionManagementPlugin::class
 * @method OptionManagementPlugin instantiateTestObject(?array $arguments = null)
 * @method OptionManagementPlugin instantiateTestObjectFromInterface(?array $arguments = null)
 */
class OptionManagementPluginTest extends TestCase
{
    use AttributeTrait;
    use IndexingAttributesTrait;
    use ObjectInstantiationTrait;
    use SetAuthKeysTrait;
    use StoreTrait;

    /**
     * @var ObjectManagerInterface|null
     */
    private ?ObjectManagerInterface $objectManager = null; // @phpstan-ignore-line
    /**
     * @var string|null
     */
    private ?string $pluginName = 'Klevu_IndexingProducts::ProductAttributeOptionManagementPlugin';

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->implementationFqcn = OptionManagementPlugin::class;
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
     * @magentoAppArea global
     */
    public function testPlugin_InterceptsCallsToTheField_InGlobalScope(): void
    {
        $pluginInfo = $this->getSystemConfigPluginInfo();
        $this->assertArrayHasKey($this->pluginName, $pluginInfo);
        $this->assertSame(OptionManagementPlugin::class, $pluginInfo[$this->pluginName]['instance']);
    }

    /**
     * @magentoAppIsolation enabled
     * @magentoDbIsolation disabled
     */
    public function testAfterAdd_UpdatesIndexingEntities(): void
    {
        $apiKey = 'klevu-js-api-key';
        $this->cleanIndexingAttributes($apiKey);

        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');
        $scopeProvider = $this->objectManager->get(ScopeProviderInterface::class);
        $scopeProvider->setCurrentScope($storeFixture->get());
        $this->setAuthKeys(
            scopeProvider: $scopeProvider,
            jsApiKey: 'klevu-js-api-key',
            restAuthKey: 'klevu-rest-auth-key',
        );

        $this->createAttribute([
            'attribute_type' => 'configurable',
            'index_as' => IndexType::INDEX,
        ]);
        $attributeFixture = $this->attributeFixturePool->get('test_attribute');
        /** @var ProductAttributeInterface $attribute */
        $attribute = $attributeFixture->getAttribute();

        $this->cleanIndexingAttributes($apiKey);

        $this->createIndexingAttribute([
            IndexingAttribute::TARGET_ID => (int)$attribute->getAttributeId(),
            IndexingAttribute::TARGET_CODE => $attribute->getAttributeCode(),
            IndexingAttribute::API_KEY => $apiKey,
            IndexingAttribute::NEXT_ACTION => Actions::NO_ACTION,
            IndexingAttribute::LAST_ACTION => Actions::ADD,
            IndexingAttribute::IS_INDEXABLE => true,
        ]);

        $optionFactory = $this->objectManager->get(AttributeOptionInterfaceFactory::class);
        /** @var AttributeOptionInterface $option */
        $option = $optionFactory->create();
        $option->setValue('999');
        $option->setLabel('TEST');
        $option->setSortOrder(1);
        $option->setIsDefault(true);

        $optionManagement = $this->objectManager->get(AttributeOptionManagement::class);
        $optionManagement->add(
            attributeCode: $attribute->getAttributeCode(),
            option: $option,
        );

        $indexingAttribute = $this->getIndexingAttributeForAttribute(
            apiKey: $apiKey,
            attribute: $attribute,
            type: 'KLEVU_PRODUCT',
        );

        $this->assertNotNull($indexingAttribute);
        $this->assertTrue(condition: $indexingAttribute->getIsIndexable());
        $this->assertSame(
            expected: Actions::UPDATE,
            actual: $indexingAttribute->getNextAction(),
            message: 'Expected ' . Actions::UPDATE->value . ', received ' . $indexingAttribute->getNextAction()->value,
        );

        $this->cleanIndexingAttributes($apiKey);
    }

    public function testAfterAdd_LogsError_ForNonExistentAttributeCode(): void
    {
        $attributeCode = 'some_code';

        $exceptionMessage = __(
            'The attribute with a "%1" attributeCode doesn\'t exist. Verify the attribute and try again.',
            $attributeCode,
        );

        $mockProductAttributeRepository = $this->getMockBuilder(ProductAttributeRepositoryInterface::class)
            ->getMock();
        $mockProductAttributeRepository->expects($this->once())
            ->method('get')
            ->willThrowException(new NoSuchEntityException($exceptionMessage));

        $mockLogger = $this->getMockBuilder(LoggerInterface::class)
            ->getMock();
        $mockLogger->expects($this->once())
            ->method('error')
            ->with(
                'Method: {method}, Error: {message}',
                [
                    // phpcs:ignore Generic.Files.LineLength.TooLong
                    'method' => 'Klevu\IndexingProducts\Plugin\Catalog\Model\Product\Attribute\OptionManagementPlugin::afterAdd',
                    'message' => $exceptionMessage->render(),
                ],
            );

        $optionFactory = $this->objectManager->get(AttributeOptionInterfaceFactory::class);
        /** @var AttributeOptionInterface $option */
        $option = $optionFactory->create();
        $option->setValue('999');
        $option->setLabel('TEST');
        $option->setSortOrder(1);
        $option->setIsDefault(true);

        $optionManagement = $this->objectManager->get(AttributeOptionManagement::class);

        $plugin = $this->instantiateTestObject([
            'repository' => $mockProductAttributeRepository,
            'logger' => $mockLogger,
        ]);
        $plugin->afterAdd(
            subject: $optionManagement,
            result: '1234',
            attributeCode: $attributeCode,
            option: $option,
        );
    }

    /**
     * @magentoAppIsolation enabled
     * @magentoDbIsolation disabled
     * @magentoConfigFixture klevu_test_store_1_store klevu_configuration/auth_keys/js_api_key klevu-js-api-key
     * @magentoConfigFixture klevu_test_store_1_store klevu_configuration/auth_keys/rest_auth_key klevu-rest-auth-key
     */
    public function testAfterDelete_UpdatesIndexingEntities(): void
    {
        $apiKey = 'klevu-js-api-key';
        $this->cleanIndexingAttributes($apiKey);

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
            'attribute_type' => 'configurable',
            'index_as' => IndexType::INDEX,
        ]);
        $attributeFixture = $this->attributeFixturePool->get('test_attribute');
        /** @var ProductAttributeInterface $attribute */
        $attribute = $attributeFixture->getAttribute();

        $this->cleanIndexingAttributes($apiKey);

        $this->createIndexingAttribute([
            IndexingAttribute::TARGET_ID => (int)$attribute->getAttributeId(),
            IndexingAttribute::TARGET_CODE => $attribute->getAttributeCode(),
            IndexingAttribute::API_KEY => $apiKey,
            IndexingAttribute::NEXT_ACTION => Actions::NO_ACTION,
            IndexingAttribute::LAST_ACTION => Actions::ADD,
            IndexingAttribute::IS_INDEXABLE => true,
        ]);

        $options = $attribute->getOptions();
        $option = array_shift($options);
        $optionId = $this->getOptionId(attribute: $attribute, requiredOption: $option);

        $optionManagement = $this->objectManager->get(AttributeOptionManagement::class);
        $optionManagement->delete(
            attributeCode: $attribute->getAttributeCode(),
            optionId: $optionId,
        );

        $indexingAttribute = $this->getIndexingAttributeForAttribute(
            apiKey: $apiKey,
            attribute: $attribute,
            type: 'KLEVU_PRODUCT',
        );

        $this->assertNotNull($indexingAttribute);
        $this->assertTrue(condition: $indexingAttribute->getIsIndexable());
        $this->assertSame(
            expected: Actions::UPDATE,
            actual: $indexingAttribute->getNextAction(),
            message: 'Expected ' . Actions::UPDATE->value . ', received ' . $indexingAttribute->getNextAction()->value,
        );

        $this->cleanIndexingAttributes($apiKey);
    }

    public function testAfterDelete_LogsError_ForNonExistentAttributeCode(): void
    {
        $attributeCode = 'some_code';

        $exceptionMessage = __(
            'The attribute with a "%1" attributeCode doesn\'t exist. Verify the attribute and try again.',
            $attributeCode,
        );

        $mockProductAttributeRepository = $this->getMockBuilder(ProductAttributeRepositoryInterface::class)
            ->getMock();
        $mockProductAttributeRepository->expects($this->once())
            ->method('get')
            ->willThrowException(new NoSuchEntityException($exceptionMessage));

        $mockLogger = $this->getMockBuilder(LoggerInterface::class)
            ->getMock();
        $mockLogger->expects($this->once())
            ->method('error')
            ->with(
                'Method: {method}, Error: {message}',
                [
                    // phpcs:ignore Generic.Files.LineLength.TooLong
                    'method' => 'Klevu\IndexingProducts\Plugin\Catalog\Model\Product\Attribute\OptionManagementPlugin::afterDelete',
                    'message' => $exceptionMessage->render(),
                ],
            );

        $optionFactory = $this->objectManager->get(AttributeOptionInterfaceFactory::class);
        /** @var AttributeOptionInterface $option */
        $option = $optionFactory->create();
        $option->setValue('999');
        $option->setLabel('TEST');
        $option->setSortOrder(1);
        $option->setIsDefault(true);

        $optionManagement = $this->objectManager->get(AttributeOptionManagement::class);

        $plugin = $this->instantiateTestObject([
            'repository' => $mockProductAttributeRepository,
            'logger' => $mockLogger,
        ]);
        $plugin->afterDelete(
            subject: $optionManagement,
            result: true,
            attributeCode: $attributeCode,
            optionId: '1234',
        );
    }

    /**
     * @magentoAppIsolation enabled
     * @magentoDbIsolation disabled
     * @magentoConfigFixture klevu_test_store_1_store klevu_configuration/auth_keys/js_api_key klevu-js-api-key
     * @magentoConfigFixture klevu_test_store_1_store klevu_configuration/auth_keys/rest_auth_key klevu-rest-auth-key
     */
    public function testAfterUpdate_UpdatesIndexingEntities(): void
    {
        $apiKey = 'klevu-js-api-key';
        $this->cleanIndexingAttributes($apiKey);

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
            'attribute_type' => 'configurable',
            'index_as' => IndexType::INDEX,
        ]);
        $attributeFixture = $this->attributeFixturePool->get('test_attribute');
        /** @var ProductAttributeInterface $attribute */
        $attribute = $attributeFixture->getAttribute();

        $this->cleanIndexingAttributes($apiKey);

        $this->createIndexingAttribute([
            IndexingAttribute::TARGET_ID => (int)$attribute->getAttributeId(),
            IndexingAttribute::TARGET_CODE => $attribute->getAttributeCode(),
            IndexingAttribute::API_KEY => $apiKey,
            IndexingAttribute::NEXT_ACTION => Actions::NO_ACTION,
            IndexingAttribute::LAST_ACTION => Actions::ADD,
            IndexingAttribute::IS_INDEXABLE => true,
        ]);

        $options = $attribute->getOptions();
        $option = array_shift($options);
        $optionId = $this->getOptionId(attribute: $attribute, requiredOption: $option);

        $optionManagement = $this->objectManager->get(AttributeOptionManagement::class);
        $optionManagement->update(
            attributeCode: $attribute->getAttributeCode(),
            optionId: $optionId,
            option: $option,
        );

        $indexingAttribute = $this->getIndexingAttributeForAttribute(
            apiKey: $apiKey,
            attribute: $attribute,
            type: 'KLEVU_PRODUCT',
        );

        $this->assertNotNull($indexingAttribute);
        $this->assertTrue(condition: $indexingAttribute->getIsIndexable());
        $this->assertSame(
            expected: Actions::UPDATE,
            actual: $indexingAttribute->getNextAction(),
            message: 'Expected ' . Actions::UPDATE->value . ', received ' . $indexingAttribute->getNextAction()->value,
        );

        $this->cleanIndexingAttributes($apiKey);
    }

    public function testAfterUpdate_LogsError_ForNonExistentAttributeCode(): void
    {
        $attributeCode = 'some_code';

        $exceptionMessage = __(
            'The attribute with a "%1" attributeCode doesn\'t exist. Verify the attribute and try again.',
            $attributeCode,
        );

        $mockProductAttributeRepository = $this->getMockBuilder(ProductAttributeRepositoryInterface::class)
            ->getMock();
        $mockProductAttributeRepository->expects($this->once())
            ->method('get')
            ->willThrowException(new NoSuchEntityException($exceptionMessage));

        $mockLogger = $this->getMockBuilder(LoggerInterface::class)
            ->getMock();
        $mockLogger->expects($this->once())
            ->method('error')
            ->with(
                'Method: {method}, Error: {message}',
                [
                    // phpcs:ignore Generic.Files.LineLength.TooLong
                    'method' => 'Klevu\IndexingProducts\Plugin\Catalog\Model\Product\Attribute\OptionManagementPlugin::afterUpdate',
                    'message' => $exceptionMessage->render(),
                ],
            );

        $optionFactory = $this->objectManager->get(AttributeOptionInterfaceFactory::class);
        /** @var AttributeOptionInterface $option */
        $option = $optionFactory->create();
        $option->setValue('999');
        $option->setLabel('TEST');
        $option->setSortOrder(1);
        $option->setIsDefault(true);

        $optionManagement = $this->objectManager->get(AttributeOptionManagement::class);

        $plugin = $this->instantiateTestObject([
            'repository' => $mockProductAttributeRepository,
            'logger' => $mockLogger,
        ]);
        $plugin->afterUpdate(
            subject: $optionManagement,
            result: true,
            attributeCode: $attributeCode,
            optionId: 1234,
            option: $option,
        );
    }

    /**
     * @param AttributeInterface $attribute
     * @param AttributeOptionInterface $requiredOption
     *
     * @return int
     */
    private function getOptionId(AttributeInterface $attribute, AttributeOptionInterface $requiredOption): int
    {
        // $attribute->getOptions() does not return the optionId, so we need to retrieve it
        // We have to generate a new sourceModel instance each time through to prevent it from
        // referencing its _options cache. No other way to get it to pick up newly-added values.
        // adapted from \TddWizard\Fixtures\Catalog\OptionBuilder::getOptionId
        $tableFactory = $this->objectManager->get(TableFactory::class);
        $sourceModel = $tableFactory->create();
        $sourceModel->setAttribute($attribute);
        $filteredOption = array_filter(
            array: $sourceModel->getAllOptions(),
            callback: static fn (array $option): bool => ($option['label'] === $requiredOption['label']),
        );
        $option = array_shift($filteredOption);
        if ($option['value'] ?? null) {
            return (int)$option['value'];
        }

        throw new \RuntimeException('Error retrieving option');
    }

    /**
     * @return mixed[]|null
     */
    private function getSystemConfigPluginInfo(): ?array
    {
        /** @var PluginList $pluginList */
        $pluginList = $this->objectManager->get(PluginList::class);

        return $pluginList->get(AttributeOptionManagement::class, []);
    }
}
