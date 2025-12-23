<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Service\Determiner\RequiresUpdateCriteria;

use Klevu\Configuration\Service\Provider\StoresProviderInterface;
use Klevu\IndexingApi\Api\Data\IndexingEntityInterface;
use Klevu\IndexingApi\Service\Determiner\RequiresUpdateCriteriaInterface;
use Klevu\IndexingProducts\Service\Provider\ProductStockStatusProviderInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;

class StockStatus implements RequiresUpdateCriteriaInterface
{
    public const CRITERIA_IDENTIFIER = 'stock_status';

    /**
     * @var ProductRepositoryInterface
     */
    private readonly ProductRepositoryInterface $productRepository;
    /**
     * @var StoresProviderInterface
     */
    private readonly StoresProviderInterface $storesProvider;
    /**
     * @var ProductStockStatusProviderInterface
     */
    private readonly ProductStockStatusProviderInterface $productStockStatusProvider;

    /**
     * @param ProductRepositoryInterface $productRepository
     * @param StoresProviderInterface $storesProvider
     * @param ProductStockStatusProviderInterface $productStockStatusProvider
     */
    public function __construct(
        ProductRepositoryInterface $productRepository,
        StoresProviderInterface $storesProvider,
        ProductStockStatusProviderInterface $productStockStatusProvider,
    ) {
        $this->productRepository = $productRepository;
        $this->storesProvider = $storesProvider;
        $this->productStockStatusProvider = $productStockStatusProvider;
    }

    /**
     * @return string
     */
    public function getEntityType(): string
    {
        return 'KLEVU_PRODUCT';
    }

    /**
     * @return string
     */
    public function getCriteriaIdentifier(): string
    {
        return static::CRITERIA_IDENTIFIER;
    }

    /**
     * @param IndexingEntityInterface $indexingEntity
     *
     * @return bool
     * @throws NoSuchEntityException
     */
    public function execute(IndexingEntityInterface $indexingEntity): bool
    {
        $indexingEntityOrigValues = $indexingEntity->getRequiresUpdateOrigValues();
        if (!array_key_exists($this->getCriteriaIdentifier(), $indexingEntityOrigValues)) {
            return false;
        }

        $originalStockStatus = (bool)$indexingEntityOrigValues[$this->getCriteriaIdentifier()];

        $storesForApiKey = $this->storesProvider->get(
            apiKey: $indexingEntity->getApiKey(),
        );
        $product = $this->productRepository->getById(
            productId: $indexingEntity->getTargetId(),
        );
        $parentProduct = $indexingEntity->getTargetParentId()
            ? $this->productRepository->getById(
                productId: $indexingEntity->getTargetParentId(),
            )
            : null;

        $return = false;
        foreach ($storesForApiKey as $store) {
            $currentStockStatus = $this->productStockStatusProvider->get(
                product: $product,
                store: $store,
                parentProduct: $parentProduct,
            );
            if ($currentStockStatus !== $originalStockStatus) {
                $return = true;
                break;
            }
        }

        return $return;
    }
}
