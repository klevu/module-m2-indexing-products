<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Test\Integration\Plugin\Catalog\Model\ResourceModel;

use Klevu\Configuration\Service\Provider\ScopeProviderInterface;
use Klevu\Indexing\Model\IndexingAttribute;
use Klevu\Indexing\Test\Integration\Traits\IndexingAttributesTrait;
use Klevu\IndexingApi\Model\Source\Actions;
use Klevu\IndexingApi\Model\Source\IndexType;
use Klevu\IndexingProducts\Model\Source\Aspect;
use Klevu\IndexingProducts\Plugin\Catalog\Model\ResourceModel\TriggerAttributeUpdateOnAttributeSavePlugin;
use Klevu\TestFixtures\Catalog\Attribute\AttributeFixturePool;
use Klevu\TestFixtures\Catalog\AttributeTrait;
use Klevu\TestFixtures\Catalog\ProductTrait;
use Klevu\TestFixtures\Store\StoreFixturesPool;
use Klevu\TestFixtures\Store\StoreTrait;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\SetAuthKeysTrait;
use Magento\Eav\Api\Data\AttributeFrontendLabelInterface;
use Magento\Eav\Api\Data\AttributeFrontendLabelInterfaceFactory;
use Magento\Eav\Model\ResourceModel\Entity\Attribute as AttributeEavResourceModel;
use Magento\Framework\Interception\PluginList\PluginList;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * @covers TriggerAttributeUpdateOnAttributeSavePlugin::class
 * @method TriggerAttributeUpdateOnAttributeSavePlugin instantiateTestObject(?array $arguments = null)
 * @method TriggerAttributeUpdateOnAttributeSavePlugin instantiateTestObjectFromInterface(?array $arguments = null)
 */
class TriggerAttributeUpdateOnAttributeSavePluginTest extends TestCase
{
    use AttributeTrait;
    use IndexingAttributesTrait;
    use ObjectInstantiationTrait;
    use ProductTrait;
    use SetAuthKeysTrait;
    use StoreTrait;

