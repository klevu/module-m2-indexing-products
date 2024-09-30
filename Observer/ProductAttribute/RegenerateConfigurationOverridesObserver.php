<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Observer\ProductAttribute;

use Klevu\PlatformPipelines\Api\ConfigurationOverridesHandlerInterface;
use Magento\Catalog\Api\Data\ProductAttributeInterface;
use Magento\Catalog\Model\ResourceModel\Eav\Attribute as EavAttribute;
use Magento\Eav\Model\ConfigFactory as EavConfigFactory;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\LocalizedException;
use Psr\Log\LoggerInterface;

class RegenerateConfigurationOverridesObserver implements ObserverInterface
{
    /**
     * @var LoggerInterface
     */
    private readonly LoggerInterface $logger;
    /**
     * @var EavConfigFactory
     */
    private readonly EavConfigFactory $eavConfigFactory;
    /**
     * @var ConfigurationOverridesHandlerInterface[]
     */
    private array $configurationOverridesHandlers = [];

    /**
     * @param LoggerInterface $logger
     * @param EavConfigFactory $eavConfigFactory
     * @param ConfigurationOverridesHandlerInterface[] $configurationOverridesHandlers
     */
    public function __construct(
        LoggerInterface $logger,
        EavConfigFactory $eavConfigFactory,
        array $configurationOverridesHandlers,
    ) {
        $this->logger = $logger;
        $this->eavConfigFactory = $eavConfigFactory;
        array_walk($configurationOverridesHandlers, [$this, 'addConfigurationOverridesHandler']);
    }

    /**
     * @param Observer $observer
     *
     * @return void
     */
    public function execute(Observer $observer): void
    {
        $event = $observer->getEvent();
        $attribute = $event->getData(key: 'attribute');
        if (!($attribute instanceof EavAttribute)) {
            return;
        }
        if ((int)$attribute->getData('entity_type_id') !== $this->getEntityTypeId()) {
            return;
        }

        foreach ($this->configurationOverridesHandlers as $configurationOverridesHandler) {
            $configurationOverridesHandler->execute();
        }
    }

    /**
     * @param ConfigurationOverridesHandlerInterface $configurationOverridesHandler
     * @param string $identifier
     *
     * @return void
     */
    private function addConfigurationOverridesHandler(
        ConfigurationOverridesHandlerInterface $configurationOverridesHandler,
        string $identifier,
    ): void {
        $this->configurationOverridesHandlers[$identifier] = $configurationOverridesHandler;
    }

    /**
     * @return int|null
     */
    private function getEntityTypeId(): ?int
    {
        $eavConfig = $this->eavConfigFactory->create();
        try {
            $entityType = $eavConfig->getEntityType(ProductAttributeInterface::ENTITY_TYPE_CODE);
        } catch (LocalizedException $exception) {
            $this->logger->error(
                message: 'Method: {method}, Error: {message}',
                context: [
                    'method' => __METHOD__,
                    'message' => $exception->getMessage(),
                ],
            );
            return null;
        }

        return $entityType->getEntityTypeId()
            ? (int)$entityType->getEntityTypeId()
            : null;
    }
}
