<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Test\Integration\Service\Provider\Rating;

use Klevu\IndexingApi\Service\Provider\Rating\AverageRatingProviderInterface;
use Klevu\IndexingProducts\Exception\InvalidRatingValue;
use Klevu\IndexingProducts\Service\Provider\Rating\MagentoAverageRatingProvider;
use Klevu\TestFixtures\Catalog\ProductTrait;
use Klevu\TestFixtures\Customer\CustomerTrait;
use Klevu\TestFixtures\Store\StoreFixturesPool;
use Klevu\TestFixtures\Store\StoreTrait;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\Registry;
use Magento\Review\Model\Rating;
use Magento\Review\Model\RatingFactory;
use Magento\Review\Model\ResourceModel\Rating as RatingResourceModel;
use Magento\Review\Model\ResourceModel\Rating\Option\Collection as RatingOptionCollection;
use Magento\Review\Model\ResourceModel\Review as ReviewResourceModel;
use Magento\Review\Model\Review;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use TddWizard\Fixtures\Catalog\ProductFixturePool;
use TddWizard\Fixtures\Customer\CustomerFixturePool;

/**
 * @covers MagentoAverageRatingProvider
 * @method AverageRatingProviderInterface instantiateTestObject(?array $arguments = null)
 * @method AverageRatingProviderInterface instantiateTestObjectFromInterface(?array $arguments = null)
 */
class MagentoAverageRatingProviderTest extends TestCase
{
    use CustomerTrait;
    use ObjectInstantiationTrait;
    use ProductTrait;
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

