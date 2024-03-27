<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Test\Integration\Service;

use Klevu\IndexingApi\Service\Provider\Rating\RatingProviderInterface;
use Klevu\IndexingApi\Service\UpdateRatingServiceInterface;
use Klevu\IndexingProducts\Exception\InvalidRatingValue;
use Klevu\IndexingProducts\Exception\KlevuProductAttributeMissingException;
use Klevu\IndexingProducts\Model\Attribute\KlevuRatingCountInterface;
use Klevu\IndexingProducts\Model\Attribute\KlevuRatingInterface;
use Klevu\IndexingProducts\Service\UpdateRatingService;
use Klevu\TestFixtures\Catalog\ProductTrait;
use Klevu\TestFixtures\Customer\CustomerTrait;
use Klevu\TestFixtures\Store\StoreFixturesPool;
use Klevu\TestFixtures\Store\StoreTrait;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Klevu\TestFixtures\Traits\TestInterfacePreferenceTrait;
use Magento\Catalog\Api\Data\ProductAttributeInterface;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Eav\Api\AttributeRepositoryInterface;
use Magento\Eav\Api\Data\AttributeInterface;
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
use Psr\Log\LoggerInterface;
use TddWizard\Fixtures\Catalog\ProductFixturePool;
use TddWizard\Fixtures\Customer\CustomerFixturePool;

/**
 * @covers UpdateRatingService
 * @method UpdateRatingServiceInterface instantiateTestObject(?array $arguments = null)
 * @method UpdateRatingServiceInterface instantiateTestObjectFromInterface(?array $arguments = null)
 */
