<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Service\Provider;

use Magento\Catalog\Api\Data\ProductInterface;

class BundlePriceProvider implements BundlePriceProviderInterface
{
    /**
     * @var BundlePriceTypeProviderInterface
     */
    private readonly BundlePriceTypeProviderInterface $finalBundlePriceProvider;
    /**
     * @var BundlePriceTypeProviderInterface
     */
    private readonly BundlePriceTypeProviderInterface $minBundlePriceProvider;
    /**
     * @var BundlePriceTypeProviderInterface
     */
    private readonly BundlePriceTypeProviderInterface $maxBundlePriceProvider;
    /**
     * @var BundlePriceTypeProviderInterface
     */
    private readonly BundlePriceTypeProviderInterface $regularBundlePriceProvider;

    /**
     * @param BundlePriceTypeProviderInterface $finalBundlePriceProvider
     * @param BundlePriceTypeProviderInterface $minBundlePriceProvider
     * @param BundlePriceTypeProviderInterface $maxBundlePriceProvider
     * @param BundlePriceTypeProviderInterface $regularBundlePriceProvider
     */
    public function __construct(
        BundlePriceTypeProviderInterface $finalBundlePriceProvider,
        BundlePriceTypeProviderInterface $minBundlePriceProvider,
        BundlePriceTypeProviderInterface $maxBundlePriceProvider,
        BundlePriceTypeProviderInterface $regularBundlePriceProvider,
    ) {
        $this->finalBundlePriceProvider = $finalBundlePriceProvider;
        $this->minBundlePriceProvider = $minBundlePriceProvider;
        $this->maxBundlePriceProvider = $maxBundlePriceProvider;
        $this->regularBundlePriceProvider = $regularBundlePriceProvider;
    }

    /**
     * @param ProductInterface $product
     * @param string $priceType
     *
     * @return float|null
     * @throws \InvalidArgumentException
     */
    public function get(ProductInterface $product, string $priceType): ?float
    {
        return match ($priceType) {
            'final_price' => $this->finalBundlePriceProvider->get(product: $product),
            'max_final_price' => $this->maxBundlePriceProvider->get(product: $product),
            'min_final_price' => $this->minBundlePriceProvider->get(product: $product),
            'regular_price' => $this->regularBundlePriceProvider->get(product: $product),
            default => throw new \InvalidArgumentException(
                sprintf(
                    'Invalid Price Type provided. Received %s, expected one of %s',
                    $priceType,
                    implode(', ', ['min_final_price', 'max_final_price', 'final_price', 'regular_price']),
                ),
            ),
        };
    }
}
