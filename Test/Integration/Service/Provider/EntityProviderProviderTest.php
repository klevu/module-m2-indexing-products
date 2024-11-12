<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Test\Integration\Service\Provider;

use Klevu\Indexing\Service\Provider\EntityProviderProvider;
use Klevu\IndexingApi\Service\Provider\EntityProviderProviderInterface;
use Klevu\IndexingProducts\Service\Provider\EntityProviderProvider as EntityProviderProviderVirtualType;
use Klevu\IndexingProducts\Service\Provider\ProductEntityProvider;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * @covers EntityProviderProvider::class
 * @method EntityProviderProviderInterface instantiateTestObject(?array $arguments = null)
 * @method EntityProviderProviderInterface instantiateTestObjectFromInterface(?array $arguments = null)
 */
class EntityProviderProviderTest extends TestCase
{
    use ObjectInstantiationTrait;
    use TestImplementsInterfaceTrait;

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

        $this->implementationFqcn = EntityProviderProviderVirtualType::class; // @phpstan-ignore-line
        $this->interfaceFqcn = EntityProviderProviderInterface::class;
        $this->implementationForVirtualType = EntityProviderProvider::class;

        $this->objectManager = Bootstrap::getObjectManager();
    }

    public function testGet_ReturnsArrayOfProviders(): void
    {
        $provider = $this->instantiateTestObject();
        $result = $provider->get();

        $this->assertNotEmpty($result);
        $keys = [
            'simple',
            'virtual',
            'downloadable',
            'grouped',
            'bundle',
            'configurable',
            'configurable_variants',
        ];
        foreach ($keys as $key) {
            $this->assertArrayHasKey(key: $key, array: $result);
            $this->assertInstanceOf(expected: ProductEntityProvider::class, actual: $result[$key]);
        }
    }
}
