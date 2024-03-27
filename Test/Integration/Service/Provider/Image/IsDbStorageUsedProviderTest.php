<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Test\Integration\Service\Provider\Image;

use Klevu\IndexingApi\Service\Provider\Image\IsDbStorageUsedProviderInterface;
use Klevu\IndexingProducts\Service\Provider\Image\IsDbStorageUsedImageProvider;
use Klevu\TestFixtures\Catalog\ProductTrait;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Klevu\TestFixtures\Traits\TestInterfacePreferenceTrait;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use TddWizard\Fixtures\Catalog\ProductFixturePool;

/**
 * @covers IsDbStorageUsedProvider
 * @method IsDbStorageUsedProviderInterface instantiateTestObject(?array $arguments = null)
 * @method IsDbStorageUsedProviderInterface instantiateTestObjectFromInterface(?array $arguments = null)
 */
class IsDbStorageUsedProviderTest extends TestCase
{
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

        $this->implementationFqcn = IsDbStorageUsedImageProvider::class;
        $this->interfaceFqcn = IsDbStorageUsedProviderInterface::class;
        $this->objectManager = Bootstrap::getObjectManager();
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
    }

    /**
     * @magentoConfigFixture default/system/media_storage_configuration/media_storage 0
     */
    public function testGet_ReturnsFalse_WhenDbIsNotUsed(): void
    {
        $provider = $this->instantiateTestObject();
        $this->assertFalse(condition: $provider->get());
    }

    /**
     * @magentoConfigFixture default/system/media_storage_configuration/media_storage 1
     */
    public function testGet_ReturnsTrue_WhenDbIsUsed(): void
    {
        $provider = $this->instantiateTestObject();
        $this->assertTrue(condition: $provider->get());
    }
}
