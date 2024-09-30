<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Test\Integration\Observer\Sync\Attribute;

use Klevu\Indexing\Model\IndexingAttribute;
use Klevu\Indexing\Observer\Sync\Attributes\DeleteAttributeObserver;
use Klevu\Indexing\Service\Provider\MagentoToKlevuAttributeMapperProvider;
use Klevu\Indexing\Test\Integration\Traits\IndexingAttributesTrait;
use Klevu\IndexingApi\Model\Source\Actions;
use Klevu\IndexingApi\Service\Action\UpdateIndexingAttributeActionsActionInterface;
use Klevu\IndexingApi\Service\Provider\MagentoToKlevuAttributeMapperProviderInterface;
use Klevu\IndexingProducts\Observer\Sync\Attributes\DeleteAttributeObserver as DeleteAttributeObserverVirtualType;
use Klevu\IndexingProducts\Service\Mapper\MagentoToKlevuAttributeMapper as MagentoToKlevuAttributeMapperVirtualType;
use Klevu\TestFixtures\Catalog\Attribute\AttributeFixturePool;
use Klevu\TestFixtures\Catalog\AttributeTrait;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Magento\Framework\Event;
use Magento\Framework\Event\ConfigInterface as EventConfig;
use Magento\Framework\Event\ManagerInterface as EventManager;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @covers DeleteAttributeObserver
 * @method ObserverInterface instantiateTestObject(?array $arguments = null)
 * @method ObserverInterface instantiateTestObjectFromInterface(?array $arguments = null)
 */
class DeleteAttributeObserverTest extends TestCase
{
    use AttributeTrait;
    use IndexingAttributesTrait;
    use ObjectInstantiationTrait;
    use TestImplementsInterfaceTrait;

    private const OBSERVER_NAME = 'Klevu_IndexingProducts_syncDeleteAttribute';
    private const EVENT_NAME = 'klevu_indexing_attributes_action_delete_after';

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

        $this->implementationFqcn = DeleteAttributeObserverVirtualType::class;
        $this->interfaceFqcn = ObserverInterface::class;
        $this->implementationForVirtualType = DeleteAttributeObserver::class;
        $this->objectManager = Bootstrap::getObjectManager();
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
    }

    public function testObserver_IsConfigured(): void
    {
        $observerConfig = $this->objectManager->create(type: EventConfig::class);
        $observers = $observerConfig->getObservers(eventName: self::EVENT_NAME);

        $this->assertArrayHasKey(key: self::OBSERVER_NAME, array: $observers);
        $this->assertSame(
            expected: ltrim(string: DeleteAttributeObserverVirtualType::class, characters: '\\'),
            actual: $observers[self::OBSERVER_NAME]['instance'],
        );
    }

    /**
     * @testWith ["api_key"]
     *           ["attribute_name"]
     */
    public function testExecute_DoesNothing_WhenMissingApiKeyOrAttributeName(string $unsetKey): void
    {
        $mockEvent = $this->getMockBuilder(Event::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockEvent->method('getData')
            ->willReturnCallback(callback: static function (string $key) use ($unsetKey): ?string {
                return ($key === $unsetKey)
                    ? null
                    : 'something';
            });

        /** @var Observer&MockObject $mockObserver */
        $mockObserver = $this->getMockBuilder(Observer::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockObserver->expects($this->once())
            ->method('getEvent')
            ->willReturn($mockEvent);

        $mockAction = $this->getMockBuilder(UpdateIndexingAttributeActionsActionInterface::class)
            ->getMock();
        $mockAction->expects($this->never())
            ->method('execute');

        $observer = $this->instantiateTestObject([
            'updateIndexingAttributeActionsAction' => $mockAction,
        ]);
        $observer->execute($mockObserver);
    }

    public function testExecute_SetsIndexingAttributeLastAction(): void
    {
        $apiKey = 'klevu-test-api-key';
        $this->cleanIndexingAttributes(apiKey: $apiKey);

        $this->createAttribute();
        $attributeFixture = $this->attributeFixturePool->get('test_attribute');

        $this->createIndexingAttribute([
            IndexingAttribute::API_KEY => $apiKey,
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE => 'KLEVU_PRODUCT',
            IndexingAttribute::TARGET_ID => (int)$attributeFixture->getAttributeId(),
            IndexingAttribute::TARGET_CODE => $attributeFixture->getAttributeCode(),
            IndexingAttribute::IS_INDEXABLE => true,
            IndexingAttribute::NEXT_ACTION => Actions::DELETE,
            IndexingAttribute::LAST_ACTION => Actions::ADD,
        ]);

        $productMapper = $this->objectManager->create(MagentoToKlevuAttributeMapperVirtualType::class, [
            'attributeMapping' => [
                $attributeFixture->getAttributeCode() => 'new_attribute_name',
            ],
        ]);
        $mapperProvider = $this->objectManager->create(
            type: MagentoToKlevuAttributeMapperProviderInterface::class,
            arguments: [
                'magentoToKlevuAttributeMappers' => [
                    'KLEVU_PRODUCT' => $productMapper,
                ],
            ],
        );

        $this->objectManager->addSharedInstance(
            $mapperProvider,
            MagentoToKlevuAttributeMapperProvider::class,
        );

        $this->dispatchEvent(
            apiKey: $apiKey,
            attributeName: 'new_attribute_name',
        );

        $updateIndexingAttribute = $this->getIndexingAttributeForAttribute(
            apiKey: $apiKey,
            attribute: $attributeFixture->getAttribute(),
            type: 'KLEVU_PRODUCT',
        );

        $this->assertSame(expected: Actions::NO_ACTION, actual: $updateIndexingAttribute->getNextAction());
        $this->assertSame(expected: Actions::DELETE, actual: $updateIndexingAttribute->getLastAction());
        $this->assertFalse(condition: $updateIndexingAttribute->getIsIndexable());
    }

    /**
     * @param string|null $apiKey
     * @param string|null $attributeName
     *
     * @return void
     */
    private function dispatchEvent(
        ?string $apiKey = null,
        ?string $attributeName = null,
    ): void {
        /** @var EventManager $eventManager */
        $eventManager = $this->objectManager->get(type: EventManager::class);
        $eventManager->dispatch(
            self::EVENT_NAME,
            [
                'api_key' => $apiKey,
                'attribute_name' => $attributeName,
                'attribute_type' => 'KLEVU_PRODUCT',
            ],
        );
    }
}