    /**
     * @var string|null
     */
    private ?string $pluginName = 'Klevu_IndexingProducts::TriggerAttributeUpdateOnAttributeSavePlugin';
    /**
     * @var ObjectManagerInterface|null
     */
    private ?ObjectManagerInterface $objectManager = null; // @phpstan-ignore-line
    /**
     * @var AttributeEavResourceModel|null
     */
    private ?AttributeEavResourceModel $resourceModel = null;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->implementationFqcn = TriggerAttributeUpdateOnAttributeSavePlugin::class;
        $this->objectManager = Bootstrap::getObjectManager();
        $this->resourceModel = $this->objectManager->get(AttributeEavResourceModel::class);
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
        $this->assertSame(
            expected: TriggerAttributeUpdateOnAttributeSavePlugin::class,
            actual: $pluginInfo[$this->pluginName]['instance'],
        );
    }

    /**
     * @return mixed[]|null
     */
    private function getSystemConfigPluginInfo(): ?array
    {
        /** @var PluginList $pluginList */
        $pluginList = $this->objectManager->get(PluginList::class);

        return $pluginList->get(AttributeEavResourceModel::class, []);
    }

    public function testAttributeSave_UpdatesNextActionToUpdate_WhenIndexableAndAttributeHasChanged(): void
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
            'aspect' => Aspect::ATTRIBUTES,
        ]);
        $attributeFixture = $this->attributeFixturePool->get('test_attribute');
        $attribute = $attributeFixture->getAttribute();

        $this->cleanIndexingAttributes($apiKey);
        $this->createIndexingAttribute([
            IndexingAttribute::TARGET_ID => $attribute->getAttributeId(),
            IndexingAttribute::TARGET_CODE => $attribute->getAttributeCode(),
            IndexingAttribute::API_KEY => $apiKey,
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE => 'KLEVU_PRODUCT',
            IndexingAttribute::NEXT_ACTION => Actions::NO_ACTION,
            IndexingAttribute::LAST_ACTION => Actions::ADD,
            IndexingAttribute::LAST_ACTION_TIMESTAMP => date('Y-m-d H:i:s'),
            IndexingAttribute::IS_INDEXABLE => true,
        ]);
        $attribuiteLabelFactory = $this->objectManager->get(AttributeFrontendLabelInterfaceFactory::class);
        /** @var AttributeFrontendLabelInterface $attributeLabel */
        $attributeLabel = $attribuiteLabelFactory->create();
        $attributeLabel->setStoreId($storeFixture->getId());
        $attributeLabel->setLabel('Some New Label');
        $attribute->setFrontendLabels([$attributeLabel]);
        $this->resourceModel->save($attribute);// @phpstan-ignore-line

        $indexingAttribute = $this->getIndexingAttributeForAttribute(
            apiKey: $apiKey,
            attribute: $attributeFixture->getAttribute(),
            type: 'KLEVU_PRODUCT',
        );

        $this->assertSame(
            expected: Actions::UPDATE,
            actual: $indexingAttribute->getNextAction(),
            message: 'Expected ' . Actions::UPDATE->value . ', received ' . $indexingAttribute->getNextAction()->value,
        );
    }

    public function testAttributeSave_DoesNotUpdateNextAction_WhenAttributeHasNotChanged(): void
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
            'aspect' => Aspect::ATTRIBUTES,
        ]);
        $attributeFixture = $this->attributeFixturePool->get('test_attribute');
        $attribute = $attributeFixture->getAttribute();

        $this->cleanIndexingAttributes($apiKey);
        $this->createIndexingAttribute([
            IndexingAttribute::TARGET_ID => $attribute->getAttributeId(),
            IndexingAttribute::TARGET_CODE => $attribute->getAttributeCode(),
            IndexingAttribute::API_KEY => $apiKey,
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE => 'KLEVU_PRODUCT',
            IndexingAttribute::NEXT_ACTION => Actions::NO_ACTION,
            IndexingAttribute::LAST_ACTION => Actions::ADD,
            IndexingAttribute::LAST_ACTION_TIMESTAMP => date('Y-m-d H:i:s'),
            IndexingAttribute::IS_INDEXABLE => true,
        ]);

        $this->resourceModel->save($attribute); // @phpstan-ignore-line

        $indexingAttribute = $this->getIndexingAttributeForAttribute(
            apiKey: $apiKey,
            attribute: $attributeFixture->getAttribute(),
            type: 'KLEVU_PRODUCT',
        );

        $this->assertSame(
            expected: Actions::NO_ACTION,
            actual: $indexingAttribute->getNextAction(),
            message: 'Expected ' . Actions::NO_ACTION->value
            . ', received ' . $indexingAttribute->getNextAction()->value,
        );
    }

    public function testAttributeSave_UpdatesNextActionToDelete_WhenNotIndexable(): void
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
            'index_as' => IndexType::NO_INDEX,
            'aspect' => Aspect::ATTRIBUTES,
        ]);
        $attributeFixture = $this->attributeFixturePool->get('test_attribute');
        $attribute = $attributeFixture->getAttribute();

        $this->cleanIndexingAttributes($apiKey);
        $this->createIndexingAttribute([
            IndexingAttribute::TARGET_ID => $attribute->getAttributeId(),
            IndexingAttribute::TARGET_CODE => $attribute->getAttributeCode(),
            IndexingAttribute::API_KEY => $apiKey,
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE => 'KLEVU_PRODUCT',
            IndexingAttribute::NEXT_ACTION => Actions::NO_ACTION,
            IndexingAttribute::LAST_ACTION => Actions::ADD,
            IndexingAttribute::LAST_ACTION_TIMESTAMP => date('Y-m-d H:i:s'),
            IndexingAttribute::IS_INDEXABLE => true,
        ]);
        $attribute->setDefaultFrontendLabel($attribute->getDefaultFrontendLabel() . ' TEST');
        $this->resourceModel->save($attribute);// @phpstan-ignore-line

        $indexingAttribute = $this->getIndexingAttributeForAttribute(
            apiKey: $apiKey,
            attribute: $attributeFixture->getAttribute(),
            type: 'KLEVU_PRODUCT',
        );

        $this->assertSame(
            expected: Actions::DELETE,
            actual: $indexingAttribute->getNextAction(),
            message: 'Expected ' . Actions::DELETE->value . ', received ' . $indexingAttribute->getNextAction()->value,
        );
    }

    public function testAttributeSave_SetsNextActionToAdd_WhenIndexable(): void
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
            'aspect' => Aspect::ALL,
        ]);
        $attributeFixture = $this->attributeFixturePool->get('test_attribute');

        $indexingAttribute = $this->getIndexingAttributeForAttribute(
            apiKey: $apiKey,
            attribute: $attributeFixture->getAttribute(),
            type: 'KLEVU_PRODUCT',
        );

        $this->assertSame(
            expected: Actions::ADD,
            actual: $indexingAttribute->getNextAction(),
            message: 'Expected ' . Actions::ADD->value . ', received ' . $indexingAttribute->getNextAction()->value,
        );
    }
}
