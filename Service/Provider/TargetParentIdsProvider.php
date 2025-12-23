<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Service\Provider;

use Klevu\IndexingApi\Service\Provider\TargetParentIdsProviderInterface;
use Magento\Bundle\Model\Product\Type as BundleType;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable as ConfigurableType;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\GroupedProduct\Model\Product\Type\Grouped as GroupedType;
use Psr\Log\LoggerInterface;

class TargetParentIdsProvider implements TargetParentIdsProviderInterface
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
     * @var ConfigurableType
     */
    private readonly ConfigurableType $configurableType;
    /**
     * @var BundleType 
     */
    private readonly BundleType $bundleType;
    /**
     * @var GroupedType 
     */
    private readonly GroupedType $groupedType;
    /**
     * @var string[]
     */
    private array $applicableProductTypes = [];

    /**
     * @param LoggerInterface $logger
     * @param ProductRepositoryInterface $productRepository
     * @param ConfigurableType $configurableType
     * @param BundleType $bundleType
     * @param GroupedType $groupedType
     * @param array<string, string> $applicableProductTypes
     */
    public function __construct(
        LoggerInterface $logger,
        ProductRepositoryInterface $productRepository,
        ConfigurableType $configurableType,
        BundleType $bundleType,
        GroupedType $groupedType,
        array $applicableProductTypes = [],
    ) {
        $this->logger = $logger;
        $this->productRepository = $productRepository;
        $this->configurableType = $configurableType;
        $this->bundleType = $bundleType;
        $this->groupedType = $groupedType;
        array_walk($applicableProductTypes, [$this, 'addApplicableProductType']);
    }

    /**
     * @param string $entityType
     * @param int $targetId
     *
     * @return int[]
     */
    public function get(
        string $entityType, 
        int $targetId,
    ): array {
        if ('KLEVU_PRODUCT' !== $entityType) {
            return [];
        }
        
        try {
            $product = $this->productRepository->getById($targetId);
        } catch (NoSuchEntityException $exception) {
            $this->logger->error(
                message: 'Could not retrieve parent ids for non-existent product',
                context: [
                    'method' => __METHOD__,
                    'exception' => $exception::class,
                    'error' => $exception->getMessage(),
                    'entityType' => $entityType,
                    'targetId' => $targetId,
                ],
            );
            
            return [];
        }

        if (!in_array($product->getTypeId(), $this->applicableProductTypes, true)) {
            return [];
        }
        
        $allParentIds = array_merge(
            $this->getConfigurableParentIds($product),
            $this->getBundleParentIds($product),
            $this->getGroupedParentIds($product),
        );
        
        return array_filter(
            array: array_unique($allParentIds),
        );
    }

    /**
     * @note Public method to allow plugins if required
     * 
     * @param ProductInterface $product
     *
     * @return int[]
     */
    public function getConfigurableParentIds(
        ProductInterface $product,
    ): array {
        return array_map(
            callback: 'intval',
            array: $this->configurableType->getParentIdsByChild(
                childId: (int)$product->getId(),
            ),
        );
    }

    /**
     * @note Public method to allow plugins if required
     *
     * @param ProductInterface $product
     *
     * @return int[]
     */
    public function getBundleParentIds(
        ProductInterface $product,
    ): array {
        return array_map(
            callback: 'intval',
            array: $this->bundleType->getParentIdsByChild(
                childId: (int)$product->getId(),
            ),
        );
    }

    /**
     * @note Public method to allow plugins if required
     *
     * @param ProductInterface $product
     *
     * @return int[]
     */
    public function getGroupedParentIds(
        ProductInterface $product,
    ): array {
        return array_map(
            callback: 'intval',
            array: $this->groupedType->getParentIdsByChild(
                childId: (int)$product->getId(),
            ),
        );
    }

    /**
     * @param string|null $productType
     * @param string $identifier
     *
     * @return void
     */
    private function addApplicableProductType(
        ?string $productType,
        string $identifier,
    ): void {
        if (null === $productType) {
            return;
        }

        $this->applicableProductTypes[$identifier] = $productType;
    }
}
