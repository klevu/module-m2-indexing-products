<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Test\Integration\Plugin\Catalog\Model\ResourceModel;

use Klevu\Indexing\Test\Integration\Traits\IndexingAttributesTrait;
use Klevu\IndexingApi\Model\Source\IndexType;
use Klevu\IndexingProducts\Plugin\Catalog\Model\ResourceModel\RegeneratePipelineOverridesOnAttributeSavePlugin;
use Klevu\TestFixtures\Catalog\Attribute\AttributeFixturePool;
use Klevu\TestFixtures\Catalog\AttributeTrait;
use Klevu\TestFixtures\Store\StoreFixturesPool;
use Klevu\TestFixtures\Store\StoreTrait;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\SetAuthKeysTrait;
use Magento\Catalog\Api\ProductAttributeRepositoryInterface;
use Magento\Catalog\Model\ResourceModel\Eav\Attribute;
use Magento\Eav\Model\ResourceModel\Entity\Attribute as AttributeEavResourceModel;
use Magento\Framework\App\Filesystem\DirectoryList as AppDirectoryList;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Filesystem\DirectoryList;
use Magento\Framework\Filesystem\Io\File as FileIo;
use Magento\Framework\Interception\PluginList\PluginList;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;
use TddWizard\Fixtures\Core\ConfigFixture;

/**
 * @covers RegeneratePipelineOverridesOnAttributeSavePlugin::class
 * @method RegeneratePipelineOverridesOnAttributeSavePlugin instantiateTestObject(?array $arguments = null)
 * @method RegeneratePipelineOverridesOnAttributeSavePlugin instantiateTestObjectFromInterface(?array $arguments = null)
 */
class RegeneratePipelineOverridesOnAttributeSavePluginTest extends TestCase
{
    use AttributeTrait;
    use IndexingAttributesTrait;
    use ObjectInstantiationTrait;
    use SetAuthKeysTrait;
    use StoreTrait;

    /**
     * @var string|null
     */
    private ?string $pluginName = 'Klevu_IndexingProducts::RegeneratePipelineOverridesOnAttributeSavePlugin';
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
     * @var ProductAttributeRepositoryInterface|null
     */
    private ?ProductAttributeRepositoryInterface $productAttributeRepository = null;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->implementationFqcn = RegeneratePipelineOverridesOnAttributeSavePlugin::class;
        $this->objectManager = Bootstrap::getObjectManager();

        $this->directoryList = $this->objectManager->get(DirectoryList::class);
        $this->fileIo = $this->objectManager->get(FileIo::class);
        $this->productAttributeRepository = $this->objectManager->get(ProductAttributeRepositoryInterface::class);

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
     * @magentoAppArea global
     */
    public function testPlugin_InterceptsCallsToTheField_InGlobalScope(): void
    {
        /** @var PluginList $pluginList */
        $pluginList = $this->objectManager->get(PluginList::class);
        $pluginInfo = $pluginList->get(AttributeEavResourceModel::class, []);
        $this->assertArrayHasKey($this->pluginName, $pluginInfo);
        $this->assertSame(
            expected: RegeneratePipelineOverridesOnAttributeSavePlugin::class,
            actual: $pluginInfo[$this->pluginName]['instance'],
        );
    }

