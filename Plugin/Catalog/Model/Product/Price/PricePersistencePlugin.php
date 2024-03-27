<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Plugin\Catalog\Model\Product\Price;

use Klevu\Indexing\Model\Update\Entity;
use Klevu\IndexingApi\Service\EntityUpdateResponderServiceInterface;
use Klevu\IndexingApi\Service\Provider\ProductIdProviderInterface;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\Product\Price\PricePersistence;
use Magento\Framework\EntityManager\EntityMetadataInterface;
use Magento\Framework\EntityManager\MetadataPool;
use Psr\Log\LoggerInterface;

class PricePersistencePlugin
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
     * @var LoggerInterface
     */
    private readonly LoggerInterface $logger;
    /**
     * @var EntityMetadataInterface|null
     */
    private readonly ?EntityMetadataInterface $productMetadata;

    /**
     * @param EntityUpdateResponderServiceInterface $responderService
     * @param ProductIdProviderInterface $productIdProvider
     * @param LoggerInterface $logger
     * @param MetadataPool $metadataPool
     */
    public function __construct(
        EntityUpdateResponderServiceInterface $responderService,
        ProductIdProviderInterface $productIdProvider,
        LoggerInterface $logger,
        MetadataPool $metadataPool,
    ) {
        $this->responderService = $responderService;
        $this->productIdProvider = $productIdProvider;
        $this->logger = $logger;
        try {
            $this->productMetadata = $metadataPool->getMetadata(ProductInterface::class);
        } catch (\Exception $exception) {
            $this->productMetadata = null; // @phpstan-ignore-line
            $this->logger->error(
                message: 'Method: {method}, Error: {message}',
                context: [
                    'method' => __METHOD__,
                    'message' => $exception->getMessage(),
                ],
            );
        }
    }

    /**
     * @param PricePersistence $subject
     * @param void $result
     * @param mixed[] $prices
     *
     * @return void
     */
    public function afterUpdate(
        // phpcs:ignore SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter
        PricePersistence $subject,
        // phpcs:ignore SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter
        mixed $result,
        array $prices,
    ): void {
        if (!$this->productMetadata) {
            return;
        }
        $updates = $this->getEntityIdsByStore($prices);
        foreach ($updates as $storeId => $entityIds) {
            $this->responderService->execute([
                Entity::ENTITY_IDS => $entityIds,
                Entity::STORE_IDS => [$storeId],
                EntityUpdateResponderServiceInterface::CHANGED_ATTRIBUTES => [
                    ProductInterface::PRICE,
                ],
            ]);
        }

        // Magento\Catalog\Model\Product\Price\PricePersistence::update returns void
    }

    /**
     * @param mixed[] $prices
     *
     * @return array<int, int[]>
     */
    private function getEntityIdsByStore(array $prices): array
    {
        $linkField = $this->productMetadata->getLinkField();
        $priceByStore = [];
        foreach ($prices as $priceUpdate) {
            $priceByStore[$priceUpdate['store_id']] = $priceByStore[$priceUpdate['store_id']] ?? [];
            $priceByStore[$priceUpdate['store_id']][] = $priceUpdate[$linkField];
        }

        return $this->convertLinkFieldIdsToEntityIds($priceByStore);
    }

    /**
     * @param mixed[] $linkFieldIdsByStore
     *
     * @return array<int, int[]>
     */
    private function convertLinkFieldIdsToEntityIds(array $linkFieldIdsByStore): array
    {
        $return = [];
        foreach ($linkFieldIdsByStore as $storeId => $linkFieldIds) {
            $return[(int)$storeId] = $this->productIdProvider->getByLinkFields($linkFieldIds);
        }

        return $return;
    }
}
