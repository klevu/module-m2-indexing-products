<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Plugin\Catalog\Model\Product\Action;

use Klevu\Configuration\Model\CurrentScopeInterfaceFactory;
use Klevu\Configuration\Service\Provider\ApiKeyProviderInterface;
use Klevu\IndexingApi\Service\EntityDiscoveryOrchestratorServiceInterface;
use Magento\Catalog\Model\Product\Action as ProductAction;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Api\Data\WebsiteInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

class UpdateWebsitesPlugin
{
    /**
     * @var LoggerInterface
     */
    private readonly LoggerInterface $logger;
    /**
     * @var StoreManagerInterface
     */
    private readonly StoreManagerInterface $storeManager;
    /**
     * @var CurrentScopeInterfaceFactory
     */
    private readonly CurrentScopeInterfaceFactory $currentScopeFactory;
    /**
     * @var ApiKeyProviderInterface
     */
    private readonly ApiKeyProviderInterface $apiKeyProvider;
    /**
     * @var EntityDiscoveryOrchestratorServiceInterface
     */
    private readonly EntityDiscoveryOrchestratorServiceInterface $entityDiscoveryOrchestratorService;

    /**
     * @param LoggerInterface $logger
     * @param StoreManagerInterface $storeManager
     * @param CurrentScopeInterfaceFactory $currentScopeFactory
     * @param ApiKeyProviderInterface $apiKeyProvider
     * @param EntityDiscoveryOrchestratorServiceInterface $entityDiscoveryOrchestratorService
     */
    public function __construct(
        LoggerInterface $logger,
        StoreManagerInterface $storeManager,
        CurrentScopeInterfaceFactory $currentScopeFactory,
        ApiKeyProviderInterface $apiKeyProvider,
        EntityDiscoveryOrchestratorServiceInterface $entityDiscoveryOrchestratorService,
    ) {
        $this->logger = $logger;
        $this->storeManager = $storeManager;
        $this->currentScopeFactory = $currentScopeFactory;
        $this->apiKeyProvider = $apiKeyProvider;
        $this->entityDiscoveryOrchestratorService = $entityDiscoveryOrchestratorService;
    }

    /**
     * @param ProductAction $subject
     * @param null $return
     * @param int[] $productIds
     * @param int[] $websiteIds
     * @param string $type
     *
     * @return null
     */
    public function afterUpdateWebsites(
        ProductAction $subject,
        $return,
        $productIds,
        $websiteIds,
        $type,
    ): mixed {
        if (!$productIds || !$websiteIds) {
            return $return;
        }

        try {
            $affectedApiKeys = $this->getApiKeysForWebsiteIds($websiteIds);
            if (!$affectedApiKeys) {
                return $return;
            }

            $resultsGeneratorGenerator = $this->entityDiscoveryOrchestratorService->execute(
                entityTypes: ['KLEVU_PRODUCT'],
                apiKeys: $affectedApiKeys,
                entityIds: $productIds,
                entitySubtypes: null,
            );
        } catch (\Exception $exception) {
            $this->logger->warning(
                message: 'Could not trigger entity discovery on update websites',
                context: [
                    'method' => __METHOD__,
                    'exception' => $exception::class,
                    'error' => $exception->getMessage(),
                    'productIds' => $productIds,
                    'websiteIds' => $websiteIds,
                    'type' => $type,
                    'affectedApiKeys' => $affectedApiKeys ?? null,
                ],
            );

            return $return;
        }

        $resultIndex = 0;
        foreach ($resultsGeneratorGenerator as $resultsGenerator) {
            foreach ($resultsGenerator as $result) {
                $context = [
                    'method' => __METHOD__,
                    'productIds' => $productIds,
                    'websiteIds' => $websiteIds,
                    'affectedApiKeys' => $affectedApiKeys,
                    'result' => [
                        'index' => $resultIndex++,
                        'messages' => $result->getMessages(),
                        'processedIds' => $result->getProcessedIds(),
                    ],
                ];

                if ($result->isSuccess()) {
                    $this->logger->debug(
                        message: 'Ran entity discovery on update websites',
                        context: $context,
                    );
                } else {
                    $this->logger->warning(
                        message: 'Entity discovery on update websites did not run successfully',
                        context: $context,
                    );
                }
            }
        }

        return $return;
    }

    /**
     * @param int[] $websiteIds
     *
     * @return string[]
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    private function getApiKeysForWebsiteIds(
        array $websiteIds,
    ): array {
        $affectedWebsites = array_map(
            callback: fn ($websiteId): WebsiteInterface => $this->storeManager->getWebsite(
                websiteId: $websiteId,
            ),
            array: $websiteIds,
        );
        $affectedStores = array_map(
            callback: function (WebsiteInterface $website): array {
                if (!method_exists($website, 'getStores')) {
                    $this->logger->warning(
                        message: 'Cannot determine stores for website: WebsiteInterface implementation does not contain getStores',
                        context: [
                            'method' => __METHOD__,
                            'website' => [
                                'id' => $website->getId(),
                                'code' => $website->getCode(),
                            ],
                            'websiteFqcn' => get_debug_type($website),
                        ],
                    );

                    return [];
                }

                return $website->getStores();
            },
            array: $affectedWebsites,
        );

        $apiKeys = array_map(
            callback: fn (StoreInterface $store): ?string => $this->apiKeyProvider->get(
                scope: $this->currentScopeFactory->create(['scopeObject' => $store]),
            ),
            array: array_merge([], ...$affectedStores),
        );

        return array_filter(
            array: array_unique($apiKeys),
        );
    }
}
