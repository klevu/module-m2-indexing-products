<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Test\Integration\Service\Provider\CatalogRule;

use Klevu\Indexing\Test\Integration\Traits\IndexingEntitiesTrait;
use Klevu\IndexingApi\Service\Provider\CatalogRule\CatalogRuleProductIdsProviderInterface;
use Klevu\IndexingProducts\Service\Provider\CatalogRule\CatalogRuleProductIdsProvider;
use Klevu\TestFixtures\Catalog\ProductTrait;
use Klevu\TestFixtures\Catalog\Rule\RuleFixturePool;
use Klevu\TestFixtures\Catalog\RuleTrait;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Klevu\TestFixtures\Traits\TestInterfacePreferenceTrait;
use Magento\CatalogRule\Model\Indexer\IndexBuilder;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use TddWizard\Fixtures\Catalog\ProductFixturePool;

/**
 * @covers \Klevu\IndexingProducts\Service\Provider\CatalogRule\CatalogRuleProductIdsProvider
 * @method CatalogRuleProductIdsProviderInterface instantiateTestObject(?array $arguments = null)
 * @method CatalogRuleProductIdsProviderInterface instantiateTestObjectFromInterface(?array $arguments = null)
 */
class GetProductIdsProviderTest extends TestCase
{
    use IndexingEntitiesTrait;
    use ObjectInstantiationTrait;
    use ProductTrait;
    use RuleTrait;
    use TestImplementsInterfaceTrait;
    use TestInterfacePreferenceTrait;

    /**
     * @var ObjectManagerInterface|null
     */
    private ?ObjectManagerInterface $objectManager = null; // @phpstan-ignore-line

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->implementationFqcn = CatalogRuleProductIdsProvider::class;
        $this->interfaceFqcn = CatalogRuleProductIdsProviderInterface::class;
        $this->objectManager = Bootstrap::getObjectManager();
        $this->productFixturePool = $this->objectManager->get(ProductFixturePool::class);
        $this->ruleFixturePool = $this->objectManager->get(RuleFixturePool::class);
    }

    /**
     * @return void
     * @throws \Exception
     */
    protected function tearDown(): void
    {
        parent::tearDown();

        $this->ruleFixturePool->rollback();
        $this->productFixturePool->rollback();
    }

    /**
     * @magentoDbIsolation disabled
     */
    public function testGet_ReturnsProductsIds_FromCatalogRule(): void
    {
        $sku = 'KlevuTest' . random_int(0, 9999999);
        $this->createProduct(
            productData: [
                'sku' => $sku,
            ],
        );
        $productFixture = $this->productFixturePool->get('test_product');

        $this->createRule([
            'conditions' => [
                [
                    'attribute' => 'sku',
                    'value' => $sku,
                ],
            ],
        ]);

        $provider = $this->instantiateTestObject();

        $indexerBuilder = $this->objectManager->get(IndexBuilder::class);
        $indexerBuilder->reindexById((int)$productFixture->getId());

        $productsIds = $provider->get();
        $this->assertContains(needle: (int)$productFixture->getId(), haystack: $productsIds);
    }
}
