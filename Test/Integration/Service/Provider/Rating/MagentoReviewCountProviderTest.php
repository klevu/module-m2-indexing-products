<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Test\Integration\Service\Provider\Rating;

use Klevu\IndexingApi\Service\Provider\Rating\ReviewCountProviderInterface;
use Klevu\IndexingProducts\Exception\InvalidRatingValue;
use Klevu\IndexingProducts\Service\Provider\Rating\MagentoReviewCountProvider;
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
use Magento\Review\Model\ReviewFactory;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use TddWizard\Fixtures\Catalog\ProductFixturePool;
use TddWizard\Fixtures\Customer\CustomerFixturePool;

/**
 * @covers MagentoReviewCountProvider
 * @method ReviewCountProviderInterface instantiateTestObject(?array $arguments = null)
 * @method ReviewCountProviderInterface instantiateTestObjectFromInterface(?array $arguments = null)
 */
class MagentoReviewCountProviderTest extends TestCase
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

        $this->implementationFqcn = MagentoReviewCountProvider::class; // @phpstan-ignore-line
        $this->interfaceFqcn = ReviewCountProviderInterface::class;
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
    public function testGet_ReturnsCount_WhenNoReviewsExist(): void
    {
        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');

        $this->createProduct();
        $productFixture = $this->productFixturePool->get('test_product');

        $provider = $this->instantiateTestObject([]);
        $result = $provider->get(productId: $productFixture->getId(), storeId: $storeFixture->getId());

        $this->assertSame(expected: 0, actual: $result);
    }

    /**
     * @magentoDbIsolation disabled
     */
    public function testGetReturnsCount_WhenProductHasPendingReview(): void
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

        $this->assertSame(expected: 0, actual: $result);

        $this->deleteReview($review);
    }

    /**
     * @magentoAppArea adminhtml
     * @magentoDbIsolation disabled
     */
    public function testGetReturnsCount_WhenProductHasApprovedReview(): void
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

        $this->assertSame(expected: 1, actual: $result);

        $this->deleteReview($review);
    }

    /**
     * @magentoDbIsolation disabled
     */
    public function testGetReturnsCount_WhenProductHasPendingReview_AndPedingReviewAreAllowed(): void
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

        $provider = $this->instantiateTestObject([
            'approvedOnly' => false,
        ]);
        $result = $provider->get(productId: $productFixture->getId(), storeId: $storeFixture->getId());

        $this->assertSame(expected: 1, actual: $result);

        $this->deleteReview($review);
    }

    /**
     * @magentoDbIsolation disabled
     */
    public function testGet_ThrowsException_WhenNegativeCountReturned(): void
    {
        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');

        $this->createProduct();
        $productFixture = $this->productFixturePool->get('test_product');

        $mockReview = $this->getMockBuilder(Review::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockReview->expects($this->once())
            ->method('getTotalReviews')
            ->with($productFixture->getId(), true, $storeFixture->getId())
            ->willReturn(-3);

        $mockReviewFactory = $this->getMockBuilder(ReviewFactory::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockReviewFactory->expects($this->once())
            ->method('create')
            ->willReturn($mockReview);

        $this->expectException(InvalidRatingValue::class);
        $this->expectExceptionMessage('Invalid review count returned. Expected non-negative integer, received -3');

        $provider = $this->instantiateTestObject([
            'reviewFactory' => $mockReviewFactory,
        ]);
        $provider->get(productId: $productFixture->getId(), storeId: $storeFixture->getId());
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
