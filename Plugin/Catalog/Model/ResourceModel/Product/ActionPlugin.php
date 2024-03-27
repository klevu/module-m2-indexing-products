<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Plugin\Catalog\Model\ResourceModel\Product;

use Klevu\Indexing\Model\Update\Entity;
use Klevu\IndexingApi\Service\EntityUpdateResponderServiceInterface;
use Klevu\IndexingApi\Service\Provider\AttributesToWatchProviderInterface;
use Magento\Catalog\Model\ResourceModel\Product\Action as ProductAction;

class ActionPlugin
{
    /**
     * @var EntityUpdateResponderServiceInterface
     */
    private readonly EntityUpdateResponderServiceInterface $responderService;
    /**
     * @var AttributesToWatchProviderInterface
     */
    private readonly AttributesToWatchProviderInterface $attributesToWatchProvider;
    /**
     * @var string[]
     */
    private array $changedAttributes = [];

    /**
     * @param EntityUpdateResponderServiceInterface $responderService
     * @param AttributesToWatchProviderInterface $attributesToWatchProvider
     */
    public function __construct(
        EntityUpdateResponderServiceInterface $responderService,
        AttributesToWatchProviderInterface $attributesToWatchProvider,
    ) {
        $this->responderService = $responderService;
        $this->attributesToWatchProvider = $attributesToWatchProvider;
    }

    /**
     * @param ProductAction $subject
     * @param ProductAction $result
     * @param array<int|string> $entityIds
     * @param mixed[] $attrData
     * @param int $storeId
     *
     * @return ProductAction
     */
    public function afterUpdateAttributes(
        // phpcs:ignore SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter
        ProductAction $subject,
        ProductAction $result,
        mixed $entityIds,
        mixed $attrData,
        mixed $storeId,
    ): ProductAction {
        /**
         * Set all supplied entities to require update for the supplied attributes.
         * We don't check the original values here due to performance considerations.
         * We assume that the attribute value has changed.
         */
        if ($this->isUpdateRequired($attrData)) {
            $data = [
                Entity::ENTITY_IDS => array_map('intval', $entityIds),
                Entity::STORE_IDS => [(int) $storeId],
                EntityUpdateResponderServiceInterface::CHANGED_ATTRIBUTES => $this->changedAttributes,
            ];
            $this->responderService->execute($data);
        }

        return $result;
    }

    /**
     * @param mixed[] $attrData
     *
     * @return bool
     */
    private function isUpdateRequired(array $attrData): bool
    {
        $attributeCodes = $this->attributesToWatchProvider->getAttributeCodes();
        foreach (array_keys($attrData) as $attributeCode) {
            if (in_array($attributeCode, $attributeCodes, true)) {
                $this->changedAttributes[] = $attributeCode;
            }
        }

        return (bool)count($this->changedAttributes);
    }
}
