<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Test\Integration\Service;

use Klevu\Indexing\Service\RegenerateConfigurationOverrides;
use Klevu\IndexingApi\Model\Source\IndexType;
use Klevu\IndexingApi\Service\RegenerateConfigurationOverridesInterface;
use Klevu\IndexingProducts\Service\RegenerateConfigurationOverrides as RegenerateConfigurationOverridesVirtualType; // phpcs:ignore Generic.Files.LineLength.TooLong
use Klevu\TestFixtures\Catalog\Attribute\AttributeFixturePool;
use Klevu\TestFixtures\Catalog\AttributeTrait;
use Klevu\TestFixtures\Store\StoreFixturesPool;
use Klevu\TestFixtures\Store\StoreTrait;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\SetAuthKeysTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Magento\Framework\App\Filesystem\DirectoryList as AppDirectoryList;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Filesystem\DirectoryList;
use Magento\Framework\Filesystem\Io\File as FileIo;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;
use TddWizard\Fixtures\Core\ConfigFixture;

/**
 * @covers RegenerateConfigurationOverrides::class
 * @method RegenerateConfigurationOverridesInterface instantiateTestObject(?array $arguments = null)
 * @method RegenerateConfigurationOverridesInterface instantiateTestObjectFromInterface(?array $arguments = null)
 */
class RegenerateConfigurationOverridesTest extends TestCase
{
    use AttributeTrait;
    use ObjectInstantiationTrait;
    use SetAuthKeysTrait;
    use StoreTrait;
    use TestImplementsInterfaceTrait;

    /**
     * @var ObjectManagerInterface|null
     */
    private ?ObjectManagerInterface $objectManager = null; // @phpstan-ignore-line
    /**
     * @var DirectoryList|null
     */
    private ?DirectoryList $directoryList = null;
    /**
     * @var FileIo|null
     */
    private ?FileIo $fileIo = null;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->implementationFqcn = RegenerateConfigurationOverridesVirtualType::class; // @phpstan-ignore-line
        $this->interfaceFqcn = RegenerateConfigurationOverridesInterface::class;
        $this->implementationForVirtualType = RegenerateConfigurationOverrides::class;
        $this->objectManager = Bootstrap::getObjectManager();

        $this->directoryList = $this->objectManager->get(DirectoryList::class);
        $this->fileIo = $this->objectManager->get(FileIo::class);

