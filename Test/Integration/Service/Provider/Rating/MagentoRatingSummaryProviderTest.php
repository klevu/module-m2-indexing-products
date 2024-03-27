<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Test\Integration\Service\Provider\Rating;

use Klevu\IndexingApi\Service\Provider\Rating\RatingSummaryProviderInterface;
use Klevu\IndexingProducts\Service\Provider\Rating\MagentoRatingSummaryProvider;
use Klevu\TestFixtures\Catalog\ProductTrait;
use Klevu\TestFixtures\Customer\CustomerTrait;
use Klevu\TestFixtures\Store\StoreFixturesPool;
use Klevu\TestFixtures\Store\StoreTrait;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Klevu\TestFixtures\Traits\TestInterfacePreferenceTrait;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
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
 * @covers MagentoRatingSummaryProvider
 * @method RatingSummaryProviderInterface instantiateTestObject(?array $arguments = null)
 * @method RatingSummaryProviderInterface instantiateTestObjectFromInterface(?array $arguments = null)
 */
class MagentoRatingSummaryProviderTest extends TestCase
{
    use CustomerTrait;
    use ObjectInstantiationTrait;
    use ProductTrait;
    use StoreTrait;
    use TestImplementsInterfaceTrait;
    use TestInterfacePreferenceTrait;

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

        $this->implementationFqcn = MagentoRatingSummaryProvider::class; // @phpstan-ignore-line
        $this->interfaceFqcn = RatingSummaryProviderInterface::class;
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
     * @magentoAppIsolation enabled
     * @magentoDbIsolation disabled
     */
    public function testGet_ThrowsException_WhenInvalidStoreIdProvided(): void
    {
        $this->markTestIncomplete(
            'Exception causes test to fail with Fatal error: due to uncaught exception,'
            . ' even though we are expecting that exception to be thrown.',
        );
        $this->expectException(NoSuchEntityException::class);
        $this->expectExceptionMessage('The store that was requested wasn\'t found. Verify the store and try again.');

        $this->createProduct();
        $productFixture = $this->productFixturePool->get('test_product');

        $provider = $this->instantiateTestObject([]);
        $provider->get($productFixture->getId(), 9999999999999);
    }

    /**
     * @magentoAppIsolation enabled
     * @magentoDbIsolation disabled
     */
    public function testGet_ReturnsRating_WhenNoRatingsExist(): void
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
    public function testGet_ReturnsRating_WhenProductHasPendingReview(): void
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
    public function testGet_ReturnsRating_WhenProductHasApprovedReview(): void
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

        $data = $result->getData();
        $this->assertArrayHasKey(key: 'entity_pk_value', array: $data);
        $this->assertSame(expected: $productFixture->getId(), actual: (int)$data['entity_pk_value']);

        $this->assertArrayHasKey(key: 'count', array: $data);
        $this->assertGreaterThan(expected: 0, actual: $data['count']);

        $this->assertArrayHasKey(key: 'sum', array: $data);
        $this->assertGreaterThan(expected: 0, actual: $data['sum']);

        $this->assertArrayHasKey(key: 'store_id', array: $data);
        $this->assertSame(expected: $storeFixture->getId(), actual: (int)$data['store_id']);

        $this->deleteReview($review);
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
