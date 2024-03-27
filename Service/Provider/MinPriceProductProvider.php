<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Service\Provider;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Pricing\Price\FinalPriceInterface;
use Magento\Framework\Pricing\PriceInfo\Base as BasePriceInfo;
use Magento\GroupedProduct\Model\Product\Type\Grouped;
use Magento\GroupedProduct\Pricing\Price\FinalPrice;

class MinPriceProductProvider implements MinPriceProductProviderInterface
{
    /**
     * @param ProductInterface $product
     *
     * @return ProductInterface|null
     * @throws \LogicException
     */
    public function get(ProductInterface $product): ?ProductInterface
    {
        if ($product->getTypeId() !== Grouped::TYPE_CODE) {
            return $product;
        }

        return $this->getMinimumPriceProduct(product: $product);
    }

    /**
     * @param ProductInterface $product
     *
     * @return ProductInterface|null
     * @throws \LogicException
     */
    private function getMinimumPriceProduct(ProductInterface $product): ?ProductInterface
    {
        if (!(method_exists($product, 'getPriceInfo'))) {
            throw new \LogicException(
                sprintf(
                    'Method getPriceInfo does not exists on product object %s',
                    $product::class,
                ),
            );
        }
        /** @var BasePriceInfo $priceInfo */
        $priceInfo = $product->getPriceInfo();
        /** @var FinalPrice $price */
        $price = $priceInfo->getPrice(FinalPrice::PRICE_CODE);

        return $this->getMinProduct($price);
    }

    /**
     * @param FinalPriceInterface $price
     *
     * @return ProductInterface|null
     */
    private function getMinProduct(FinalPriceInterface $price): ?ProductInterface
    {
        if (!(method_exists($price, 'getMinProduct'))) {
            return null;
        }
        // Magento has getMinProduct type hinted as always returning Product,
        // though it can return null if all child products are disabled.
        /** @var ProductInterface|null $product */
        $product = $price->getMinProduct();

        return $product;
    }
}