        $this->implementationFqcn = MagentoAverageRatingProvider::class; // @phpstan-ignore-line
        $this->interfaceFqcn = AverageRatingProviderInterface::class;
        $this->objectManager = Bootstrap::getObjectManager();
        $this->customerFixturePool = $this->objectManager->get(CustomerFixturePool::class);
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
        $this->customerFixturePool->rollback();
    }

    /**
     * @magentoDbIsolation disabled
     */
    public function testGet_ReturnsNull_WhenNoReviewsExist(): void
    {
        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');

        $this->createProduct();
        $productFixture = $this->productFixturePool->get('test_product');

        $provider = $this->instantiateTestObject([]);
        $result = $provider->get(productId: $productFixture->getId(), storeId: $storeFixture->getId());

        $this->assertNull(actual: $result);
    }

    /**
     * @magentoDbIsolation disabled
     */
    public function testGet_ReturnsArray_WhenProductHasPendingReview(): void
    {
        $this->createCustomer();
        $customerFixture = $this->customerFixturePool->get('test_customer');
        $customer = $customerFixture->getCustomer();

        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');

        $this->createProduct();
        $productFixture = $this->productFixturePool->get('test_product');

        $review = $this->objectManager->get(Review::class);
        $review->setEntityPkValue($productFixture->getId());
        $review->setStatusId(Review::STATUS_PENDING);
        $review->setTitle('A review');
        $review->setDetail('This is a good thing');
        $review->setEntityId(
            $review->getEntityIdByCode(entityCode: Review::ENTITY_PRODUCT_CODE),
        );
        $review->setStoreId($storeFixture->getId());
        $review->setStores($storeFixture->getId());
        $review->setCustomerId($customer->getId());
        $review->setNickname($customer->getFirstname());

        // there is no review repository so falling back to resourceModel
        $reviewResourceModel = $this->objectManager->get(ReviewResourceModel::class);
        $reviewResourceModel->save($review);

        $ratingOptionCollection = $this->objectManager->get(RatingOptionCollection::class);
        $options = $ratingOptionCollection->getItems();

        foreach ($options as $option) {
            $ratingFactory = $this->objectManager->get(RatingFactory::class);
            /** @var Rating $rating */
            $rating = $ratingFactory->create();
            $rating->setRatingId($option->getData('rating_id'));
            $rating->setReviewId($review->getId());
            $rating->setStores([$storeFixture->getId()]);
            $rating->addOptionVote($option->getData('option_id'), $productFixture->getId());
            $ratingResourceModel = $this->objectManager->get(RatingResourceModel::class);
            $ratingResourceModel->save($rating);
        }
        $review->aggregate();

        $provider = $this->instantiateTestObject([]);
        $result = $provider->get(productId: $productFixture->getId(), storeId: $storeFixture->getId());

        $this->assertNull(actual: $result);

        $this->deleteReview($review);
    }

    /**
     * @magentoAppArea adminhtml
     * @magentoDbIsolation disabled
     */
    public function testGet_ReturnsScore_WhenProductHasApprovedReview(): void
    {
        $this->createCustomer();
        $customerFixture = $this->customerFixturePool->get('test_customer');
        $customer = $customerFixture->getCustomer();

        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');

        $this->createProduct();
        $productFixture = $this->productFixturePool->get('test_product');

        $review = $this->objectManager->get(Review::class);
        $review->setEntityPkValue($productFixture->getId());
        $review->setStatusId(Review::STATUS_APPROVED);
        $review->setTitle('A review');
        $review->setDetail('This is a good thing');
        $review->setEntityId(
            $review->getEntityIdByCode(entityCode: Review::ENTITY_PRODUCT_CODE),
        );
        $review->setStoreId($storeFixture->getId());
        $review->setStores($storeFixture->getId());
        $review->setCustomerId($customer->getId());
        $review->setNickname($customer->getFirstname());

        // there is no review repository so falling back to resourceModel
        $reviewResourceModel = $this->objectManager->get(ReviewResourceModel::class);
        $reviewResourceModel->save($review);

        $ratingOptionCollection = $this->objectManager->get(RatingOptionCollection::class);
        $options = $ratingOptionCollection->getItems();

        foreach ($options as $option) {
            if ((int)$option->getData('value') !== 5) {
                continue;
            }
            $ratingFactory = $this->objectManager->get(RatingFactory::class);
            /** @var Rating $rating */
            $rating = $ratingFactory->create();
            $rating->setRatingId($option->getData('rating_id'));
            $rating->setReviewId($review->getId());
            $rating->setStores([$storeFixture->getId()]);
            $rating->addOptionVote($option->getData('option_id'), $productFixture->getId());
            $ratingResourceModel = $this->objectManager->get(RatingResourceModel::class);
            $ratingResourceModel->save($rating);
        }
        $review->aggregate();

        $provider = $this->instantiateTestObject([]);
        $result = $provider->get(productId: $productFixture->getId(), storeId: $storeFixture->getId());

        $this->assertSame(expected: 100.00, actual: $result);

        $this->deleteReview($review);
    }

    /**
     * @magentoAppIsolation enabled
     * @magentoDbIsolation disabled
     * @testWith [-1]
     *           [0]
     *           ["string"]
     */
    public function testGet_ThrowsException_WhenRatingCountIsInvalid(mixed $invalidCount): void
    {
        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');

        $this->createProduct();
        $productFixture = $this->productFixturePool->get('test_product');

        $mockRatingSummary = $this->getMockBuilder(Rating::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockRatingSummary->method('getData')
            ->willReturnCallback(callback: static function (string $param) use ($invalidCount): mixed {
                switch ($param) {
                    case MagentoAverageRatingProvider::RATING_SUM:
                        $return = 1;
                        break;
                    case MagentoAverageRatingProvider::RATING_COUNT:
                        $return = $invalidCount;
                        break;
                    default:
                        $return = 0;
                }

                return $return;
            });

        $mockRating = $this->getMockBuilder(Rating::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockRating->method('getEntitySummary')
            ->with($productFixture->getId(), $storeFixture->getId())
            ->willReturn($mockRatingSummary);

        $mockRatingFactory = $this->getMockBuilder(RatingFactory::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockRatingFactory->method('create')
            ->willReturn($mockRating);

        $this->objectManager->addSharedInstance(
            instance: $mockRatingFactory,
            className: RatingFactory::class,
        );

        $this->expectException(InvalidRatingValue::class);
        $this->expectExceptionMessage(
            sprintf(
                'Invalid rating count returned. Expected positive numeric value or null, received %s. '
                . 'Product ID: %s and Store ID: %s',
                $invalidCount,
                $productFixture->getId(),
                $storeFixture->getId(),
            ),
        );

        $provider = $this->instantiateTestObject([]);
        $provider->get(productId: $productFixture->getId(), storeId: $storeFixture->getId());

        $this->objectManager->removeSharedInstance(
            className: RatingFactory::class,
        );
    }

    /**
     * @magentoAppIsolation enabled
     * @magentoDbIsolation disabled
     * @testWith [-1]
     *           ["string"]
     */
    public function testGet_ThrowsException_WhenRatingSumIsInvalid(mixed $invalidSum): void
    {
        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');

        $this->createProduct();
        $productFixture = $this->productFixturePool->get('test_product');

        $mockRatingSummary = $this->getMockBuilder(Rating::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockRatingSummary->method('getData')
            ->willReturnCallback(callback: static function (string $param) use ($invalidSum): mixed {
                switch ($param) {
                    case MagentoAverageRatingProvider::RATING_SUM:
                        $return = $invalidSum;
                        break;
                    case MagentoAverageRatingProvider::RATING_COUNT:
                        $return = 1;
                        break;
                    default:
                        $return = 0;
                }

                return $return;
            });

        $mockRating = $this->getMockBuilder(Rating::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockRating->expects($this->once())
            ->method('getEntitySummary')
            ->with($productFixture->getId(), $storeFixture->getId())
            ->willReturn($mockRatingSummary);

        $mockRatingFactory = $this->getMockBuilder(RatingFactory::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockRatingFactory->expects($this->once())
            ->method('create')
            ->willReturn($mockRating);

        $this->objectManager->addSharedInstance(
            instance: $mockRatingFactory,
            className: RatingFactory::class,
        );

        $this->expectException(InvalidRatingValue::class);
        $this->expectExceptionMessage(
            sprintf(
                'Invalid rating sum returned. Expected non-negative numeric value or null, received %s. '
                . 'Product ID: %s and Store ID: %s',
                $invalidSum,
                $productFixture->getId(),
                $storeFixture->getId(),
            ),
        );

        $provider = $this->instantiateTestObject([]);
        $provider->get(productId: $productFixture->getId(), storeId: $storeFixture->getId());

        $this->objectManager->removeSharedInstance(
            className: RatingFactory::class,
        );
    }

    /**
     * @param Review $review
     *
     * @return void
     * @throws LocalizedException
     */
    private function deleteReview(Review $review): void
    {
        $registry = $this->objectManager->get(Registry::class);
        $registry->unregister('isSecureArea');
        $registry->register(key: 'isSecureArea', value: true);

        $reviewResourceModel = $this->objectManager->get(ReviewResourceModel::class);
        $reviewResourceModel->delete($review);

        $registry->unregister('isSecureArea');
        $registry->register(key: 'isSecureArea', value: false);
    }
}
