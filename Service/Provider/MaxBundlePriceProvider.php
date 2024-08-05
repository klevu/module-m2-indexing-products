<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Service\Provider;

use Klevu\IndexingApi\Service\Provider\Bundle\Price\FinalPriceProviderInterface;
use Magento\Catalog\Api\Data\ProductInterface;
use Psr\Log\LoggerInterface;

class MaxBundlePriceProvider implements BundlePriceTypeProviderInterface
{
    /**
     * @var LoggerInterface
     */
    private readonly LoggerInterface $logger;
    /**
     * @var FinalPriceProviderInterface
     */
    private readonly FinalPriceProviderInterface $finalPriceProvider;

    /**
     * @param LoggerInterface $logger
     * @param FinalPriceProviderInterface $finalPriceProvider
     */
    public function __construct(
        LoggerInterface $logger,
        FinalPriceProviderInterface $finalPriceProvider,
    ) {
        $this->logger = $logger;
        $this->finalPriceProvider = $finalPriceProvider;
    }

    /**
     * @param ProductInterface $product
     *
     * @return float|null
     */
    public function get(ProductInterface $product): ?float
    {
        if (!method_exists($product, 'getPriceInfo')) {
            $this->logger->error(
                message: 'Method: {method}, Error: {message}',
                context: [
                    'method' => __METHOD__,
                    'message' => sprintf(
                        'Method getPriceInfo does not exist in %s for product id %s',
                        $product::class,
                        $product->getId(),
                    ),
                ],
            );

            return null;
        }
        $finalPrice = $this->finalPriceProvider->get(product: $product);
        $maximalPrice = $finalPrice->getMaximalPrice();

        return (float)$maximalPrice->getValue();
    }
}
