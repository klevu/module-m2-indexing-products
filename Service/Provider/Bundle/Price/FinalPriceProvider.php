<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Service\Provider\Bundle\Price;

use Klevu\IndexingApi\Service\Provider\Bundle\Price\FinalPriceProviderInterface;
use Magento\Bundle\Pricing\Adjustment\BundleCalculatorInterface;
use Magento\Bundle\Pricing\Price\FinalPriceFactory;
use Magento\Bundle\Pricing\Price\FinalPriceInterface;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductCustomOptionRepositoryInterface;
use Magento\Framework\Pricing\PriceCurrencyInterface;

class FinalPriceProvider implements FinalPriceProviderInterface
{
    private const QTY_FOR_CUSTOMER_GROUP_PRICE_CALCULATION = 1.0;

    /**
     * @var FinalPriceFactory
     */
    private readonly FinalPriceFactory $finalPriceFactory;
    /**
     * @var BundleCalculatorInterface
     */
    private readonly BundleCalculatorInterface $calculator;
    /**
     * @var PriceCurrencyInterface
     */
    private readonly PriceCurrencyInterface $priceCurrency;
    /**
     * @var ProductCustomOptionRepositoryInterface
     */
    private readonly ProductCustomOptionRepositoryInterface $productOptionRepository;

    /**
     * @param FinalPriceFactory $finalPriceFactory
     * @param BundleCalculatorInterface $calculator
     * @param PriceCurrencyInterface $priceCurrency
     * @param ProductCustomOptionRepositoryInterface $productOptionRepository
     */
    public function __construct(
        FinalPriceFactory $finalPriceFactory,
        BundleCalculatorInterface $calculator,
        PriceCurrencyInterface $priceCurrency,
        ProductCustomOptionRepositoryInterface $productOptionRepository,
    ) {
        $this->finalPriceFactory = $finalPriceFactory;
        $this->calculator = $calculator;
        $this->priceCurrency = $priceCurrency;
        $this->productOptionRepository = $productOptionRepository;
    }

    /**
     * @param ProductInterface $product
     *
     * @return FinalPriceInterface
     */
    public function get(ProductInterface $product): FinalPriceInterface
    {
        $qty = method_exists($product, 'getQty')
            ? $product->getQty()
            : null;

        return $this->finalPriceFactory->create([
            'saleableItem' => $product,
            'quantity' => (float)($qty ?? self::QTY_FOR_CUSTOMER_GROUP_PRICE_CALCULATION),
            'calculator' => $this->calculator,
            'priceCurrency' => $this->priceCurrency,
            'productOptionRepository' => $this->productOptionRepository,
        ]);
    }
}
