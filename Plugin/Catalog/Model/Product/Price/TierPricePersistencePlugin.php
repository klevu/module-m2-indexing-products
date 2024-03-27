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
use Magento\Catalog\Model\Product\Price\TierPricePersistence;
use Magento\Catalog\Model\ResourceModel\Product\Attribute\Backend\Tierprice as TierPriceResourceModel;
use Magento\Framework\EntityManager\EntityMetadataInterface;
use Magento\Framework\EntityManager\MetadataPool;
use Magento\Framework\Exception\LocalizedException;
use Psr\Log\LoggerInterface;

class TierPricePersistencePlugin
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
     * @var TierPriceResourceModel
     */
    private readonly TierPriceResourceModel $tierPriceResourceModel;
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
     * @param TierPriceResourceModel $tierPriceResourceModel
     * @param LoggerInterface $logger
     * @param MetadataPool $metadataPool
     */
    public function __construct(
        EntityUpdateResponderServiceInterface $responderService,
        ProductIdProviderInterface $productIdProvider,
        TierPriceResourceModel $tierPriceResourceModel,
        LoggerInterface $logger,
        MetadataPool $metadataPool,
    ) {
        $this->responderService = $responderService;
        $this->productIdProvider = $productIdProvider;
        $this->tierPriceResourceModel = $tierPriceResourceModel;
        $this->logger = $logger;
        try {
            $this->productMetadata = $metadataPool->getMetadata(ProductInterface::class);
        } catch (\Exception $exception) {
            $this->productMetadata = null; //@phpstan-ignore-line
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
     * @param TierPricePersistence $subject
     * @param void $result
     * @param mixed[] $prices
     *
     * @return void
     */
    public function afterUpdate(
        // phpcs:ignore SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter
        TierPricePersistence $subject,
        // phpcs:ignore SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter
        mixed $result,
        array $prices,
    ): void {
        if (!$this->productMetadata) {
            return;
        }
        $linkField = $this->productMetadata->getLinkField();
        $entityIds = $this->productIdProvider->getByLinkFields(
            array_unique(
                array_map(
                    callback: static fn (array $priceData) => ($priceData[$linkField]),
                    array: $prices,
                ),
            ),
        );
        if ($entityIds) {
            $this->responderService->execute([
                Entity::ENTITY_IDS => $entityIds,
                EntityUpdateResponderServiceInterface::CHANGED_ATTRIBUTES => [
                    ProductInterface::PRICE,
                ],
            ]);
        }
        //  \Magento\Catalog\Model\Product\Price\TierPricePersistence::update returns void
    }

    /**
     * @param TierPricePersistence $subject
     * @param void $result
     * @param mixed[] $prices
     * @param mixed[] $ids
     *
     * @return void
     */
    public function afterReplace(
        // phpcs:ignore SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter
        TierPricePersistence $subject,
        // phpcs:ignore SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter
        mixed $result,
        array $prices,
        array $ids,
    ): void {
        if (!$this->productMetadata) {
            return;
        }
        $linkField = $this->productMetadata->getLinkField();
        $entityIds = $this->productIdProvider->getByLinkFields(
            array_unique(
                array_merge(
                    $ids,
                    array_map(
                        callback: static fn (array $priceData) => ($priceData[$linkField]),
                        array: $prices,
                    ),
                ),
            ),
        );
        if ($entityIds) {
            $this->responderService->execute([
                Entity::ENTITY_IDS => $entityIds,
                EntityUpdateResponderServiceInterface::CHANGED_ATTRIBUTES => [
                    ProductInterface::PRICE,
                ],
            ]);
        }
        //  \Magento\Catalog\Model\Product\Price\TierPricePersistence::replace returns void
    }

    /**
     * @param TierPricePersistence $subject
     * @param callable $proceed
     * @param mixed[] $ids
     *
     * @return void
     */
    public function aroundDelete(
        // phpcs:ignore SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter
        TierPricePersistence $subject,
        callable $proceed,
        array $ids,
    ): void {
        if (!$this->productMetadata) {
            return;
        }
        $entityIds = $this->getEntityIdsFromValueId($ids);

        $proceed($ids); // returns void

        if ($entityIds) {
            $this->responderService->execute([
                Entity::ENTITY_IDS => $entityIds,
                EntityUpdateResponderServiceInterface::CHANGED_ATTRIBUTES => [
                    ProductInterface::PRICE,
                ],
            ]);
        }
    }

    /**
     * @param mixed[] $ids
     *
     * @return int[]
     */
    private function getEntityIdsFromValueId(array $ids): array
    {
        $return = [];
        try {
            $linkField = $this->productMetadata->getLinkField();
            $connection = $this->tierPriceResourceModel->getConnection();
            $select = $connection->select();
            $select->from(
                name: $this->tierPriceResourceModel->getMainTable(),
                cols: [$linkField],
            );
            $select->where('value_id IN (?)', $ids, \Zend_Db::INT_TYPE);
            $linkFieldIds = $connection->fetchAll($select);

            $return = array_filter(
                array: $this->productIdProvider->getByLinkFields($linkFieldIds),
            );
        } catch (LocalizedException $exception) {
            $this->logger->error(
                message: 'Method: {method}, Error: {message}',
                context: [
                    'method' => __METHOD__,
                    'message' => $exception->getMessage(),
                ],
            );
        }

        return $return;
    }
}
