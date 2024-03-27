<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Test\Integration\Service\Provider;

use Klevu\IndexingProducts\Service\Provider\BundlePriceTypeProviderInterface;
use Klevu\IndexingProducts\Service\Provider\RegularBundlePriceProvider;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

class RegularBundlePriceProviderTest extends TestCase
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

        $this->implementationFqcn = RegularBundlePriceProvider::class;
        $this->interfaceFqcn = BundlePriceTypeProviderInterface::class;
        $this->objectManager = Bootstrap::getObjectManager();
    }
}
