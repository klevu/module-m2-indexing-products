<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Test\Integration\Model\Source;

use Klevu\IndexingProducts\Model\Source\EntitySubtypeOptions;
use Klevu\IndexingProducts\Model\Source\EntitySubtypeOptionsInterface;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Klevu\TestFixtures\Traits\TestInterfacePreferenceTrait;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\Phrase;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * @covers EntitySubtypeOptions::class
 * @method EntitySubtypeOptionsInterface instantiateTestObject(?array $arguments = null)
 * @method EntitySubtypeOptionsInterface instantiateTestObjectFromInterface(?array $arguments = null)
 */
class EntitySubtypeOptionsTest extends TestCase
{
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

        $this->implementationFqcn = EntitySubtypeOptions::class;
        $this->interfaceFqcn = EntitySubtypeOptionsInterface::class;
        $this->objectManager = Bootstrap::getObjectManager();
    }

    /**
     * @testWith ["simple", "Simple"]
     *           ["virtual", "Virtual"]
     *           ["downloadable", "Downloadable"]
     *           ["grouped", "Grouped"]
     *           ["bundle", "Bundle"]
     *           ["configurable", "Configurable (Parent)"]
     *           ["configurable_variants", "Configurable (Variant)"]
     *           ["", "-- Remove --"]
     */
    public function testToArrayOptions_ReturnsDefaultOptions(string $value, string $expected): void
    {
        $source = $this->instantiateTestObject();
        $result = $source->toOptionArray();

        $option = $this->filterOptions($result, $value);
        $this->assertSame(
            expected: $expected,
            actual: isset($option['label']) ? $option['label']->render() : null,
        );
    }

    public function testToArrayOptions_ReturnsInjectedOptions(): void
    {
        $source = $this->instantiateTestObject([
            'customProductTypes' => [
                'value_1' => 'Label 1',
                'value_2' => 'Label 2',
            ],
        ]);
        $result = $source->toOptionArray();

        $option1 = $this->filterOptions($result, 'value_1');
        $this->assertSame(
            expected: 'Label 1',
            actual: isset($option1['label']) ? $option1['label']->render() : null,
        );

        $option2 = $this->filterOptions($result, 'value_2');
        $this->assertSame(
            expected: 'Label 2',
            actual: isset($option2['label']) ? $option2['label']->render() : null,
        );
    }

    public function testGetValues_ReturnsDefaultAndCustomValues(): void
    {
        $source = $this->instantiateTestObject([
            'customProductTypes' => [
                'value_1' => 'Label 1',
                'value_2' => 'Label 2',
            ],
        ]);
        $result = $source->getValues();

        $this->assertContains(needle: 'simple', haystack: $result);
        $this->assertContains(needle: 'virtual', haystack: $result);
        $this->assertContains(needle: 'downloadable', haystack: $result);
        $this->assertContains(needle: 'grouped', haystack: $result);
        $this->assertContains(needle: 'bundle', haystack: $result);
        $this->assertContains(needle: 'configurable', haystack: $result);
        $this->assertContains(needle: 'configurable_variants', haystack: $result);
        $this->assertContains(needle: 'value_1', haystack: $result);
        $this->assertContains(needle: 'value_2', haystack: $result);
    }

    /**
     * @param array<int, array<string, string|Phrase>> $result
     * @param string $value
     *
     * @return array<string, string|Phrase>
     */
    private function filterOptions(array $result, string $value): array
    {
        $optionArray = array_filter(
            array: $result,
            callback: static fn (array $option): bool => ($option['value'] ?? null) === $value,
        );

        return array_shift($optionArray);
    }
}
