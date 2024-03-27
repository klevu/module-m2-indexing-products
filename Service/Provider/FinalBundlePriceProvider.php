<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Service\Provider;

use Magento\Bundle\Pricing\Price\FinalPriceInterface;
use Magento\Catalog\Api\Data\ProductInterface;
use Psr\Log\LoggerInterface;

class FinalBundlePriceProvider implements BundlePriceTypeProviderInterface
{
    /**
     * @var LoggerInterface
     */
    private readonly LoggerInterface $logger;

    /**
     * @param LoggerInterface $logger
     */
    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
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
        $priceInfo = $product->getPriceInfo();
        $finalPrice = $priceInfo->getPrice('final_price');
        if (!($finalPrice instanceof FinalPriceInterface)) {
            $this->logger->error(
                message: 'Method: {method}, Error: {message}',
                context: [
                    'method' => __METHOD__,
                    'message' => sprintf(
                        'getPrice("final_price") did not return instance of %s, for product id %s',
                        FinalPriceInterface::class,
                        $product->getId(),
                    ),
                ],
            );

            return null;
        }
        if (!method_exists($finalPrice, 'getValue')) {
            $this->logger->error(
                message: 'Method: {method}, Error: {message}',
                context: [
                    'method' => __METHOD__,
                    'message' => sprintf(
                        'Method getValue does not exist in %s for product id %s',
                        $finalPrice::class,
                        $product->getId(),
                    ),
                ],
            );

            return null;
        }

        return (float)$finalPrice->getValue();
    }
}
