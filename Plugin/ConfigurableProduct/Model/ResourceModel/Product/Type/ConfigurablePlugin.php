<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Plugin\ConfigurableProduct\Model\ResourceModel\Product\Type;

use Klevu\Indexing\Model\Update\Entity;
use Klevu\IndexingApi\Service\EntityUpdateResponderServiceInterface;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\ConfigurableProduct\Model\ResourceModel\Product\Type\Configurable;
use Magento\ConfigurableProduct\Pricing\Price\ConfigurableOptionsProviderInterface;
use Magento\ConfigurableProduct\Pricing\Price\ConfigurableOptionsProviderInterfaceFactory;

class ConfigurablePlugin
{
    /**
     * @var EntityUpdateResponderServiceInterface
     */
    private readonly EntityUpdateResponderServiceInterface $responderService;
    /**
     * @var ConfigurableOptionsProviderInterfaceFactory
     */
    private readonly ConfigurableOptionsProviderInterfaceFactory $configurableOptionsProviderFactory;

    /**
     * @param EntityUpdateResponderServiceInterface $responderService
     * @param ConfigurableOptionsProviderInterfaceFactory $configurableOptionsProviderFactory
     */
    public function __construct(
        EntityUpdateResponderServiceInterface $responderService,
        ConfigurableOptionsProviderInterfaceFactory $configurableOptionsProviderFactory,
    ) {
        $this->responderService = $responderService;
        $this->configurableOptionsProviderFactory = $configurableOptionsProviderFactory;
    }

    /**
     * Ideally we would have used an after plugin here,
     * however, $mainProduct->getOrigData() already has the extension attribute for configurable options updated.
     * Therefore, we can't tell if products have been removed or added when using the after plugin :-(
     *
     * @param Configurable $subject
     * @param \Closure $proceed
     * @param mixed $mainProduct
     * @param int[] $productIds
     *
     * @return Configurable
     */
    public function aroundSaveProducts(
        // phpcs:ignore SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter
        Configurable $subject,
        \Closure $proceed,
        mixed $mainProduct,
        array $productIds,
    ): Configurable {
        $productIdsToSend = $this->getProductIdsToSend(
            product: $mainProduct,
            productIds: $productIds,
        );

        $return = $proceed($mainProduct, $productIds);

        if (!empty($productIdsToSend)) {
            $this->responderService->execute([
                Entity::ENTITY_IDS => array_map('intval', $productIdsToSend),
            ]);
        }

        return $return;
    }

    /**
     * @param mixed $product
     * @param int[] $productIds
     *
     * @return int[]
     */
    private function getProductIdsToSend(mixed $product, array $productIds): array
    {
        // Configurable::saveProducts does not have a type hint on $mainProduct,
        // therefore we use mixed and check type to be safe
        if (!$product instanceof ProductInterface) {
            return [];
        }
        $productIds = array_map('intval', $productIds);
        $childProductIds = $this->getExistingChildProductIds(product: $product);
        $add = array_diff($productIds, $childProductIds);
        $delete = array_diff($childProductIds, $productIds);

        return array_merge($add, $delete);
    }

    /**
     * @param ProductInterface $product
     *
     * @return int[]
     */
    private function getExistingChildProductIds(ProductInterface $product): array
    {
        /** @var ConfigurableOptionsProviderInterface $configurableOptionsProvider */
        $configurableOptionsProvider = $this->configurableOptionsProviderFactory->create();
        $childProducts = $configurableOptionsProvider->getProducts(product: $product);

        return array_map(
            callback: static fn (ProductInterface $childProduct) => (int)$childProduct->getId(),
            array: $childProducts,
        );
    }
}
