<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Test\Integration\Service\Provider\Catalog\Product\Collection;

use Klevu\IndexingProducts\Service\Modifier\Catalog\Product\Collection\AddParentAttributeToCollectionModifier;
use Klevu\IndexingProducts\Service\Provider\Catalog\Product\Collection\AddParentAttributeToCollectionModifierProvider;
use Klevu\IndexingProducts\Service\Provider\Catalog\Product\Collection\AddParentAttributeToCollectionModifierProviderInterface; //phpcs:ignore Generic.Files.LineLength.TooLong
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Klevu\TestFixtures\Traits\TestInterfacePreferenceTrait;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

// phpcs:disable Generic.Files.LineLength.TooLong
/**
 * @covers AddParentAttributeToCollectionModifierProvider::class
 * @method AddParentAttributeToCollectionModifierProviderInterface instantiateTestObject(?array $arguments = null)
 * @method AddParentAttributeToCollectionModifierProviderInterface instantiateTestObjectFromInterface(?array $arguments = null)
 */
class AddParentAttributeToCollectionModifierProviderTest extends TestCase
{
    // phpcs:enable Generic.Files.LineLength.TooLong
    use ObjectInstantiationTrait;
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

        $this->implementationFqcn = AddParentAttributeToCollectionModifierProvider::class;
        $this->interfaceFqcn = AddParentAttributeToCollectionModifierProviderInterface::class;
        $this->objectManager = Bootstrap::getObjectManager();
    }

    public function testGet_ReturnsEmptyArray_WhenNoModifiersInjected(): void
    {
        $provider = $this->instantiateTestObject([
            'modifiers' => [],
        ]);
        $this->assertEmpty(actual: $provider->get());
    }

    public function testGet_ReturnsInjectedModifiers(): void
    {
        $modifier1 = $this->objectManager->create(AddParentAttributeToCollectionModifier::class, [
            'attributeCode' => 'attribute_code_1',
            'columnName' => 'parent_attribute_code_1',
        ]);
        $modifier2 = $this->objectManager->create(AddParentAttributeToCollectionModifier::class, [
            'attributeCode' => 'attribute_code_2',
            'columnName' => 'parent_attribute_code_2',
        ]);

        $provider = $this->instantiateTestObject([
            'modifiers' => [
                'modifier1' => $modifier1,
                'modifier2' => $modifier2,
            ],
        ]);
        $result = $provider->get();

        $this->assertArrayHasKey(key: 'modifier1', array: $result);
        $this->assertEquals(expected: $modifier1, actual: $result['modifier1']);

        $this->assertArrayHasKey(key: 'modifier2', array: $result);
        $this->assertEquals(expected: $modifier2, actual: $result['modifier2']);
    }
}
