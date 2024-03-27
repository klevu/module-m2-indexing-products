<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Service\Provider;

use Magento\Bundle\Pricing\Price\RegularPriceInterface;
use Magento\Catalog\Api\Data\ProductInterface;
use Psr\Log\LoggerInterface;

class RegularBundlePriceProvider implements BundlePriceTypeProviderInterface
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
        $regularPrice = $priceInfo->getPrice('regular_price');
        if (!($regularPrice instanceof RegularPriceInterface)) {
            $this->logger->error(
                message: 'Method: {method}, Error: {message}',
                context: [
                    'method' => __METHOD__,
                    'message' => sprintf(
                        'getPrice("regular_price") did not return instance of %s, for product id %s',
                        RegularPriceInterface::class,
                        $product->getId(),
                    ),
                ],
            );

            return null;
        }
        $minimalPrice = $regularPrice->getMinimalPrice();

        return (float)$minimalPrice->getValue();
    }
}