class UpdateRatingServiceTest extends TestCase
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

        $this->implementationFqcn = UpdateRatingService::class;
        $this->interfaceFqcn = UpdateRatingServiceInterface::class;
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

    public function testExecute_ThrowsException_WhenKlevuRatingAttributeDoesNotExist(): void
    {
        $this->expectException(KlevuProductAttributeMissingException::class);
        $this->expectExceptionMessage(
            sprintf(
                'The attribute with a "%s" attributeCode doesn\'t exist. Verify the attribute and try again.',
                KlevuRatingInterface::ATTRIBUTE_CODE,
            ),
        );

        $this->createProduct();
        $productFixture = $this->productFixturePool->get('test_product');

        $matcher = $this->exactly(2);
        $mockEavAttribute = $this->getMockBuilder(AttributeRepositoryInterface::class)
            ->getMock();
        $mockEavAttribute->expects($matcher)
            ->method('get')
            ->withConsecutive(
                [
                    ProductAttributeInterface::ENTITY_TYPE_CODE,
                    KlevuRatingInterface::ATTRIBUTE_CODE,
                ],
                [
                    ProductAttributeInterface::ENTITY_TYPE_CODE,
                    KlevuRatingCountInterface::ATTRIBUTE_CODE,
                ],
            )
            ->willReturnCallback(callback: function () use ($matcher): AttributeInterface {
                if ($matcher->getInvocationCount() === 1) {
                    throw new NoSuchEntityException(
                        __(
                            'The attribute with a "%1" attributeCode doesn\'t exist.'
                            . ' Verify the attribute and try again.',
                            KlevuRatingInterface::ATTRIBUTE_CODE,
                        ),
                    );
                }
                $attributeRepository = $this->objectManager->create(AttributeRepositoryInterface::class);

                return $attributeRepository->get(
                    entityTypeCode: ProductAttributeInterface::ENTITY_TYPE_CODE,
                    attributeCode: KlevuRatingCountInterface::ATTRIBUTE_CODE,
                );
            });

        $this->objectManager->addSharedInstance(
            instance: $mockEavAttribute,
            className: AttributeRepositoryInterface::class,
            forPreference: true,
        );

        $service = $this->instantiateTestObject();
        $service->execute($productFixture->getProduct());

        $this->objectManager->removeSharedInstance(
            className: AttributeRepositoryInterface::class,
            forPreference: true,
        );
    }

    /**
     * @magentoAppIsolation enabled
     */
    public function testExecute_ThrowsException_WhenKlevuRatingCountAttributeDoesNotExist(): void
    {
        $this->expectException(KlevuProductAttributeMissingException::class);
        $this->expectExceptionMessage(
            sprintf(
                'The attribute with a "%s" attributeCode doesn\'t exist. Verify the attribute and try again.',
                KlevuRatingCountInterface::ATTRIBUTE_CODE,
            ),
        );

        $this->createProduct();
        $productFixture = $this->productFixturePool->get('test_product');

        $matcher = $this->exactly(2);
        $mockEavAttribute = $this->getMockBuilder(AttributeRepositoryInterface::class)
            ->getMock();
        $mockEavAttribute->expects($matcher)
            ->method('get')
            ->withConsecutive(
                [
                    ProductAttributeInterface::ENTITY_TYPE_CODE,
                    KlevuRatingInterface::ATTRIBUTE_CODE,
                ],
                [
                    ProductAttributeInterface::ENTITY_TYPE_CODE,
                    KlevuRatingCountInterface::ATTRIBUTE_CODE,
                ],
            )
            ->willReturnCallback(callback: function () use ($matcher): AttributeInterface {
                if ($matcher->getInvocationCount() === 2) {
                    throw new NoSuchEntityException(
                        __(
                            'The attribute with a "%1" attributeCode doesn\'t exist.'
                            . ' Verify the attribute and try again.',
                            KlevuRatingCountInterface::ATTRIBUTE_CODE,
                        ),
                    );
                }
                $attributeRepository = $this->objectManager->create(AttributeRepositoryInterface::class);

                return $attributeRepository->get(
                    entityTypeCode: ProductAttributeInterface::ENTITY_TYPE_CODE,
                    attributeCode: KlevuRatingInterface::ATTRIBUTE_CODE,
                );
            });

        $this->objectManager->addSharedInstance(
            instance: $mockEavAttribute,
            className: AttributeRepositoryInterface::class,
            forPreference: true,
        );

        $service = $this->instantiateTestObject();
        $service->execute($productFixture->getProduct());

        $this->objectManager->removeSharedInstance(
            className: AttributeRepositoryInterface::class,
            forPreference: true,
        );
    }

    /**
     * @magentoAppIsolation enabled
     */
    public function testExecute_ReturnsWithoutCallingDataProvider_WhenNoStoresReturned(): void
    {
        $mockProduct = $this->getMockBuilder(Product::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockProduct->expects($this->once())
            ->method('getStoreIds')
            ->willReturn([]);

        $mockRatingProvider = $this->getMockBuilder(RatingProviderInterface::class)
            ->getMock();
        $mockRatingProvider->expects($this->never())
            ->method('get');

        $service = $this->instantiateTestObject([
            'ratingDataProvider' => $mockRatingProvider,
        ]);
        $service->execute($mockProduct);
    }

    public function testExecute_LogsError_WhenInvalidRatingValueExceptionThrown(): void
    {
        $this->createProduct();
        $productFixture = $this->productFixturePool->get('test_product');

        $exceptionMessage = 'Some exception message';

        $mockLogger = $this->getMockBuilder(LoggerInterface::class)
            ->getMock();
        $mockLogger->expects($this->once())
            ->method('error')
            ->with(
                'Method: {method}, Error: {message}',
                [
                    'method' => 'Klevu\IndexingProducts\Service\UpdateRatingService::execute',
                    'message' => $exceptionMessage,
                ],
            );

        $mockRatingProvider = $this->getMockBuilder(RatingProviderInterface::class)
            ->getMock();
        $mockRatingProvider->expects($this->once())
            ->method('get')
            ->willThrowException(
                new InvalidRatingValue(__($exceptionMessage)),
            );

        $mockProductRepository = $this->getMockBuilder(ProductRepositoryInterface::class)
            ->getMock();
        $mockProductRepository->expects($this->never())
            ->method('save');

        $service = $this->instantiateTestObject([
            'ratingDataProvider' => $mockRatingProvider,
            'logger' => $mockLogger,
            'productRepository' => $mockProductRepository,
        ]);
        $service->execute($productFixture->getProduct());
    }

    /**
     * @magentoDbIsolation disabled
     */
    public function testExecute_SavesRatingDataToProduct(): void
    {
        $this->createCustomer();
        $customerFixture = $this->customerFixturePool->get('test_customer');
        $customer = $customerFixture->getCustomer();

        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');

        $this->createProduct();
        $productFixture = $this->productFixturePool->get('test_product');
        /** @var Product&ProductInterface $product */
        $product = $productFixture->getProduct();

        $this->assertNull(actual: $product->getData(KlevuRatingInterface::ATTRIBUTE_CODE));
        $this->assertSame(expected: 0, actual: (int)$product->getData(KlevuRatingCountInterface::ATTRIBUTE_CODE));

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

        $service = $this->instantiateTestObject();
        $service->execute($product);

        $productRepository = $this->objectManager->get(ProductRepositoryInterface::class);
        $savedProduct = $productRepository->getById($product->getId(), $storeFixture->getId());

        $this->assertSame(expected: 5.0, actual: (float)$savedProduct->getData(KlevuRatingInterface::ATTRIBUTE_CODE));
        $this->assertSame(expected: 1, actual: (int)$savedProduct->getData(KlevuRatingCountInterface::ATTRIBUTE_CODE));

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
