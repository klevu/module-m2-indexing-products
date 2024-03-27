<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Test\Integration\Service\Provider;

use Klevu\Indexing\Service\Provider\DefaultIndexingAttributesProvider;
use Klevu\IndexingApi\Model\Source\IndexType;
use Klevu\IndexingApi\Service\Provider\DefaultIndexingAttributesProviderInterface;
use Klevu\IndexingProducts\Model\Attribute\KlevuImageInterface;
use Klevu\IndexingProducts\Service\Provider\DefaultIndexingAttributesProvider as DefaultIndexingAttributesProviderVirtualType; // phpcs:ignore Generic.Files.LineLength.TooLong
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Magento\Catalog\Api\Data\ProductAttributeInterface;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Klevu\Indexing\Service\Provider\DefaultIndexingAttributesProvider::class
 * @method DefaultIndexingAttributesProviderInterface instantiateTestObject(?array $arguments = null)
 * @method DefaultIndexingAttributesProviderInterface instantiateTestObjectFromInterface(?array $arguments = null)
 */
class DefaultIndexingAttributesProviderTest extends TestCase
{
    use ObjectInstantiationTrait;
    use TestImplementsInterfaceTrait;

    /**
     * @var ObjectManagerInterface|null
     */
    private ?ObjectManagerInterface $objectManager = null; //@phpstan-ignore-line

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->objectManager = Bootstrap::getObjectManager();

        $this->implementationFqcn = DefaultIndexingAttributesProviderVirtualType::class;
        $this->interfaceFqcn = DefaultIndexingAttributesProviderInterface::class;
        $this->implementationForVirtualType = DefaultIndexingAttributesProvider::class;
    }

    public function testGet_ReturnsAttributeList(): void
    {
        $provider = $this->instantiateTestObject();
        $attributes = $provider->get();

        $this->assertArrayHasKey(key: ProductAttributeInterface::CODE_DESCRIPTION, array: $attributes);
        $this->assertSame(
            expected: IndexType::INDEX,
            actual: $attributes[ProductAttributeInterface::CODE_DESCRIPTION],
        );

        $this->assertArrayHasKey(key: KlevuImageInterface::ATTRIBUTE_CODE, array: $attributes);
        $this->assertSame(expected: IndexType::INDEX, actual: $attributes[KlevuImageInterface::ATTRIBUTE_CODE]);

        $this->assertArrayHasKey(key: ProductInterface::NAME, array: $attributes);
        $this->assertSame(expected: IndexType::INDEX, actual: $attributes[ProductInterface::NAME]);

        $this->assertArrayHasKey(key: ProductInterface::PRICE, array: $attributes);
        $this->assertSame(expected: IndexType::INDEX, actual: $attributes[ProductInterface::PRICE]);

        // @TODO re-enable once rating attributes have been created
//        $this->assertArrayHasKey(key: KlevuRatingInterface::ATTRIBUTE_CODE, array: $attributes);
//        $this->assertSame(expected: IndexType::INDEX, actual: $attributes[KlevuRatingInterface::ATTRIBUTE_CODE]);
//
//        $this->assertArrayHasKey(key: KlevuRatingCountInterface::ATTRIBUTE_CODE, array: $attributes);
//        $this->assertSame(expected: IndexType::INDEX, actual: $attributes[KlevuRatingCountInterface::ATTRIBUTE_CODE]);

        $this->assertArrayHasKey(key: ProductAttributeInterface::CODE_SHORT_DESCRIPTION, array: $attributes);
        $this->assertSame(
            expected: IndexType::INDEX,
            actual: $attributes[ProductAttributeInterface::CODE_SHORT_DESCRIPTION],
        );

        $this->assertArrayHasKey(key: ProductInterface::SKU, array: $attributes);
        $this->assertSame(expected: IndexType::INDEX, actual: $attributes[ProductInterface::SKU]);

        $this->assertArrayHasKey(key: ProductInterface::VISIBILITY, array: $attributes);
        $this->assertSame(expected: IndexType::INDEX, actual: $attributes[ProductInterface::VISIBILITY]);

        $this->assertArrayHasKey(key: ProductAttributeInterface::CODE_SEO_FIELD_URL_KEY, array: $attributes);
        $this->assertSame(
            expected: IndexType::INDEX,
            actual: $attributes[ProductAttributeInterface::CODE_SEO_FIELD_URL_KEY],
        );
    }
}
