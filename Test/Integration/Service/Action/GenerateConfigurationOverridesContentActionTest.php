<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Test\Integration\Service\Action;

use Klevu\Indexing\Service\Action\GenerateConfigurationOverridesContentAction;
use Klevu\IndexingApi\Model\MagentoAttributeInterface;
use Klevu\IndexingApi\Model\MagentoAttributeInterfaceFactory;
use Klevu\IndexingApi\Service\Provider\AttributeProviderInterface;
use Klevu\IndexingProducts\Model\Source\EntitySubtypeOptions;
use Klevu\IndexingProducts\Service\Action\GenerateConfigurationOverridesContentAction as GenerateConfigurationOverridesContentActionVirtualType; // phpcs:ignore Generic.Files.LineLength.TooLong
use Klevu\PhpSDK\Model\Indexing\DataType;
use Klevu\PlatformPipelines\Api\GenerateConfigurationOverridesContentActionInterface;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\ObjectManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

// phpcs:disable Generic.Files.LineLength.TooLong
/**
 * @covers \Klevu\Indexing\Service\Action\GenerateConfigurationOverridesContentAction::class
 * @method GenerateConfigurationOverridesContentActionInterface instantiateTestObject(?array $arguments = null)
 * @method GenerateConfigurationOverridesContentActionInterface instantiateTestObjectFromInterface(?array $arguments = null)
 * /
 */
// phpcs:enable Generic.Files.LineLength.TooLong
class GenerateConfigurationOverridesContentActionTest extends TestCase
{
    use ObjectInstantiationTrait;
    use TestImplementsInterfaceTrait;

    /**
     * @var ObjectManagerInterface|null
     */
    private ?ObjectManagerInterface $objectManager = null; // @phpstan-ignore-line Used by traits
    /**
     * @var MagentoAttributeInterfaceFactory|null
     */
    private ?MagentoAttributeInterfaceFactory $magentoAttributeFactory = null;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->objectManager = ObjectManager::getInstance();

        $this->implementationFqcn = GenerateConfigurationOverridesContentActionVirtualType::class;//@phpstan-ignore-line
        $this->interfaceFqcn = GenerateConfigurationOverridesContentActionInterface::class;
        $this->implementationForVirtualType = GenerateConfigurationOverridesContentAction::class;

