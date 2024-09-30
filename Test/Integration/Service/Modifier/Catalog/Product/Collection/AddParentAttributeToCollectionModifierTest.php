<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Test\Integration\Service\Modifier\Catalog\Product\Collection;

use Klevu\IndexingProducts\Service\Modifier\Catalog\Product\Collection\AddParentAttributeToCollectionModifier;
use Klevu\IndexingProducts\Service\Modifier\Catalog\Product\Collection\AddParentAttributeToCollectionModifierInterface;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * @covers AddParentAttributeToCollectionModifier::class
 * @method AddParentAttributeToCollectionModifierInterface instantiateTestObject(?array $arguments = null)
 * @method AddParentAttributeToCollectionModifierInterface instantiateTestObjectFromInterface(?array $arguments = null)
 */
class AddParentAttributeToCollectionModifierTest extends TestCase
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

        $this->implementationFqcn = AddParentAttributeToCollectionModifier::class;
        $this->interfaceFqcn = AddParentAttributeToCollectionModifierInterface::class;
        $this->constructorArgumentDefaults = [
            'attributeCode' => 'klevu_test_attribute_code',
            'columnName' => 'klevu_test_attribute_code_parent',
        ];

        $this->objectManager = Bootstrap::getObjectManager();
    }

    public function testGetAttributeCode_ReturnsAttributeCode(): void
    {
        $modifier = $this->instantiateTestObject([
            'attributeCode' => 'klevu_test_attribute_code',
            'columnName' => 'klevu_test_attribute_code_parent',
        ]);
        $this->assertSame(
            expected: 'klevu_test_attribute_code',
            actual: $modifier->getAttributeCode(),
        );
    }
}
