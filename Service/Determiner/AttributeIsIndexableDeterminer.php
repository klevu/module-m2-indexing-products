<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Service\Determiner;

use Klevu\Configuration\Service\Provider\ScopeProviderInterface;
use Klevu\IndexingApi\Model\MagentoAttributeInterface;
use Klevu\IndexingApi\Model\Source\IndexType;
use Klevu\IndexingApi\Service\Determiner\IsAttributeIndexableDeterminerInterface;
use Magento\Catalog\Api\Data\ProductAttributeInterface;
use Magento\Eav\Api\Data\AttributeInterface;
use Magento\Store\Api\Data\StoreInterface;
use Psr\Log\LoggerInterface;

class AttributeIsIndexableDeterminer implements IsAttributeIndexableDeterminerInterface
{
    /**
     * @var LoggerInterface
     */
    private readonly LoggerInterface $logger;
    /**
     * @var ScopeProviderInterface
     */
    private readonly ScopeProviderInterface $scopeProvider;

    /**
     * @param LoggerInterface $logger
     * @param ScopeProviderInterface $scopeProvider
     */
    public function __construct(
        LoggerInterface $logger,
        ScopeProviderInterface $scopeProvider,
    ) {
        $this->logger = $logger;
        $this->scopeProvider = $scopeProvider;
    }

    /**
     * @param AttributeInterface $attribute
     * @param StoreInterface $store
     *
     * @return bool
     */
    public function execute(AttributeInterface $attribute, StoreInterface $store): bool
    {
        if (!($attribute instanceof ProductAttributeInterface)) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Invalid argument provided for "$attribute". Expected %s, received %s.',
                    ProductAttributeInterface::class,
                    get_debug_type($attribute),
                ),
            );
        }

        return $this->isIndexable(attribute: $attribute, store: $store);
    }

    /**
     * @param ProductAttributeInterface $attribute
     * @param StoreInterface $store
     *
     * @return bool
     */
    private function isIndexable(ProductAttributeInterface $attribute, StoreInterface $store): bool
    {
        $indexAs = (int)$attribute->getData( //@phpstan-ignore-line
            key: MagentoAttributeInterface::ATTRIBUTE_PROPERTY_IS_INDEXABLE,
        );
        $isIndexable = $indexAs !== IndexType::NO_INDEX->value;

        if (!$isIndexable) {
            $currentScope = $this->scopeProvider->getCurrentScope();
            $this->scopeProvider->setCurrentScope(scope: $store);
            $this->logger->debug(
                // phpcs:ignore Generic.Files.LineLength.TooLong
                message: 'Store ID: {storeId} Attribute ID: {attributeId} not indexable due to Klevu Index: {indexAs} in {method}',
                context: [
                    'storeId' => $store->getId(),
                    'attributeId' => $attribute->getAttributeId(),
                    'indexAs' => IndexType::NO_INDEX->label(),
                    'method' => __METHOD__,
                ],
            );
            if ($currentScope->getScopeObject()) {
                $this->scopeProvider->setCurrentScope(scope: $currentScope->getScopeObject());
            } else {
                $this->scopeProvider->unsetCurrentScope();
            }
        }

        return $isIndexable;
    }
}
