<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Service\Provider;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Api\Data\StoreInterface;
use Psr\Log\LoggerInterface;

class ProductStatusProvider implements ProductStatusProviderInterface
{
    /**
     * @var LoggerInterface
     */
    private readonly LoggerInterface $logger;
    /**
     * @var ProductRepositoryInterface
     */
    private readonly ProductRepositoryInterface $productRepository;

    /**
     * @param LoggerInterface $logger
     * @param ProductRepositoryInterface $productRepository
     */
    public function __construct(
        LoggerInterface $logger,
        ProductRepositoryInterface $productRepository,
    ) {
        $this->logger = $logger;
        $this->productRepository = $productRepository;
    }

    /**
     * @param ProductInterface $product
     * @param StoreInterface|null $store
     * @param ProductInterface|null $parentProduct
     *
     * @return bool
     */
    public function get(
        ProductInterface $product,
        ?StoreInterface $store,
        ?ProductInterface $parentProduct = null,
    ): bool {
        if (null !== $parentProduct) {
            $parentProductStatus = $this->get(
                product: $parentProduct,
                store: $store,
            );
            if (!$parentProductStatus) {
                return false;
            }
        }

        $productToTest = $product;
        if (
            null !== $store
            && (int)$store->getId() !== (int)$product->getStoreId()
        ) {
            try {
                $productToTest = $this->productRepository->get(
                    sku: $product->getSku(),
                    editMode: false,
                    storeId: (int)$store->getId(),
                );
            } catch (NoSuchEntityException $exception) {
                $this->logger->error(
                    message: 'Could not load product for store to test status',
                    context: [
                        'exception' => $exception::class,
                        'error' => $exception->getMessage(),
                        'productId' => $product->getId(),
                        'storeId' => $store->getId(),
                    ],
                );
            }
        }

        return (int)$productToTest->getStatus() !== Status::STATUS_DISABLED;
    }
}