        $this->storeFixturesPool = $this->objectManager->get(StoreFixturesPool::class);
        $this->attributeFixturePool = $this->objectManager->get(AttributeFixturePool::class);
    }

    /**
     * @return void
     * @throws \Exception
     */
    protected function tearDown(): void
    {
        parent::tearDown();

        $this->attributeFixturePool->rollback();
        $this->storeFixturesPool->rollback();
    }

    /**
     * @magentoAppIsolation enabled
     */
    public function testExecute_RegeneratesOverridesFiles_WhenOverridesFilesDoNotExist(): void
    {
        $expectedFiles = $this->getExpectedFiles();

        $this->createAttribute([
            'key' => 'klevu_test_attribute_text',
            'code' => 'klevu_test_attribute_text',
            'label' => 'Klevu Test Attribute (Text)',
            'attribute_type' => 'text',
            'is_global' => 1,
            'is_html_allowed_on_front' => 1,
            'index_as' => IndexType::INDEX,
            'generate_config_for' => [
                'simple',
            ],
        ]);

        foreach ($expectedFiles as $expectedFile) {
            if ($this->fileIo->fileExists($expectedFile)) {
                $this->fileIo->rm($expectedFile);
            }
            $this->assertFileDoesNotExist($expectedFile);
        }

        $service = $this->instantiateTestObject();
        $service->execute();

        foreach ($expectedFiles as $expectedFile) {
            $this->assertFileExists($expectedFile);
            $this->assertNotEmpty(
                actual: $this->fileIo->read($expectedFile),
            );
        }

        $addUpdateArray = Yaml::parse(
            input: $this->fileIo->read(
                filename: $expectedFiles['add_update'],
            ),
        );
        $this->assertSame(
            expected: [
                'stages' => [
                    'processAttribute' => [
                        'pipeline' => 'Pipeline\Fallback',
                        'stages' => [
                            'getData' => [
                                'stages' => [
                                    'extract' => [
                                        'pipeline' => 'Stage\Extract',
                                        'args' => [
                                            'extraction' => 'currentProduct::getKlevuTestAttributeText()',
                                            'transformations' => [],
                                        ],
                                    ],
                                    'validate' => [
                                        'pipeline' => 'Stage\Validate',
                                        'args' => [
                                            'validation' => [
                                                'IsNotIn([null, ""], true)',
                                            ],
                                        ],
                                    ],
                                    'transform' => [
                                        'pipeline' => 'Stage\Transform',
                                        'args' => [
                                            'transformation' => 'ToString|StripTags(["p", "br", "hr", "h1", "h2", "h3", "h4", "h5", "h6", "strong", "em", "ul", "ol", "li", "dl", "dt", "dd", "img", "sub", "sup", "small"], ["script"])|EscapeHtml|Trim', // phpcs:ignore Generic.Files.LineLength.TooLong
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            actual: $addUpdateArray['stages']['iterateIndexingRecordsBatch']['stages']
                ['iterateIndexingRecords']['stages']['processProduct']['stages']
                ['default']['stages']['generateRecord']['stages']['attributes']['addStages']
                ['klevu_test_attribute_text'] ?? null,
        );
    }

    /**
     * @magentoAppIsolation enabled
     */
    public function testExecute_RegeneratesOverridesFiles_WhenOverridesFilesExist(): void
    {
        $expectedFiles = $this->getExpectedFiles();

        $this->createAttribute([
            'key' => 'klevu_test_attribute_text',
            'code' => 'klevu_test_attribute_text',
            'label' => 'Klevu Test Attribute (Text)',
            'attribute_type' => 'text',
            'is_global' => 1,
            'is_html_allowed_on_front' => 1,
            'index_as' => IndexType::INDEX,
            'generate_config_for' => [
                'simple',
            ],
        ]);

        foreach ($expectedFiles as $expectedFile) {
            if ($this->fileIo->fileExists($expectedFile)) {
                $this->fileIo->rm($expectedFile);
            }
            if (!$this->fileIo->fileExists($expectedFile)) {
                $this->fileIo->write(
                    filename: $expectedFile,
                    src: '# Test Content',
                );
            }
            $this->assertFileExists($expectedFile);
        }

        $service = $this->instantiateTestObject();
        $service->execute();

        foreach ($expectedFiles as $expectedFile) {
            $this->assertFileExists($expectedFile);
            $this->assertNotEmpty(
                actual: $this->fileIo->read($expectedFile),
            );
        }

        $addUpdateArray = Yaml::parse(
            input: $this->fileIo->read(
                filename: $expectedFiles['add_update'],
            ),
        );
        $this->assertSame(
            expected: [
                'stages' => [
                    'processAttribute' => [
                        'pipeline' => 'Pipeline\Fallback',
                        'stages' => [
                            'getData' => [
                                'stages' => [
                                    'extract' => [
                                        'pipeline' => 'Stage\Extract',
                                        'args' => [
                                            'extraction' => 'currentProduct::getKlevuTestAttributeText()',
                                            'transformations' => [],
                                        ],
                                    ],
                                    'validate' => [
                                        'pipeline' => 'Stage\Validate',
                                        'args' => [
                                            'validation' => [
                                                'IsNotIn([null, ""], true)',
                                            ],
                                        ],
                                    ],
                                    'transform' => [
                                        'pipeline' => 'Stage\Transform',
                                        'args' => [
                                            'transformation' => 'ToString|StripTags(["p", "br", "hr", "h1", "h2", "h3", "h4", "h5", "h6", "strong", "em", "ul", "ol", "li", "dl", "dt", "dd", "img", "sub", "sup", "small"], ["script"])|EscapeHtml|Trim', // phpcs:ignore Generic.Files.LineLength.TooLong
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            actual: $addUpdateArray['stages']['iterateIndexingRecordsBatch']['stages']
                ['iterateIndexingRecords']['stages']['processProduct']['stages']
                ['default']['stages']['generateRecord']['stages']['attributes']['addStages']
                ['klevu_test_attribute_text'] ?? null,
        );
    }

    /**
     * @magentoAppIsolation enabled
     */
    public function testExecute_PerformsNoAction_WhenConfigurationDisabled_AndFilesDoNotExist(): void
    {
        ConfigFixture::setGlobal(
            path: 'klevu/platform_pipelines/configuration_overrides_generation_enabled',
            value: 0,
        );

        $expectedFiles = $this->getExpectedFiles();

        $this->createAttribute([
            'key' => 'klevu_test_attribute_text',
            'code' => 'klevu_test_attribute_text',
            'label' => 'Klevu Test Attribute (Text)',
            'attribute_type' => 'text',
            'is_global' => 1,
            'is_html_allowed_on_front' => 1,
            'index_as' => IndexType::INDEX,
            'generate_config_for' => [
                'simple',
            ],
        ]);

        foreach ($expectedFiles as $expectedFile) {
            if ($this->fileIo->fileExists($expectedFile)) {
                $this->fileIo->rm($expectedFile);
            }
            $this->assertFileDoesNotExist($expectedFile);
        }

        $service = $this->instantiateTestObject();
        $service->execute();

        foreach ($expectedFiles as $expectedFile) {
            $this->assertFileDoesNotExist($expectedFile);
        }
    }

    /**
     * @magentoAppIsolation enabled
     */
    public function testExecute_PerformsNoAction_WhenConfigurationDisabled_AndFilesExist(): void
    {
        ConfigFixture::setGlobal(
            path: 'klevu/platform_pipelines/configuration_overrides_generation_enabled',
            value: 0,
        );

        $expectedFiles = $this->getExpectedFiles();

        $this->createAttribute([
            'key' => 'klevu_test_attribute_text',
            'code' => 'klevu_test_attribute_text',
            'label' => 'Klevu Test Attribute (Text)',
            'attribute_type' => 'text',
            'is_global' => 1,
            'is_html_allowed_on_front' => 1,
            'index_as' => IndexType::INDEX,
            'generate_config_for' => [
                'simple',
            ],
        ]);

        foreach ($expectedFiles as $expectedFile) {
            if ($this->fileIo->fileExists($expectedFile)) {
                $this->fileIo->rm($expectedFile);
            }
            if (!$this->fileIo->fileExists($expectedFile)) {
                $this->fileIo->write(
                    filename: $expectedFile,
                    src: '# Test Content',
                );
            }
            $this->assertFileExists($expectedFile);
        }

        $service = $this->instantiateTestObject();
        $service->execute();

        foreach ($expectedFiles as $expectedFile) {
            $this->assertFileExists($expectedFile);
            $this->assertSame(
                expected: '# Test Content',
                actual: $this->fileIo->read($expectedFile),
            );
        }
    }

    /**
     * @return string[]
     * @throws FileSystemException
     */
    private function getExpectedFiles(): array
    {
        $baseOverridesDirectory = implode(
            separator: DIRECTORY_SEPARATOR,
            array: [
                $this->directoryList->getPath(AppDirectoryList::VAR_DIR),
                'klevu',
                'indexing',
                'pipeline',
                'product',
            ],
        );

        return [
            'add_update' => $baseOverridesDirectory . DIRECTORY_SEPARATOR . 'add_update.overrides.yml',
            'delete' => $baseOverridesDirectory . DIRECTORY_SEPARATOR . 'delete.overrides.yml',
        ];
    }
}
