<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Pricing\Bundle\Adjustment;

use Magento\Bundle\Pricing\Adjustment\Calculator as BundleAdjustmentCalculator;
use Magento\Bundle\Pricing\Adjustment\SelectionPriceListProviderInterface;
use Magento\Bundle\Pricing\Price\BundleSelectionFactory;
use Magento\Catalog\Model\Product;
use Magento\Customer\Model\Session;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Pricing\Adjustment\Calculator as CalculatorBase;
use Magento\Framework\Pricing\Amount\AmountFactory;
use Magento\Framework\Pricing\Amount\AmountInterface;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Tax\Helper\Data as TaxHelper;

/**
 * Class is not intended to be used as a preference for Magento\Bundle\Pricing\Adjustment\Calculator.
 * It is only used in Klevu indexing to get the customer group prices min and max prices.
 * Without this change then the same min and max prices are returned for every customer group.
 * Class is injected directly into
 *  Klevu\IndexingProducts\Service\Provider\Bundle\Price\FinalPriceProvider
 * via module-m2-indexing-products/etc/di.xml
 */
class Calculator extends BundleAdjustmentCalculator
{
    /**
     * @var Session
     */
    private readonly Session $customerSession;
    /**
     * @var AmountInterface[]
     */
    private array $optionAmount = [];

    /**
     * @param CalculatorBase $calculator
     * @param AmountFactory $amountFactory
     * @param BundleSelectionFactory $bundleSelectionFactory
     * @param TaxHelper $taxHelper
     * @param PriceCurrencyInterface $priceCurrency
     * @param SelectionPriceListProviderInterface $selectionPriceListProvider
     * @param Session $customerSession
     */
    public function __construct(
        CalculatorBase $calculator,
        AmountFactory $amountFactory,
        BundleSelectionFactory $bundleSelectionFactory,
        TaxHelper $taxHelper,
        PriceCurrencyInterface $priceCurrency,
        SelectionPriceListProviderInterface $selectionPriceListProvider,
        Session $customerSession,
    ) {
        parent::__construct(
            calculator: $calculator,
            amountFactory: $amountFactory,
            bundleSelectionFactory: $bundleSelectionFactory,
            taxHelper: $taxHelper,
            priceCurrency: $priceCurrency,
            selectionPriceListProvider: $selectionPriceListProvider,
        );

        $this->customerSession = $customerSession;
    }

    // @phpcs:disable SlevomatCodingStandard.TypeHints.ParameterTypeHint.MissingNativeTypeHint
    /**
     * Option amount calculation for bundle product
     * Override parent method and add customer group id to cache key,
     * otherwise the same value is always returned for min and max price no matter which customer group is set.
     *
     * @param Product $saleableItem
     * @param null $exclude
     * @param bool $searchMin
     * @param float $baseAmount
     * @param bool $useRegularPrice
     *
     * @return AmountInterface
     * @throws LocalizedException
     * @throws NoSuchEntityException
     * @see BundleAdjustmentCalculator::getOptionsAmount
     */
    public function getOptionsAmount(
        Product $saleableItem,
        $exclude = null,
        $searchMin = true,
        $baseAmount = 0.0,
        $useRegularPrice = false,
    ): AmountInterface {
        // @phpcs:enable SlevomatCodingStandard.TypeHints.ParameterTypeHint.MissingNativeTypeHint
        // Klevu customisation begins - amend cache key to include customerGroupId
        $groupId = $this->customerSession->getCustomerGroupId();
        $cacheKey = implode(
            separator: '-',
            array: [$saleableItem->getId(), $exclude, $searchMin, $baseAmount, $useRegularPrice, $groupId],
        );
        // Klevu customisation ends
        if (!isset($this->optionAmount[$cacheKey])) {
            $this->optionAmount[$cacheKey] = $this->calculateBundleAmount(
                basePriceValue: $baseAmount,
                bundleProduct: $saleableItem,
                selectionPriceList: $this->getSelectionAmounts($saleableItem, $searchMin, $useRegularPrice),
                exclude: $exclude,
            );
        }

        return $this->optionAmount[$cacheKey];
    }
}
