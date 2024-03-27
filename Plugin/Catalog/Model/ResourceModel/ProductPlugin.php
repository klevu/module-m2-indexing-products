<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Plugin\Catalog\Model\ResourceModel;

use Klevu\Indexing\Model\Update\Entity;
use Klevu\IndexingApi\Service\EntityUpdateResponderServiceInterface;
use Klevu\IndexingApi\Service\Provider\AttributesToWatchProviderInterface;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\ProductFactory;
use Magento\Catalog\Model\ResourceModel\Product as ProductResourceModel;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Framework\Model\AbstractModel;

class ProductPlugin
{
    /**
     * @var ProductFactory
     */
    private readonly ProductFactory $productFactory;
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
     * @param ProductFactory $productFactory
     * @param EntityUpdateResponderServiceInterface $responderService
     * @param AttributesToWatchProviderInterface $attributesToWatchProvider
     */
    public function __construct(
        ProductFactory $productFactory,
        EntityUpdateResponderServiceInterface $responderService,
        AttributesToWatchProviderInterface $attributesToWatchProvider,
    ) {
        $this->productFactory = $productFactory;
        $this->responderService = $responderService;
        $this->attributesToWatchProvider = $attributesToWatchProvider;
    }

    /**
     * @param ProductResourceModel $resourceModel
     * @param \Closure $proceed
     * @param AbstractModel $object
     *
     * @return ProductResourceModel
     */
    public function aroundSave(
        ProductResourceModel $resourceModel,
        \Closure $proceed,
        AbstractModel $object,
    ): ProductResourceModel {
        /** @var ProductInterface&AbstractModel $object */
        $originalProduct = $this->getOriginalProduct($resourceModel, $object);

        $return = $proceed($object);

        if ($this->isUpdateRequired($originalProduct, $object)) {
            $data = [
                Entity::ENTITY_IDS => [(int)$object->getId()],
                Entity::STORE_IDS => $this->getStoreIds($originalProduct, $object),
                EntityUpdateResponderServiceInterface::CHANGED_ATTRIBUTES => $this->changedAttributes,
            ];
            $this->responderService->execute($data);
        }

        return $return;
    }

    /**
     * @param ProductResourceModel $resourceModel
     * @param AbstractModel&ProductInterface $product
     *
     * @return AbstractModel&ProductInterface
     */
    private function getOriginalProduct(
        ProductResourceModel $resourceModel,
        AbstractModel&ProductInterface $product,
    ): AbstractModel&ProductInterface {
        $originalProduct = $this->productFactory->create();
        $productId = $product->getId();
        if ($productId) {
            $resourceModel->load($originalProduct, $productId);
        }

        return $originalProduct;
    }

    /**
     * @param AbstractModel&ProductInterface $originalProduct
     * @param AbstractModel&ProductInterface $product
     *
     * @return bool
     */
    private function isUpdateRequired(
        AbstractModel&ProductInterface $originalProduct,
        AbstractModel&ProductInterface $product,
    ): bool {
        if (!$originalProduct->getId()) {
            // is a new product
            return true;
        }
        foreach ($this->attributesToWatchProvider->getAttributeCodes() as $attribute) {
            if (Configurable::TYPE_CODE === $product->getTypeId() && 'quantity_and_stock_status' === $attribute) {
                // This attribute is always different even when saving the product with no changes.
                // 'quantity_and_stock_status' = ['inStock' => true, qty => 1.0]
                // becomes 'quantity_and_stock_status' = '1'
                // Therefore we ignore it here
                continue;
            }
            if ($originalProduct->getData($attribute) !== $product->getData($attribute)) {
                $this->changedAttributes[] = $attribute;
            }
        }

        return (bool)count($this->changedAttributes);
    }

    /**
     * @param AbstractModel&ProductInterface $originalProduct
     * @param AbstractModel&ProductInterface $product
     *
     * @return int[]
     */
    private function getStoreIds(
        AbstractModel&ProductInterface $originalProduct,
        AbstractModel&ProductInterface $product,
    ): array {
        return array_filter(
            array_unique(
                array_merge(
                    [(int)$originalProduct->getStoreId()],
                    [(int)$product->getStoreId()],
                ),
            ),
        );
    }
}
