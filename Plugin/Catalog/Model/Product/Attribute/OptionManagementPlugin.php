<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Plugin\Catalog\Model\Product\Attribute;

use Klevu\Indexing\Model\Update\Attribute;
use Klevu\IndexingApi\Service\AttributeUpdateResponderServiceInterface;
use Magento\Catalog\Api\ProductAttributeRepositoryInterface;
use Magento\Catalog\Model\Product\Attribute\OptionManagement;
use Magento\Eav\Api\Data\AttributeOptionInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Psr\Log\LoggerInterface;

class OptionManagementPlugin
{
    /**
     * @var AttributeUpdateResponderServiceInterface
     */
    private readonly AttributeUpdateResponderServiceInterface $responderService;
    /**
     * @var ProductAttributeRepositoryInterface
     */
    private readonly ProductAttributeRepositoryInterface $repository;
    /**
     * @var LoggerInterface
     */
    private readonly LoggerInterface $logger;

    /**
     * @param AttributeUpdateResponderServiceInterface $responderService
     * @param ProductAttributeRepositoryInterface $repository
     * @param LoggerInterface $logger
     */
    public function __construct(
        AttributeUpdateResponderServiceInterface $responderService,
        ProductAttributeRepositoryInterface $repository,
        LoggerInterface $logger,
    ) {
        $this->responderService = $responderService;
        $this->repository = $repository;
        $this->logger = $logger;
    }

    /**
     * @param OptionManagement $subject
     * @param string $result
     * @param string $attributeCode
     * @param AttributeOptionInterface $option
     *
     * @return string
     */
    public function afterAdd(
        // phpcs:ignore SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter
        OptionManagement $subject,
        mixed $result,
        mixed $attributeCode,
        // phpcs:ignore SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter
        mixed $option,
    ): string {
        try {
            $attribute = $this->repository->get(attributeCode: $attributeCode);
            $this->responderService->execute(data: [
                Attribute::ATTRIBUTE_IDS => [(int)$attribute->getAttributeId()],
            ]);
        } catch (NoSuchEntityException $exception) {
            $this->logger->error(
                message: 'Method: {method}, Error: {message}',
                context: [
                    'method' => __METHOD__,
                    'message' => $exception->getMessage(),
                ],
            );
        }

        return $result;
    }

    /**
     * @param OptionManagement $subject
     * @param bool $result
     * @param string $attributeCode
     * @param string $optionId
     *
     * @return bool
     */
    public function afterDelete(
        // phpcs:ignore SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter
        OptionManagement $subject,
        // phpcs:ignore SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter
        mixed $result,
        mixed $attributeCode,
        // phpcs:ignore SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter
        mixed $optionId,
    ): bool {
        try {
            $attribute = $this->repository->get(attributeCode: $attributeCode);
            $this->responderService->execute(data: [
                Attribute::ATTRIBUTE_IDS => [(int)$attribute->getAttributeId()],
            ]);
        } catch (NoSuchEntityException $exception) {
            $this->logger->error(
                message: 'Method: {method}, Error: {message}',
                context: [
                    'method' => __METHOD__,
                    'message' => $exception->getMessage(),
                ],
            );
        }

        return $result;
    }

    public function afterUpdate(
        // phpcs:ignore SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter
        OptionManagement $subject,
        // phpcs:ignore SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter
        bool $result,
        string $attributeCode,
        // phpcs:ignore SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter
        int $optionId,
        // phpcs:ignore SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter
        AttributeOptionInterface $option,
    ): bool {
        try {
            $attribute = $this->repository->get(attributeCode: $attributeCode);
            $this->responderService->execute(data: [
                Attribute::ATTRIBUTE_IDS => [(int)$attribute->getAttributeId()],
            ]);
        } catch (NoSuchEntityException $exception) {
            $this->logger->error(
                message: 'Method: {method}, Error: {message}',
                context: [
                    'method' => __METHOD__,
                    'message' => $exception->getMessage(),
                ],
            );
        }

        return $result;
    }
}
