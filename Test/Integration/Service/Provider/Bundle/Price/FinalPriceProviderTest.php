<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Test\Integration\Service\Provider\Bundle\Price;

use Klevu\IndexingApi\Service\Provider\Bundle\Price\FinalPriceProviderInterface;
use Klevu\IndexingProducts\Service\Provider\Bundle\Price\FinalPriceProvider;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * @covers FinalPriceProvider
 * @method FinalPriceProviderInterface instantiateTestObject(?array $arguments = null)
 * @method FinalPriceProviderInterface instantiateTestObjectFromInterface(?array $arguments = null)
 */
class FinalPriceProviderTest extends TestCase
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

        $this->implementationFqcn = FinalPriceProvider::class;
        $this->interfaceFqcn = FinalPriceProviderInterface::class;
        $this->objectManager = Bootstrap::getObjectManager();
    }
}