        $this->magentoAttributeFactory = $this->objectManager->get(MagentoAttributeInterfaceFactory::class);
    }

    /**
     * @return mixed[][]
     */
    private function getAttributesForConfigurationOverridesFixtures(): array
    {
        return [
            [
                'attributeId' => 123,
                'attributeCode' => 'test_attribute_string',
                'apiKey' => 'klevu-1234567890',
                'isIndexable' => true,
                'klevuAttributeName' => 'test_attribute_string_klevuName',
                'klevuAttributeType' => DataType::STRING,
                'generateConfigurationForEntitySubtypes' => [
                    EntitySubtypeOptions::SIMPLE,
                    EntitySubtypeOptions::VIRTUAL,
                    EntitySubtypeOptions::DOWNLOADABLE,
                    EntitySubtypeOptions::CONFIGURABLE_VARIANTS,
                ],
            ],
            [
                'attributeId' => 123,
                'attributeCode' => 'test_attribute_string_global',
                'apiKey' => 'klevu-1234567890',
                'isIndexable' => true,
                'klevuAttributeName' => 'test_attribute_string_global',
                'klevuAttributeType' => DataType::STRING,
                'generateConfigurationForEntitySubtypes' => [
                    EntitySubtypeOptions::SIMPLE,
                    EntitySubtypeOptions::VIRTUAL,
                    EntitySubtypeOptions::DOWNLOADABLE,
                    EntitySubtypeOptions::CONFIGURABLE_VARIANTS,
                ],
                'isGlobal' => true,
            ],
            [
                'attributeId' => 123,
                'attributeCode' => 'test_attribute_string_html',
                'apiKey' => 'klevu-1234567890',
                'isIndexable' => true,
                'klevuAttributeName' => 'test_attribute_string_html',
                'klevuAttributeType' => DataType::STRING,
                'generateConfigurationForEntitySubtypes' => [
                    EntitySubtypeOptions::SIMPLE,
                    EntitySubtypeOptions::VIRTUAL,
                    EntitySubtypeOptions::DOWNLOADABLE,
                    EntitySubtypeOptions::CONFIGURABLE_VARIANTS,
                ],
                'isHtmlAllowed' => true,
            ],
            [
                'attributeId' => 123,
                'attributeCode' => 'test_attribute_number',
                'apiKey' => 'klevu-1234567890',
                'isIndexable' => true,
                'klevuAttributeName' => 'test_attribute_number',
                'klevuAttributeType' => DataType::NUMBER,
                'generateConfigurationForEntitySubtypes' => [
                    EntitySubtypeOptions::BUNDLE,
                    EntitySubtypeOptions::GROUPED,
                ],
            ],
            [
                'attributeId' => 123,
                'attributeCode' => 'test_attribute_number_global',
                'apiKey' => 'klevu-1234567890',
                'isIndexable' => true,
                'klevuAttributeName' => 'test_attribute_number_global',
                'klevuAttributeType' => DataType::NUMBER,
                'generateConfigurationForEntitySubtypes' => [
                    EntitySubtypeOptions::BUNDLE,
                    EntitySubtypeOptions::GROUPED,
                ],
                'isGlobal' => true,
            ],
            [
                'attributeId' => 123,
                'attributeCode' => 'test_attribute_multivalue',
                'apiKey' => 'klevu-1234567890',
                'isIndexable' => true,
                'klevuAttributeName' => 'test_attribute_multivalue',
                'klevuAttributeType' => DataType::MULTIVALUE,
                'generateConfigurationForEntitySubtypes' => [
                    EntitySubtypeOptions::SIMPLE,
                    EntitySubtypeOptions::VIRTUAL,
                    EntitySubtypeOptions::CONFIGURABLE,
                ],
            ],
            [
                'attributeId' => 123,
                'attributeCode' => 'test_attribute_multivalue_global',
                'apiKey' => 'klevu-1234567890',
                'isIndexable' => true,
                'klevuAttributeName' => 'test_attribute_multivalue_global',
                'klevuAttributeType' => DataType::MULTIVALUE,
                'generateConfigurationForEntitySubtypes' => [
                    EntitySubtypeOptions::SIMPLE,
                    EntitySubtypeOptions::VIRTUAL,
                    EntitySubtypeOptions::CONFIGURABLE,
                ],
                'isGlobal' => true,
            ],
            [
                'attributeId' => 123,
                'attributeCode' => 'test_attribute_multivalue_usessource',
                'apiKey' => 'klevu-1234567890',
                'isIndexable' => true,
                'klevuAttributeName' => 'test_attribute_multivalue_usessource',
                'klevuAttributeType' => DataType::MULTIVALUE,
                'generateConfigurationForEntitySubtypes' => [
                    EntitySubtypeOptions::SIMPLE,
                    EntitySubtypeOptions::VIRTUAL,
                    EntitySubtypeOptions::CONFIGURABLE,
                ],
                'usesSourceModel' => true,
            ],
            [
                'attributeId' => 123,
                'attributeCode' => 'test_attribute_multivalue_global_usessource',
                'apiKey' => 'klevu-1234567890',
                'isIndexable' => true,
                'klevuAttributeName' => 'test_attribute_multivalue_global_usessource',
                'klevuAttributeType' => DataType::MULTIVALUE,
                'generateConfigurationForEntitySubtypes' => [
                    EntitySubtypeOptions::SIMPLE,
                    EntitySubtypeOptions::VIRTUAL,
                    EntitySubtypeOptions::CONFIGURABLE,
                ],
                'isGlobal' => true,
                'usesSourceModel' => true,
            ],
            [
                'attributeId' => 123,
                'attributeCode' => 'test_attribute_notindexable',
                'apiKey' => 'klevu-1234567890',
                'isIndexable' => false,
                'klevuAttributeName' => 'test_attribute_notindexable_klevuName',
                'klevuAttributeType' => DataType::STRING,
                'generateConfigurationForEntitySubtypes' => [
                    EntitySubtypeOptions::SIMPLE,
                    EntitySubtypeOptions::VIRTUAL,
                    EntitySubtypeOptions::DOWNLOADABLE,
                    EntitySubtypeOptions::GROUPED,
                    EntitySubtypeOptions::BUNDLE,
                    EntitySubtypeOptions::CONFIGURABLE,
                    EntitySubtypeOptions::CONFIGURABLE_VARIANTS,
                ],
            ],
        ];
    }

    public function testExecute(): void {
        $mockAttributesForConfigurationOverridesProvider = $this->getMockAttributesForConfigurationOverridesProvider(
            attributesData: $this->getAttributesForConfigurationOverridesFixtures(),
        );

        $generateConfigurationOverridesContentAction = $this->instantiateTestObject([
            'attributesForConfigurationOverridesProvider' => $mockAttributesForConfigurationOverridesProvider,
        ]);

        $content = $generateConfigurationOverridesContentAction->execute();

        $this->assertStringContainsString(
            needle: '# WARNING: This file is autogenerated',
            haystack: $content,
        );

        $contentAsArray = Yaml::parse(
            input: $content,
            flags: Yaml::PARSE_EXCEPTION_ON_INVALID_TYPE,
        );

        $addStages = [
            'default' => $contentAsArray['stages']['iterateIndexingRecordsBatch']['stages']
                ['iterateIndexingRecords']['stages']['processProduct']['stages']
                ['default']['stages']['generateRecord']['stages']['attributes']['addStages'] ?? null,
            'virtual' => $contentAsArray['stages']['iterateIndexingRecordsBatch']['stages']
                ['iterateIndexingRecords']['stages']['processProduct']['stages']
                ['virtualProduct']['stages']['generateRecord']['stages']['attributes']['addStages'] ?? null,
            'downloadable' => $contentAsArray['stages']['iterateIndexingRecordsBatch']['stages']
                ['iterateIndexingRecords']['stages']['processProduct']['stages']
                ['downloadableProduct']['stages']['generateRecord']['stages']['attributes']['addStages'] ?? null,
            'variant' => $contentAsArray['stages']['iterateIndexingRecordsBatch']['stages']
                ['iterateIndexingRecords']['stages']['processProduct']['stages']
                ['variantProduct']['stages']['generateRecord']['stages']['attributes']['addStages'] ?? null,
            'grouped' => $contentAsArray['stages']['iterateIndexingRecordsBatch']['stages']
                ['iterateIndexingRecords']['stages']['processProduct']['stages']
                ['bundleProduct']['stages']['generateRecord']['stages']['attributes']['addStages'] ?? null,
            'bundle' => $contentAsArray['stages']['iterateIndexingRecordsBatch']['stages']
                ['iterateIndexingRecords']['stages']['processProduct']['stages']
                ['groupedProduct']['stages']['generateRecord']['stages']['attributes']['addStages'] ?? null,
            'configurable' => $contentAsArray['stages']['iterateIndexingRecordsBatch']['stages']
                ['iterateIndexingRecords']['stages']['processProduct']['stages']
                ['configurableProduct']['stages']['generateRecord']['stages']['attributes']['addStages'] ?? null,
        ];
        array_walk($addStages, function (?array $stagesToAdd, string $entitySubtype): void {
            $this->assertNotNull(
                actual: $stagesToAdd,
                message: sprintf(
                    'Entity subtype %s contains addStages',
                    $entitySubtype,
                ),
            );
        });

        $expectedStageKeys = ['default', 'virtual', 'downloadable', 'variant'];
        foreach (array_keys($addStages) as $addStagesKey) {
            $this->assertArrayNotHasKey(
                key: 'test_attribute_string',
                array: $addStages[$addStagesKey],
                message: sprintf('Stage: %s', $addStagesKey),
            );
            if (!in_array($addStagesKey, $expectedStageKeys, true)) {
                $this->assertArrayNotHasKey(
                    key: 'test_attribute_string_klevuName',
                    array: $addStages[$addStagesKey],
                    message: sprintf('Stage: %s', $addStagesKey),
                );
                $this->assertArrayNotHasKey(
                    key: 'test_attribute_string_global',
                    array: $addStages[$addStagesKey],
                    message: sprintf('Stage: %s', $addStagesKey),
                );
                $this->assertArrayNotHasKey(
                    key: 'test_attribute_string_html',
                    array: $addStages[$addStagesKey],
                    message: sprintf('Stage: %s', $addStagesKey),
                );
                continue;
            }

            $this->assertArrayHasKey(
                key: 'test_attribute_string_klevuName',
                array: $addStages[$addStagesKey],
                message: sprintf('Stage: %s', $addStagesKey),
            );
            $this->assertSame(
                expected: [
                'pipeline' => 'Stage\Extract',
                    'args' => [
                        'extraction' => 'currentProduct::getTestAttributeString()',
                        'transformations' => [
                            'ToString',
                            'StripTags(null, ["script"])',
                            'Trim',
                        ],
                    ],
                ],
                actual: $addStages[$addStagesKey]['test_attribute_string_klevuName'],
            );

            $this->assertArrayHasKey(
                key: 'test_attribute_string_global',
                array: $addStages[$addStagesKey],
                message: sprintf('Stage: %s', $addStagesKey),
            );
            $this->assertSame(
                expected: [
                    'pipeline' => 'Stage\Extract',
                    'args' => [
                        'extraction' => 'currentProduct::getTestAttributeStringGlobal()',
                        'transformations' => [
                            'ToString',
                            'StripTags(null, ["script"])',
                            'Trim',
                        ],
                    ],
                ],
                actual: $addStages[$addStagesKey]['test_attribute_string_global'],
            );

            $this->assertArrayHasKey(
                key: 'test_attribute_string_html',
                array: $addStages[$addStagesKey],
                message: sprintf('Stage: %s', $addStagesKey),
            );
            $this->assertSame(
                expected: [
                    'pipeline' => 'Stage\Extract',
                    'args' => [
                        'extraction' => 'currentProduct::getTestAttributeStringHtml()',
                        'transformations' => [
                            'ToString',
                            'StripTags(["p", "br", "hr", "h1", "h2", "h3", "h4", "h5", "h6", "strong", "em", "ul", "ol", "li", "dl", "dt", "dd", "img", "sub", "sup", "small"], ["script"])', //phpcs:ignore Generic.Files.LineLength.TooLong
                            'EscapeHtml',
                            'Trim',
                        ],
                    ],
                ],
                actual: $addStages[$addStagesKey]['test_attribute_string_html'],
            );
        }

        $expectedStageKeys = ['bundle', 'grouped'];
        foreach (array_keys($addStages) as $addStagesKey) {
            if (!in_array($addStagesKey, $expectedStageKeys, true)) {
                $this->assertArrayNotHasKey(
                    key: 'test_attribute_number',
                    array: $addStages[$addStagesKey],
                    message: sprintf('Stage: %s', $addStagesKey),
                );
                $this->assertArrayNotHasKey(
                    key: 'test_attribute_number_global',
                    array: $addStages[$addStagesKey],
                    message: sprintf('Stage: %s', $addStagesKey),
                );
                continue;
            }

            $this->assertArrayHasKey(
                key: 'test_attribute_number',
                array: $addStages[$addStagesKey],
                message: sprintf('Stage: %s', $addStagesKey),
            );
            $this->assertSame(
                expected: [
                    'pipeline' => 'Stage\Extract',
                    'args' => [
                        'extraction' => 'currentProduct::getTestAttributeNumber()',
                        'transformations' => [
                            'ToFloat',
                        ],
                    ],
                ],
                actual: $addStages[$addStagesKey]['test_attribute_number'],
            );

            $this->assertArrayHasKey(
                key: 'test_attribute_number_global',
                array: $addStages[$addStagesKey],
                message: sprintf('Stage: %s', $addStagesKey),
            );
            $this->assertSame(
                expected: [
                    'pipeline' => 'Stage\Extract',
                    'args' => [
                        'extraction' => 'currentProduct::getTestAttributeNumberGlobal()',
                        'transformations' => [
                            'ToFloat',
                        ],
                    ],
                ],
                actual: $addStages[$addStagesKey]['test_attribute_number_global'],
            );
        }

        $expectedStageKeys = ['default', 'virtual', 'configurable'];
        foreach (array_keys($addStages) as $addStagesKey) {
            if (!in_array($addStagesKey, $expectedStageKeys, true)) {
                $this->assertArrayNotHasKey(
                    key: 'test_attribute_multivalue',
                    array: $addStages[$addStagesKey],
                    message: sprintf('Stage: %s', $addStagesKey),
                );
                $this->assertArrayNotHasKey(
                    key: 'test_attribute_multivalue_global',
                    array: $addStages[$addStagesKey],
                    message: sprintf('Stage: %s', $addStagesKey),
                );
                $this->assertArrayNotHasKey(
                    key: 'test_attribute_multivalue_usessource',
                    array: $addStages[$addStagesKey],
                    message: sprintf('Stage: %s', $addStagesKey),
                );
                $this->assertArrayNotHasKey(
                    key: 'test_attribute_multivalue_global_usessource',
                    array: $addStages[$addStagesKey],
                    message: sprintf('Stage: %s', $addStagesKey),
                );
                continue;
            }

            $this->assertArrayHasKey(
                key: 'test_attribute_multivalue',
                array: $addStages[$addStagesKey],
                message: sprintf('Stage: %s', $addStagesKey),
            );
            $this->assertSame(
                expected: [
                    'pipeline' => 'Stage\Extract',
                    'args' => [
                        'extraction' => 'currentProduct::getTestAttributeMultivalue()',
                        'transformations' => [
                            'ToArray',
                            'ToString',
                            'StripTags(null, ["script"])',
                            'Trim',
                        ],
                    ],
                ],
                actual: $addStages[$addStagesKey]['test_attribute_multivalue'],
            );

            $this->assertArrayHasKey(
                key: 'test_attribute_multivalue_global',
                array: $addStages[$addStagesKey],
                message: sprintf('Stage: %s', $addStagesKey),
            );
            $this->assertSame(
                expected: [
                    'pipeline' => 'Stage\Extract',
                    'args' => [
                        'extraction' => 'currentProduct::getTestAttributeMultivalueGlobal()',
                        'transformations' => [
                            'ToArray',
                            'ToString',
                            'StripTags(null, ["script"])',
                            'Trim',
                        ],
                    ],
                ],
                actual: $addStages[$addStagesKey]['test_attribute_multivalue_global'],
            );

            $this->assertArrayHasKey(
                key: 'test_attribute_multivalue_usessource',
                array: $addStages[$addStagesKey],
                message: sprintf('Stage: %s', $addStagesKey),
            );
            $this->assertSame(
                expected: [
                    'pipeline' => 'Stage\Extract',
                    'args' => [
                        'extraction' => 'currentProduct::',
                        'transformations' => [
                            'GetAttributeText("test_attribute_multivalue_usessource")',
                            'ToArray',
                            'ToString',
                            'StripTags(null, ["script"])',
                            'Trim',
                        ],
                    ],
                ],
                actual: $addStages[$addStagesKey]['test_attribute_multivalue_usessource'],
            );

            $this->assertArrayHasKey(
                key: 'test_attribute_multivalue_global_usessource',
                array: $addStages[$addStagesKey],
                message: sprintf('Stage: %s', $addStagesKey),
            );
            $this->assertSame(
                expected: [
                    'pipeline' => 'Stage\Extract',
                    'args' => [
                        'extraction' => 'currentProduct::',
                        'transformations' => [
                            'GetAttributeText("test_attribute_multivalue_global_usessource")',
                            'ToArray',
                            'ToString',
                            'StripTags(null, ["script"])',
                            'Trim',
                        ],
                    ],
                ],
                actual: $addStages[$addStagesKey]['test_attribute_multivalue_global_usessource'],
            );
        }

        foreach (array_keys($addStages) as $addStagesKey) {
            $this->assertArrayNotHasKey(
                key: 'test_attribute_notindexable',
                array: $addStages[$addStagesKey],
                message: sprintf('Stage: %s', $addStagesKey),
            );
            $this->assertArrayNotHasKey(
                key: 'test_attribute_notindexable_klevuName',
                array: $addStages[$addStagesKey],
                message: sprintf('Stage: %s', $addStagesKey),
            );
        }
    }

    /**
     * @param mixed[] $attributeData
     *
     * @return MagentoAttributeInterface
     */
    private function createMagentoAttribute(array $attributeData): MagentoAttributeInterface
    {
        $constructorArgs = array_intersect_key(
            $attributeData,
            [
                'attributeId' => null,
                'attributeCode' => null,
                'apiKey' => null,
                'isIndexable' => null,
                'klevuAttributeName' => null,
            ],
        );

        $magentoAttribute = $this->magentoAttributeFactory->create($constructorArgs);

        if ($attributeData['klevuAttributeType'] ?? null) {
            $magentoAttribute->setKlevuAttributeType($attributeData['klevuAttributeType']);
        }
        if ($attributeData['generateConfigurationForEntitySubtypes'] ?? null) {
            $magentoAttribute->setGenerateConfigurationForEntitySubtypes(
                generateConfigurationForEntitySubtypes: $attributeData['generateConfigurationForEntitySubtypes'],
            );
        }
        if (null !== ($attributeData['isGlobal'] ?? null)) {
            $magentoAttribute->setIsGlobal($attributeData['isGlobal']);
        }
        if (null !== ($attributeData['usesSourceModel'] ?? null)) {
            $magentoAttribute->setUsesSourceModel($attributeData['usesSourceModel']);
        }
        if (null !== ($attributeData['isHtmlAllowed'] ?? null)) {
            $magentoAttribute->setIsHtmlAllowed($attributeData['isHtmlAllowed']);
        }
        if (null !== ($attributeData['allowsMultipleValues'] ?? null)) {
            $magentoAttribute->setAllowsMultipleValues($attributeData['allowsMultipleValues']);
        }

        return $magentoAttribute;
    }

    /**
     * @param mixed[][] $attributesData
     *
     * @return MockObject&AttributeProviderInterface
     */
    private function getMockAttributesForConfigurationOverridesProvider(
        array $attributesData,
    ): MockObject {
        $mockAttributesForConfigurationOverridesProvider = $this->getMockBuilder(AttributeProviderInterface::class)
            ->disableOriginalConstructor()
            ->getMock();

        $mockAttributesForConfigurationOverridesProvider
            ->method('get')
            ->will(
                $this->returnCallback(function () use ($attributesData): \Generator {
                    foreach ($attributesData as $attributeData) {
                        yield $this->createMagentoAttribute($attributeData);
                    }
                }),
            );

        return $mockAttributesForConfigurationOverridesProvider;
    }
}
