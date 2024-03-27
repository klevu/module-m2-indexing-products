<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Test\Integration\Service\Provider;

use Klevu\IndexingApi\Service\Provider\DefaultIndexingAttributesAspectMappingProviderInterface;
use Klevu\IndexingProducts\Model\Source\Aspect;
use Klevu\IndexingProducts\Service\Provider\DefaultIndexingAttributesAspectMappingProvider;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

// phpcs:disable Generic.Files.LineLength.TooLong
/**
 * @covers \Klevu\IndexingProducts\Service\Provider\DefaultIndexingAttributesAspectMappingProvider::class
 * @method DefaultIndexingAttributesAspectMappingProviderInterface instantiateTestObject(?array $arguments = null)
 * @method DefaultIndexingAttributesAspectMappingProviderInterface instantiateTestObjectFromInterface(?array $arguments = null)
 */
class DefaultIndexingAttributesAspectMappingProviderTest extends TestCase
{
    // phpcs:enable Generic.Files.LineLength.TooLong
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

        $this->implementationFqcn = DefaultIndexingAttributesAspectMappingProvider::class;
        $this->interfaceFqcn = DefaultIndexingAttributesAspectMappingProviderInterface::class;
    }

    public function testGet_ReturnsAspectMapping(): void
    {
        $provider = $this->instantiateTestObject();
        $result = $provider->get();

        $this->assertArrayHasKey(key: 'category_ids', array: $result);
        $this->assertSame(expected: $result['category_ids'], actual: Aspect::CATEGORIES);

        $this->assertArrayHasKey(key: 'name', array: $result);
        $this->assertSame(expected: $result['name'], actual: Aspect::ATTRIBUTES);

        $this->assertArrayHasKey(key: 'price', array: $result);
        $this->assertSame(expected: $result['price'], actual: Aspect::PRICE);

        $this->assertArrayHasKey(key: 'quantity_and_stock_status', array: $result);
        $this->assertSame(expected: $result['quantity_and_stock_status'], actual: Aspect::STOCK);

        $this->assertArrayHasKey(key: 'status', array: $result);
        $this->assertSame(expected: $result['status'], actual: Aspect::ATTRIBUTES);

        $this->assertArrayHasKey(key: 'visibility', array: $result);
        $this->assertSame(expected: $result['visibility'], actual: Aspect::VISIBILITY);
    }
}
