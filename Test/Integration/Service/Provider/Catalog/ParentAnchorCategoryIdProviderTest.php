<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Test\Integration\Service\Provider\Catalog;

use Klevu\IndexingApi\Service\Provider\Catalog\ParentAnchorCategoryIdProviderInterface;
use Klevu\IndexingProducts\Service\Provider\Catalog\ParentAnchorCategoryIdProvider;
use Klevu\TestFixtures\Catalog\CategoryTrait;
use Klevu\TestFixtures\Catalog\ProductTrait;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Klevu\TestFixtures\Traits\TestInterfacePreferenceTrait;
use Magento\Catalog\Model\Product;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use TddWizard\Fixtures\Catalog\CategoryFixturePool;
use TddWizard\Fixtures\Catalog\ProductFixturePool;

/**
 * @covers ParentAnchorCategoryIdProvider
 * @method ParentAnchorCategoryIdProviderInterface instantiateTestObject(?array $arguments = null)
 * @method ParentAnchorCategoryIdProviderInterface instantiateTestObjectFromInterface(?array $arguments = null)
 */
class ParentAnchorCategoryIdProviderTest extends TestCase
{
    use CategoryTrait;
    use ObjectInstantiationTrait;
    use ProductTrait;
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

        $this->implementationFqcn = ParentAnchorCategoryIdProvider::class; // @phpstan-ignore-line
        $this->interfaceFqcn = ParentAnchorCategoryIdProviderInterface::class;
        $this->objectManager = Bootstrap::getObjectManager();

        $this->categoryFixturePool = $this->objectManager->get(CategoryFixturePool::class);
        $this->productFixturePool = $this->objectManager->get(ProductFixturePool::class);
    }

    /**
     * @return void
     * @throws \Exception
     */
    protected function tearDown(): void
    {
        parent::tearDown();

        $this->productFixturePool->rollback();
        $this->categoryFixturePool->rollback();
    }

    public function testGet_ReturnsEmptyArray_WhenEmptyArrayProvided(): void
    {
        $provider = $this->instantiateTestObject();
        $result = $provider->get([]);

        $this->assertCount(expectedCount: 0, haystack: $result);
    }

    public function testGet_ReturnsArrayOfParentAnchorCategoryIds(): void
    {
        $this->createCategory([
            'key' => 'test_category_level_1',
            'name' => 'Test Category Level 1',
            'is_anchor' => true,
        ]);
        $categoryFixtureLevel1 = $this->categoryFixturePool->get('test_category_level_1');
        $this->createCategory([
            'key' => 'test_category_level_2',
            'name' => 'Test Category Level 2',
            'parent' => $categoryFixtureLevel1,
            'is_anchor' => false,
        ]);
        $categoryFixtureLevel2 = $this->categoryFixturePool->get('test_category_level_2');
        $this->createCategory([
            'key' => 'test_category_level_3',
            'name' => 'Test Category Level 3',
            'parent' => $categoryFixtureLevel2,
            'is_anchor' => true,
        ]);
        $categoryFixtureLevel3 = $this->categoryFixturePool->get('test_category_level_3');
        $this->createCategory([
            'key' => 'test_category_level_4',
            'name' => 'Test Category Level 4',
            'parent' => $categoryFixtureLevel3,
            'is_anchor' => true,
        ]);
        $categoryFixtureLevel4 = $this->categoryFixturePool->get('test_category_level_4');
        $this->createCategory([
            'key' => 'test_category_level_5',
            'name' => 'Test Category Level 5',
            'parent' => $categoryFixtureLevel4,
            'is_anchor' => false,
        ]);
        $categoryFixtureLevel5 = $this->categoryFixturePool->get('test_category_level_5');

        $this->createProduct([
            'category_ids' => [$categoryFixtureLevel5->getId()],
        ]);
        $productFixture = $this->productFixturePool->get('test_product');
        /** @var Product $product */
        $product = $productFixture->getProduct();

        $provider = $this->instantiateTestObject();
        $result = $provider->get($product->getCategoryIds());

        $this->assertNotContains(
            needle: $categoryFixtureLevel5->getId(),
            haystack: $result,
            message: 'Non-Anchor Category - Assigned to product',
        );
        $this->assertContains(
            needle: $categoryFixtureLevel4->getId(),
            haystack: $result,
            message: 'Anchor Category - parent category',
        );
        $this->assertContains(
            needle: $categoryFixtureLevel3->getId(),
            haystack: $result,
            message: 'Anchor Category - grand parent category',
        );
        $this->assertNotContains(
            needle: $categoryFixtureLevel2->getId(),
            haystack: $result,
            message: 'Non-Anchor Category - great grand parent category',
        );
        $this->assertNotContains(
            needle: $categoryFixtureLevel1->getId(),
            haystack: $result,
            message: 'Top category',
        );
    }
}
