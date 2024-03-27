<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Test\Integration\Plugin\Review\Model\ResourceModel\Rating\Option;

use Klevu\IndexingApi\Service\UpdateRatingServiceInterface;
use Klevu\IndexingProducts\Plugin\Review\Model\ResourceModel\Rating\Option\UpdateRatingAttributesOnAggregatePlugin;
use Klevu\TestFixtures\Catalog\ProductTrait;
use Klevu\TestFixtures\Store\StoreFixturesPool;
use Klevu\TestFixtures\Store\StoreTrait;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Interception\PluginList\PluginList;
use Magento\Framework\ObjectManagerInterface;
use Magento\Review\Model\ResourceModel\Rating\Option as RatingOptionResourceModel;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use TddWizard\Fixtures\Catalog\ProductFixturePool;

/**
 * @covers UpdateRatingAttributesOnAggregatePlugin
 * @method UpdateRatingAttributesOnAggregatePlugin instantiateTestObject(?array $arguments = null)
 * @method UpdateRatingAttributesOnAggregatePlugin instantiateTestObjectFromInterface(?array $arguments = null)
 */
class UpdateRatingAttributesOnAggregatePluginTest extends TestCase
{
    use ObjectInstantiationTrait;
    use ProductTrait;
    use StoreTrait;

    /**
     * @var ObjectManagerInterface|null
     */
    private ?ObjectManagerInterface $objectManager = null; // @phpstan-ignore-line
    /**
     * @var string|null
     */
    private ?string $pluginName = 'Klevu_IndexingProducts::ReviewResourceModelRatingOption';

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->implementationFqcn = UpdateRatingAttributesOnAggregatePlugin::class;
        $this->objectManager = Bootstrap::getObjectManager();
        $this->storeFixturesPool = $this->objectManager->get(StoreFixturesPool::class);
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
        $this->storeFixturesPool->rollback();
    }

    /**
     * @magentoAppArea global
     */
    public function testPlugin_InterceptsCallsToTheField_InGlobalScope(): void
    {
        $pluginInfo = $this->getSystemConfigPluginInfo();
        $this->assertArrayHasKey($this->pluginName, $pluginInfo);
        $this->assertSame(UpdateRatingAttributesOnAggregatePlugin::class, $pluginInfo[$this->pluginName]['instance']);
    }

    public function testExecute_LogsError_WhenProductNotFound(): void
    {
        $mockLogger = $this->getMockBuilder(LoggerInterface::class)
            ->getMock();
        $mockLogger->expects($this->once())
            ->method('error')
            ->with(
                'Method: {method}, Error: {message}',
                [
                    // phpcs:ignore Generic.Files.LineLength.TooLong
                    'method' => 'Klevu\IndexingProducts\Plugin\Review\Model\ResourceModel\Rating\Option\UpdateRatingAttributesOnAggregatePlugin::afterAggregateEntityByRatingId',
                    'message' => 'The product that was requested doesn\'t exist. Verify the product and try again.',
                ],
            );

        $mockRatingOptionResourceModel = $this->getMockBuilder(RatingOptionResourceModel::class)
            ->disableOriginalConstructor()
            ->getMock();

        $plugin = $this->instantiateTestObject([
            'logger' => $mockLogger,
        ]);
        $plugin->afterAggregateEntityByRatingId(
            subject: $mockRatingOptionResourceModel,
            result: null, // @phpstan-ignore-line expects void, null given
            ratingId: 1, // can be set to anything we do not use this argument
            entityPkValue: 99999999999999999,
        );
    }

    public function testExecute_CallsUpdateRecordService_WhenProductFound(): void
    {
        $this->createProduct();
        $productFixture = $this->productFixturePool->get('test_product');

        $productRepository = $this->objectManager->get(ProductRepositoryInterface::class);
        $product = $productRepository->getById($productFixture->getId());

        $mockLogger = $this->getMockBuilder(LoggerInterface::class)
            ->getMock();
        $mockLogger->expects($this->never())
            ->method('error');

        $mockRatingOptionResourceModel = $this->getMockBuilder(RatingOptionResourceModel::class)
            ->disableOriginalConstructor()
            ->getMock();

        $mockUpdateRating = $this->getMockBuilder(UpdateRatingServiceInterface::class)
            ->getMock();
        $mockUpdateRating->expects($this->once())
            ->method('execute')
            ->with($product);

        $plugin = $this->instantiateTestObject([
            'logger' => $mockLogger,
            'updateRatingService' => $mockUpdateRating,
        ]);
        $plugin->afterAggregateEntityByRatingId(
            subject: $mockRatingOptionResourceModel,
            result: null, // @phpstan-ignore-line expects void, null given
            ratingId: 1, // can be set to anything we do not use this argument
            entityPkValue: $productFixture->getId(),
        );
    }

    /**
     * @return mixed[]|null
     */
    private function getSystemConfigPluginInfo(): ?array
    {
        /** @var PluginList $pluginList */
        $pluginList = $this->objectManager->get(PluginList::class);

        return $pluginList->get(RatingOptionResourceModel::class, []);
    }
}
