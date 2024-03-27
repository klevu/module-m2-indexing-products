<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Plugin\Catalog\Model\ProductLink;

use Klevu\Indexing\Model\Update\Entity;
use Klevu\IndexingApi\Service\EntityUpdateResponderServiceInterface;
use Klevu\IndexingApi\Service\Provider\ProductIdProviderInterface;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\Data\ProductLinkInterface;
use Magento\Catalog\Model\ProductLink\Repository as ProductLinkRepository;
use Magento\CatalogInventory\Model\Stock\Status;

class ProductLinkRepositoryPlugin
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
     * @param ProductLinkRepository $subject
     * @param bool $result
     * @param ProductLinkInterface $entity
     *
     * @return bool
     */
    public function afterDelete(
        // phpcs:ignore SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter
        ProductLinkRepository $subject,
        bool $result,
        ProductLinkInterface $entity,
    ): bool {
        if ($entity->getLinkType() === 'associated') {
            $parentId = $this->productIdProvider->getBySku($entity->getSku());
            $this->responderService->execute([
                Entity::ENTITY_IDS => [(int)$parentId],
                EntityUpdateResponderServiceInterface::CHANGED_ATTRIBUTES => [
                    ProductInterface::PRICE,
                    Status::STOCK_STATUS,
                ],
            ]);
        }

        return $result;
    }
}
