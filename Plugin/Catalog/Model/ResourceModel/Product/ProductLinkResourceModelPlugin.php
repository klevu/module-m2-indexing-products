<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Plugin\Catalog\Model\ResourceModel\Product;

use Klevu\Indexing\Model\Update\Entity;
use Klevu\IndexingApi\Service\EntityUpdateResponderServiceInterface;
use Klevu\IndexingApi\Service\Provider\ProductIdProviderInterface;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\ResourceModel\Product\Link as ProductLinkResourceModel;
use Magento\CatalogInventory\Model\Stock\Status;
use Magento\GroupedProduct\Model\ResourceModel\Product\Link as GroupedProductLinkResourceModel;

class ProductLinkResourceModelPlugin
{
    /**
     * @var EntityUpdateResponderServiceInterface
     */
    private readonly EntityUpdateResponderServiceInterface $responderService;
    /**
     * @var ProductIdProviderInterface
     */
    private readonly ProductIdProviderInterface $productIdProvider;

    /**
     * @param EntityUpdateResponderServiceInterface $responderService
     * @param ProductIdProviderInterface $productIdProvider
     */
    public function __construct(
        EntityUpdateResponderServiceInterface $responderService,
        ProductIdProviderInterface $productIdProvider,
    ) {
        $this->responderService = $responderService;
        $this->productIdProvider = $productIdProvider;
    }

    /**
     * @param ProductLinkResourceModel $subject
     * @param ProductLinkResourceModel $result
     * @param mixed $parentId
     * @param mixed $data
     * @param mixed $typeId
     *
     * @return ProductLinkResourceModel
     */
    public function afterSaveProductLinks(
        // phpcs:ignore SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter
        ProductLinkResourceModel $subject,
        ProductLinkResourceModel $result,
        mixed $parentId,
        // phpcs:ignore SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter
        mixed $data,
        mixed $typeId,
    ): ProductLinkResourceModel {
        if ($this->isGroupedProductLink($typeId)) {
            $this->responderService->execute([
                Entity::ENTITY_IDS => $this->productIdProvider->getByLinkFields(
                    linkFieldIds: [(int)$parentId],
                ),
                EntityUpdateResponderServiceInterface::CHANGED_ATTRIBUTES => [
                    ProductInterface::PRICE,
                    Status::STOCK_STATUS,
                ],
            ]);
        }

        return $result;
    }

    /**
     * @param mixed $typeId
     *
     * @return bool
     */
    private function isGroupedProductLink(mixed $typeId): bool
    {
        return (int)$typeId === GroupedProductLinkResourceModel::LINK_TYPE_GROUPED;
    }
}