    public function testAttributeCreate_RegeneratesOverridesFiles_WhenOverridesFilesDoNotExist(): void
    {
        $expectedFiles = $this->getExpectedFiles();

        foreach ($expectedFiles as $expectedFile) {
            if ($this->fileIo->fileExists($expectedFile)) {
                $this->fileIo->rm($expectedFile);
            }
            $this->assertFileDoesNotExist($expectedFile);
        }

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
                'pipeline' => 'Stage\Extract',
                'args' => [
                    'extraction' => 'currentProduct::getKlevuTestAttributeText()',
                    'transformations' => [
                        'ToString',
                        'StripTags(["p", "br", "hr", "h1", "h2", "h3", "h4", "h5", "h6", "strong", "em", "ul", "ol", "li", "dl", "dt", "dd", "img", "sub", "sup", "small"], ["script"])', // phpcs:ignore Generic.Files.LineLength.TooLong
                        'EscapeHtml',
                        'Trim',
                    ],
                ],
            ],
            actual: $addUpdateArray['stages']['iterateIndexingRecordsBatch']['stages']
                ['iterateIndexingRecords']['stages']['processProduct']['stages']
                ['default']['stages']['generateRecord']['stages']['attributes']['addStages']
                ['klevu_test_attribute_text'] ?? null,
        );
    }

    public function testAttributeUpdate_RegeneratesOverridesFiles_WhenOverridesFilesDoNotExist(): void
    {
        $expectedFiles = $this->getExpectedFiles();

        $this->createAttribute([
            'key' => 'klevu_test_attribute_text',
            'code' => 'klevu_test_attribute_text',
            'label' => 'Klevu Test Attribute (Text)',
            'attribute_type' => 'text',
            'is_global' => 1,
            'is_html_allowed_on_front' => 1,
            'index_as' => IndexType::NO_INDEX,
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

        $attributeFixture = $this->attributeFixturePool->get('klevu_test_attribute_text');
        /** @var Attribute $magentoAttribute */
        $magentoAttribute = $attributeFixture->getAttribute();
        $magentoAttribute->setData('klevu_is_indexable', IndexType::INDEX->value);
        $this->productAttributeRepository->save($magentoAttribute);

        foreach ($expectedFiles as $expectedFile) {
            $this->assertFileExists($expectedFile);
            $this->assertNotEmpty(
                actual: $this->fileIo->read($expectedFile),
            );
        }

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
                'pipeline' => 'Stage\Extract',
                'args' => [
                    'extraction' => 'currentProduct::getKlevuTestAttributeText()',
                    'transformations' => [
                        'ToString',
                        'StripTags(["p", "br", "hr", "h1", "h2", "h3", "h4", "h5", "h6", "strong", "em", "ul", "ol", "li", "dl", "dt", "dd", "img", "sub", "sup", "small"], ["script"])', // phpcs:ignore Generic.Files.LineLength.TooLong
                        'EscapeHtml',
                        'Trim',
                    ],
                ],
            ],
            actual: $addUpdateArray['stages']['iterateIndexingRecordsBatch']['stages']
                ['iterateIndexingRecords']['stages']['processProduct']['stages']
                ['default']['stages']['generateRecord']['stages']['attributes']['addStages']
                ['klevu_test_attribute_text'] ?? null,
        );
    }

    public function testAttributeCreate_RegeneratesOverridesFiles_WhenOverridesFilesExist(): void
    {
        $expectedFiles = $this->getExpectedFiles();

        foreach ($expectedFiles as $expectedFile) {
            if (!$this->fileIo->fileExists($expectedFile)) {
                $this->fileIo->write(
                    filename: $expectedFile,
                    src: '# Test Content',
                );
            }
            $this->assertFileExists($expectedFile);
        }

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
                'pipeline' => 'Stage\Extract',
                'args' => [
                    'extraction' => 'currentProduct::getKlevuTestAttributeText()',
                    'transformations' => [
                        'ToString',
                        'StripTags(["p", "br", "hr", "h1", "h2", "h3", "h4", "h5", "h6", "strong", "em", "ul", "ol", "li", "dl", "dt", "dd", "img", "sub", "sup", "small"], ["script"])', // phpcs:ignore Generic.Files.LineLength.TooLong
                        'EscapeHtml',
                        'Trim',
                    ],
                ],
            ],
            actual: $addUpdateArray['stages']['iterateIndexingRecordsBatch']['stages']
                ['iterateIndexingRecords']['stages']['processProduct']['stages']
                ['default']['stages']['generateRecord']['stages']['attributes']['addStages']
                ['klevu_test_attribute_text'] ?? null,
        );
    }

    public function testAttributeUpdate_RegeneratesOverridesFiles_WhenOverridesFilesExist(): void
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
            if (!$this->fileIo->fileExists($expectedFile)) {
                $this->fileIo->write(
                    filename: $expectedFile,
                    src: '# Test Content',
                );
            }
            $this->assertFileExists($expectedFile);
        }

        $attributeFixture = $this->attributeFixturePool->get('klevu_test_attribute_text');
        /** @var Attribute $magentoAttribute */
        $magentoAttribute = $attributeFixture->getAttribute();
        $magentoAttribute->setIsGlobal(0);
        $this->productAttributeRepository->save($magentoAttribute);

        foreach ($expectedFiles as $expectedFile) {
            $this->assertFileExists($expectedFile);
            $this->assertNotEmpty(
                actual: $this->fileIo->read($expectedFile),
            );
        }

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
                'pipeline' => 'Stage\Extract',
                'args' => [
                    'extraction' => 'currentProduct::getKlevuTestAttributeText()',
                    'transformations' => [
                        'ToString',
                        'StripTags(["p", "br", "hr", "h1", "h2", "h3", "h4", "h5", "h6", "strong", "em", "ul", "ol", "li", "dl", "dt", "dd", "img", "sub", "sup", "small"], ["script"])', // phpcs:ignore Generic.Files.LineLength.TooLong
                        'EscapeHtml',
                        'Trim',
                    ],
                ],
            ],
            actual: $addUpdateArray['stages']['iterateIndexingRecordsBatch']['stages']
                ['iterateIndexingRecords']['stages']['processProduct']['stages']
                ['default']['stages']['generateRecord']['stages']['attributes']['addStages']
                ['klevu_test_attribute_text'] ?? null,
        );
    }

    public function testAttributeSave_PerformsNoAction_WhenConfigurationDisabled_AndFilesDoNotExist(): void
    {
        ConfigFixture::setGlobal(
            path: 'klevu/platform_pipelines/configuration_overrides_generation_enabled',
            value: 0,
        );

        $expectedFiles = $this->getExpectedFiles();

        foreach ($expectedFiles as $expectedFile) {
            if ($this->fileIo->fileExists($expectedFile)) {
                $this->fileIo->rm($expectedFile);
            }
            $this->assertFileDoesNotExist($expectedFile);
        }

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
            $this->assertFileDoesNotExist($expectedFile);
        }
    }

    public function testAttributeSave_PerformsNoAction_WhenConfigurationDisabled_AndFilesExist(): void
    {
        ConfigFixture::setGlobal(
            path: 'klevu/platform_pipelines/configuration_overrides_generation_enabled',
            value: 0,
        );

        $expectedFiles = $this->getExpectedFiles();

        foreach ($expectedFiles as $expectedFile) {
            if (!$this->fileIo->fileExists($expectedFile)) {
                $this->fileIo->write(
                    filename: $expectedFile,
                    src: '# Test Content',
                );
            }
            $this->assertFileExists($expectedFile);
        }

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
            $this->assertFileExists($expectedFile);
            $this->assertSame(
                expected: '# Test Content',
                actual: $this->fileIo->read($expectedFile),
            );
        }
    }

    public function testAttributeSave_PerformsNoAction_WhenPropertiesToCheckHaveNotChanged(): void
    {
        ConfigFixture::setGlobal(
            path: 'klevu/platform_pipelines/configuration_overrides_generation_enabled',
            value: 1,
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

        $attributeFixture = $this->attributeFixturePool->get('klevu_test_attribute_text');
        /** @var Attribute $magentoAttribute */
        $magentoAttribute = $attributeFixture->getAttribute();
        $magentoAttribute->setIsGlobal(0);
        $this->productAttributeRepository->save($magentoAttribute);

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
