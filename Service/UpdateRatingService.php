<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Service;

use Klevu\IndexingApi\Service\Provider\Rating\RatingProviderInterface;
use Klevu\IndexingApi\Service\UpdateRatingServiceInterface;
use Klevu\IndexingApi\Validator\ValidatorInterface;
use Klevu\IndexingProducts\Exception\InvalidRatingValue;
use Klevu\IndexingProducts\Exception\KlevuProductAttributeMissingException;
use Klevu\IndexingProducts\Model\Attribute\KlevuRatingCountInterface;
use Klevu\IndexingProducts\Model\Attribute\KlevuRatingInterface;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\Product\Action as ProductAction;
use Magento\Catalog\Model\Product\ActionFactory as ProductActionFactory;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

class UpdateRatingService implements UpdateRatingServiceInterface
{
    /**
     * @var ValidatorInterface
     */
    private readonly ValidatorInterface $ratingAttributeValidator;
    /**
     * @var ValidatorInterface
     */
    private readonly ValidatorInterface $ratingCountAttributeValidator;
    /**
     * @var StoreManagerInterface
     */
    private readonly StoreManagerInterface $storeManager;
    /**
     * @var RatingProviderInterface
     */
    private readonly RatingProviderInterface $ratingDataProvider;
    /**
     * @var LoggerInterface
     */
    private readonly LoggerInterface $logger;
    /**
     * @var ProductActionFactory
     */
    private readonly ProductActionFactory $productActionFactory;
    /**
     * @var string|null
     */
    private readonly ?string $ratingAttribute;
    /**
     * @var string|null
     */
    private readonly ?string $ratingCountAttribute;

    /**
     * @param ValidatorInterface $ratingAttributeValidator
     * @param ValidatorInterface $ratingCountAttributeValidator
     * @param StoreManagerInterface $storeManager
     * @param RatingProviderInterface $ratingDataProvider
     * @param LoggerInterface $logger
     * @param ProductActionFactory $productActionFactory
     * @param string|null $ratingAttribute
     * @param string|null $ratingCountAttribute
     */
    public function __construct(
        ValidatorInterface $ratingAttributeValidator,
        ValidatorInterface $ratingCountAttributeValidator,
        StoreManagerInterface $storeManager,
        RatingProviderInterface $ratingDataProvider,
        LoggerInterface $logger,
        ProductActionFactory $productActionFactory,
        ?string $ratingAttribute = KlevuRatingInterface::ATTRIBUTE_CODE,
        ?string $ratingCountAttribute = KlevuRatingCountInterface::ATTRIBUTE_CODE,
    ) {
        $this->ratingAttributeValidator = $ratingAttributeValidator;
        $this->ratingCountAttributeValidator = $ratingCountAttributeValidator;
        $this->storeManager = $storeManager;
        $this->ratingDataProvider = $ratingDataProvider;
        $this->logger = $logger;
        $this->productActionFactory = $productActionFactory;
        $this->ratingAttribute = $ratingAttribute;
        $this->ratingCountAttribute = $ratingCountAttribute;
    }

    /**
     * @param DataObject&ProductInterface $product
     *
     * @return void
     * @throws KlevuProductAttributeMissingException
     * @throws NoSuchEntityException
     */
    public function execute(ProductInterface $product): void
    {
        $this->validateAttributes();
        $storeIds = $this->getStoresToUpdate($product);
        if (empty($storeIds)) {
            return;
        }
        /** @var ProductAction $productAction */
        $productAction = $this->productActionFactory->create();
        foreach ($storeIds as $storeId) {
            try {
                $ratingData = $this->ratingDataProvider->get((int)$product->getId(), (int)$storeId);
                $attributeData = [];
                if ($ratingData[RatingProviderInterface::RATING] ?? null) {
                    $attributeData[KlevuRatingInterface::ATTRIBUTE_CODE] = $ratingData[RatingProviderInterface::RATING];
                }
                if ($ratingData[RatingProviderInterface::COUNT] ?? null) {
                    // phpcs:ignore Generic.Files.LineLength.TooLong
                    $attributeData[KlevuRatingCountInterface::ATTRIBUTE_CODE] = $ratingData[RatingProviderInterface::COUNT];
                }
                if (!empty($attributeData)) {
                    $productAction->updateAttributes(
                        productIds: [$product->getId()],
                        attrData: $attributeData,
                        storeId: $storeId,
                    );
                }
            } catch (InvalidRatingValue $exception) {
                $this->logger->error(
                    message: 'Method: {method}, Error: {message}',
                    context: [
                        'method' => __METHOD__,
                        'message' => $exception->getMessage(),
                    ],
                );
            }
        }
    }

    /**
     * @return void
     * @throws KlevuProductAttributeMissingException
     */
    private function validateAttributes(): void
    {
        $messages = [];
        $isRatingAttributeValid = $this->ratingAttributeValidator->isValid($this->ratingAttribute);
        if (!$isRatingAttributeValid && $this->ratingAttributeValidator->hasMessages()) {
            $messages += $this->ratingAttributeValidator->getMessages();
        }
        $isRatingCountAttributeValid = $this->ratingCountAttributeValidator->isValid($this->ratingCountAttribute);
        if (!$isRatingCountAttributeValid && $this->ratingCountAttributeValidator->hasMessages()) {
            $messages += $this->ratingCountAttributeValidator->getMessages();
        }
        if (!$isRatingAttributeValid || !$isRatingCountAttributeValid) {
            $message = $messages
                ? implode('; ', $messages)
                : 'Invalid Attribute supplied.';
            throw new KlevuProductAttributeMissingException(
                __($message),
            );
        }
    }

    /**
     * @param ProductInterface $product
     *
     * @return int[]
     */
    private function getStoresToUpdate(ProductInterface $product): array
    {
        $isSingleStoreMode = $this->storeManager->isSingleStoreMode();

        if (method_exists($product, 'getStoreIds')) {
            $storeIds = array_map(callback: 'intval', array: $product->getStoreIds());
        } else {
            $storeIds = array_map(
                callback: static function (StoreInterface $store) {
                    return (int)$store->getId();
                },
                array: $this->storeManager->getStores($isSingleStoreMode)
                    ?: [],
            );
        }
        if (!$isSingleStoreMode) {
            $storeIds = array_filter($storeIds);
        }

        return $storeIds;
    }
}
