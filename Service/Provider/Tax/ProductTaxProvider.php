<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Service\Provider\Tax;

use Klevu\IndexingApi\Service\Provider\Tax\ProductTaxProviderInterface;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\AbstractModel;
use Magento\Customer\Api\GroupRepositoryInterface as CustomerGroupRepositoryInterface;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Tax\Model\Calculation as TaxCalculation;
use Magento\Tax\Model\Config as TaxConfig;

class ProductTaxProvider implements ProductTaxProviderInterface
{
    public const TAX_CLASS_ID = 'tax_class_id';

    /**
     * @var TaxCalculation
     */
    private readonly TaxCalculation $taxCalculation;
    /**
     * @var TaxConfig
     */
    private readonly TaxConfig $taxConfig;
    /**
     * @var CustomerGroupRepositoryInterface
     */
    private readonly CustomerGroupRepositoryInterface $customerGroupRepository;
    /**
     * @var int
     */
    private int $displayBothPricesAs;
    /**
     * @var bool[]
     */
    private array $priceIncludesTax = [];
    /**
     * @var bool[]
     */
    private array $displayIncludesTax = [];

    /**
     * @param TaxCalculation $taxCalculation
     * @param TaxConfig $taxConfig
     * @param CustomerGroupRepositoryInterface $customerGroupRepository
     * @param int $displayBothPricesAs
     *
     * @throws LocalizedException
     */
    public function __construct(
        TaxCalculation $taxCalculation,
        TaxConfig $taxConfig,
        CustomerGroupRepositoryInterface $customerGroupRepository,
        int $displayBothPricesAs = TaxConfig::DISPLAY_TYPE_INCLUDING_TAX,
    ) {
        $this->taxCalculation = $taxCalculation;
        $this->taxConfig = $taxConfig;
        $this->customerGroupRepository = $customerGroupRepository;
        $this->setDisplayBothPricesAs($displayBothPricesAs);
    }

    /**
     * @param ProductInterface $product
     * @param float $price
     * @param int|null $customerGroupId
     *
     * @return float
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function get(ProductInterface $product, float $price, ?int $customerGroupId): float
    {
        /** @var AbstractModel&ProductInterface $product */
        $store = $product->getStore();
        $storeId = (string)$store->getId();

        if ($this->isTaxRequiredInDisplayPrices($storeId) !== $this->isTaxIncludedInCatalogPrices($storeId)) {
            $taxAmount = $this->getTaxAmount(
                product: $product,
                price: $price,
                customerGroupId: $customerGroupId,
            );
            $price = $this->isTaxRequiredInDisplayPrices($storeId)
                ? $price + $taxAmount
                : $price - $taxAmount;
        }

        return round(num: $price, precision: 2);
    }

    /**
     * Ensure value is valid
     *
     * @param int $displayBothPricesAs
     *
     * @return void
     * @throws LocalizedException
     */
    private function setDisplayBothPricesAs(int $displayBothPricesAs): void
    {
        if (
            !in_array(
                needle: $displayBothPricesAs,
                haystack: [TaxConfig::DISPLAY_TYPE_EXCLUDING_TAX, TaxConfig::DISPLAY_TYPE_INCLUDING_TAX],
                strict: true,
            )
        ) {
            throw new LocalizedException(
                __(
                    'Invalid value for $displayBothPricesAs in %1. Expected one of (%2), Received %3',
                    self::class,
                    implode(
                        separator: ', ',
                        array: [TaxConfig::DISPLAY_TYPE_EXCLUDING_TAX, TaxConfig::DISPLAY_TYPE_INCLUDING_TAX],
                    ),
                    $displayBothPricesAs,
                ),
            );
        }
        $this->displayBothPricesAs = $displayBothPricesAs;
    }

    /**
     * @param ProductInterface $product
     * @param float $price
     * @param int|null $customerGroupId
     *
     * @return float
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    private function getTaxAmount(ProductInterface $product, float $price, ?int $customerGroupId): float
    {
        /** @var AbstractModel&ProductInterface $product */
        $store = $product->getStore();
        $customerTaxClass = $this->getCustomerTaxClass(store: $store, customerGroupId: $customerGroupId);

        /** @var DataObject $request */
        $request = $this->taxCalculation->getRateRequest(
            customerTaxClass: $customerTaxClass,
            store: $store,
        );
        $request->setData(
            key: 'product_class_id',
            value: $product->getData(key: static::TAX_CLASS_ID),
        );
        $taxRate = $this->taxCalculation->getRate(request: $request);

        return $this->taxCalculation->calcTaxAmount(
            price: $price,
            taxRate: $taxRate,
            priceIncludeTax: $this->isTaxIncludedInCatalogPrices(storeId: (string)$store->getId()),
            round: true,
        );
    }

    /**
     * @param StoreInterface $store
     * @param int|null $customerGroupId
     *
     * @return int
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    private function getCustomerTaxClass(StoreInterface $store, ?int $customerGroupId): int
    {
        if (null !== $customerGroupId) {
            $customerGroup = $this->customerGroupRepository->getById(id: $customerGroupId);
            $customerTaxClass = $customerGroup->getTaxClassId();
        } else {
            $customerTaxClass = $this->taxCalculation->getDefaultCustomerTaxClass(store: (int)$store->getId());
        }

        return (int)$customerTaxClass;
    }

    /**
     * @param string $storeId
     *
     * @return bool
     */
    private function isTaxIncludedInCatalogPrices(string $storeId): bool
    {
        if (null === ($this->priceIncludesTax[$storeId] ?? null)) {
            $this->priceIncludesTax[$storeId] = $this->taxConfig->priceIncludesTax(store: $storeId);
        }

        return $this->priceIncludesTax[$storeId];
    }

    /**
     * @param string $storeId
     *
     * @return bool
     */
    private function isTaxRequiredInDisplayPrices(string $storeId): bool
    {
        if (null === ($this->displayIncludesTax[$storeId] ?? null)) {
            $displayTax = (int)$this->taxConfig->getPriceDisplayType(store: $storeId);
            if ($displayTax === TaxConfig::DISPLAY_TYPE_BOTH) {
                /**
                 * Klevu JSON Indexing can only accept one price value.
                 *  So if TaxConfig::CONFIG_XML_PATH_PRICE_DISPLAY_TYPE is set to TaxConfig::DISPLAY_TYPE_BOTH,
                 *  we treat it as if it were $this->displayBothPricesAs, which can be changed via di.xml
                 */
                $displayTax = $this->displayBothPricesAs;
            }
            $this->displayIncludesTax[$storeId] = $displayTax !== TaxConfig::DISPLAY_TYPE_EXCLUDING_TAX;
        }

        return $this->displayIncludesTax[$storeId];
    }
}
