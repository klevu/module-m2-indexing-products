<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Test\Integration\Service\Action;

use Klevu\Configuration\Service\Provider\ApiKeyProvider;
use Klevu\Indexing\Exception\AttributeMappingMissingException;
use Klevu\Indexing\Service\Action\ConvertEavAttributeToIndexingAttributeAction;
use Klevu\IndexingApi\Api\ConvertEavAttributeToIndexingAttributeActionInterface;
use Klevu\IndexingApi\Model\MagentoAttributeInterface;
use Klevu\IndexingApi\Model\Source\IndexType;
use Klevu\IndexingProducts\Observer\ProductAttribute\AttributeUpdateResponderObserver;
use Klevu\IndexingProducts\Observer\ProductAttribute\RegenerateConfigurationOverridesObserver;
use Klevu\PhpSDK\Model\Indexing\DataType;
use Klevu\TestFixtures\Catalog\Attribute\AttributeFixturePool;
use Klevu\TestFixtures\Catalog\AttributeTrait;
use Klevu\TestFixtures\Traits\AttributeApiCallTrait;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Magento\Catalog\Api\ProductAttributeRepositoryInterface;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\ObjectManagerInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\TestFramework\ObjectManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use TddWizard\Fixtures\Core\ConfigFixture;

// phpcs:disable Generic.Files.LineLength.TooLong
/**
 * @covers \Klevu\Indexing\Service\Action\ConvertEavAttributeToIndexingAttributeAction::class
 * @method ConvertEavAttributeToIndexingAttributeActionInterface instantiateTestObject(?array $arguments = null)
 * @method ConvertEavAttributeToIndexingAttributeActionInterface instantiateTestObjectFromInterface(?array $arguments = null)
 */
// phpcs:enable Generic.Files.LineLength.TooLong
class ConvertEavAttributeToIndexingAttributeActionTest extends TestCase
{
    use AttributeApiCallTrait;
    use AttributeTrait;
    use ObjectInstantiationTrait;
    use TestImplementsInterfaceTrait;

    /**
     * @var ObjectManagerInterface|null
     */
    private ?ObjectManagerInterface $objectManager = null; // @phpstan-ignore-line Used by traits
    /**
     * @var StoreManagerInterface|null
     */
    private ?StoreManagerInterface $storeManager = null;
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

        $this->objectManager = ObjectManager::getInstance();

        $this->implementationFqcn = ConvertEavAttributeToIndexingAttributeAction::class;
        $this->interfaceFqcn = ConvertEavAttributeToIndexingAttributeActionInterface::class;

        $this->attributeFixturePool = $this->objectManager->get(AttributeFixturePool::class);
        $this->storeManager = $this->objectManager->get(StoreManagerInterface::class);
        $this->productAttributeRepository = $this->objectManager->get(ProductAttributeRepositoryInterface::class);

        $this->objectManager->removeSharedInstance(
            className: AttributeUpdateResponderObserver::class,
        );
        $this->objectManager->addSharedInstance(
            instance: $this->getMockObserver(),
            className: AttributeUpdateResponderObserver::class,
        );

        $this->objectManager->removeSharedInstance(
            className: RegenerateConfigurationOverridesObserver::class,
        );
        $this->objectManager->addSharedInstance(
            instance: $this->getMockObserver(),
            className: RegenerateConfigurationOverridesObserver::class,
        );
    }

    /**
     * @return void
     * @throws \Exception
     */
    protected function tearDown(): void
    {
        parent::tearDown();

        $this->attributeFixturePool->rollback();
    }

    /**
     * @magentoDbIsolation disabled
     */
    public function testExecute_WithAttributeFixture_Text(): void
    {
        ConfigFixture::setForStore(
            path: ApiKeyProvider::CONFIG_XML_PATH_JS_API_KEY,
            value: 'klevu-1234567890',
            storeCode: 'default',
        );

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
        $attributeFixture = $this->attributeFixturePool->get('klevu_test_attribute_text');
        $magentoAttribute = $attributeFixture->getAttribute();

        $convertEavAttributeToIndexingAttributeAction = $this->instantiateTestObject();

        $resultWithoutStore = $convertEavAttributeToIndexingAttributeAction->execute(
            entityType: 'KLEVU_PRODUCT',
            attribute: $magentoAttribute,
            store: null,
        );
        $this->assertInstanceOf(
            expected: MagentoAttributeInterface::class,
            actual: $resultWithoutStore,
        );
        $this->assertNotEmpty(
            actual: $resultWithoutStore->getAttributeId(),
            message: 'attribute_id (without store)',
        );
        $this->assertSame(
            expected: 'klevu_test_attribute_text',
            actual: $resultWithoutStore->getAttributeCode(),
            message: 'attribute_code (without store)',
        );
        $this->assertSame(
            expected: '',
            actual: $resultWithoutStore->getApiKey(),
            message: 'api_key (without store)',
        );
        $this->assertTrue(
            condition: $resultWithoutStore->isIndexable(),
            message: 'is_indexable (without store)',
        );
        $this->assertSame(
            expected: [
                'simple',
            ],
            actual: $resultWithoutStore->getGenerateConfigurationForEntitySubtypes(),
            message: 'generate_configuration_for_entity_subtypes (without store)',
        );
        $this->assertEquals(
            expected: DataType::STRING,
            actual: $resultWithoutStore->getKlevuAttributeType(),
            message: 'klevu_attribute_type (without store)',
        );
        $this->assertTrue(
            condition: $resultWithoutStore->isGlobal(),
            message: 'is_global (without store)',
        );
        $this->assertFalse(
            condition: $resultWithoutStore->usesSourceModel(),
            message: 'uses_source_model (without store)',
        );
        $this->assertTrue(
            condition: $resultWithoutStore->isHtmlAllowed(),
            message: 'is_html_allowed (without store)',
        );
        $this->assertFalse(
            condition: $resultWithoutStore->allowsMultipleValues(),
            message: 'allows_multiple_values (without store)',
        );

        $resultWithStore = $convertEavAttributeToIndexingAttributeAction->execute(
            entityType: 'KLEVU_PRODUCT',
            attribute: $magentoAttribute,
            store: $this->storeManager->getStore('default'),
        );
        $this->assertInstanceOf(
            expected: MagentoAttributeInterface::class,
            actual: $resultWithStore,
        );
        $this->assertSame(
            expected: $resultWithoutStore->getAttributeId(),
            actual: $resultWithStore->getAttributeId(),
            message: 'attribute_id (with store)',
        );
        $this->assertSame(
            expected: $resultWithoutStore->getAttributeCode(),
            actual: $resultWithStore->getAttributeCode(),
            message: 'attribute_code (with store)',
        );
        $this->assertSame(
            expected: 'klevu-1234567890',
            actual: $resultWithStore->getApiKey(),
            message: 'api_key (with store)',
        );
        $this->assertTrue(
            $resultWithStore->isIndexable(),
            message: 'is_indexable (with store)',
        );
        $this->assertSame(
            expected: $resultWithoutStore->getGenerateConfigurationForEntitySubtypes(),
            actual: $resultWithStore->getGenerateConfigurationForEntitySubtypes(),
            message: 'generate_configuration_for_entity_subtypes (with store)',
        );
        $this->assertEquals(
            expected: $resultWithoutStore->getKlevuAttributeType(),
            actual: $resultWithStore->getKlevuAttributeType(),
            message: 'klevu_attribute_type (with store)',
        );
        $this->assertSame(
            expected: $resultWithoutStore->isGlobal(),
            actual: $resultWithStore->isGlobal(),
            message: 'is_global (with store)',
        );
        $this->assertSame(
            expected: $resultWithoutStore->usesSourceModel(),
            actual: $resultWithStore->usesSourceModel(),
            message: 'uses_source_model (with store)',
        );
        $this->assertSame(
            expected: $resultWithoutStore->isHtmlAllowed(),
            actual: $resultWithStore->isHtmlAllowed(),
            message: 'is_html_allowed (with store)',
        );
        $this->assertSame(
            expected: $resultWithoutStore->allowsMultipleValues(),
            actual: $resultWithStore->allowsMultipleValues(),
            message: 'allows_multiple_values (with store)',
        );
    }

    public function testExecute_WithAttributeFixture_Configurable(): void
    {
        $this->createAttribute([
            'key' => 'klevu_test_attribute_config',
            'code' => 'klevu_test_attribute_config',
            'label' => 'Klevu Test Attribute (Configurable)',
            'attribute_type' => 'configurable',
            'index_as' => IndexType::INDEX,
        ]);
        $attributeFixture = $this->attributeFixturePool->get('klevu_test_attribute_config');
        $magentoAttribute = $attributeFixture->getAttribute();

        $convertEavAttributeToIndexingAttributeAction = $this->instantiateTestObject();

        $resultWithoutStore = $convertEavAttributeToIndexingAttributeAction->execute(
            entityType: 'KLEVU_PRODUCT',
            attribute: $magentoAttribute,
            store: null,
        );
        $this->assertInstanceOf(
            expected: MagentoAttributeInterface::class,
            actual: $resultWithoutStore,
        );
        $this->assertTrue(
            condition: $resultWithoutStore->usesSourceModel(),
            message: 'uses source model (without store)',
        );
        $this->assertFalse(
            condition: $resultWithoutStore->allowsMultipleValues(),
            message: 'allows multiple values (without store)',
        );

        $resultWithStore = $convertEavAttributeToIndexingAttributeAction->execute(
            entityType: 'KLEVU_PRODUCT',
            attribute: $magentoAttribute,
            store: $this->storeManager->getStore('default'),
        );
        $this->assertInstanceOf(
            expected: MagentoAttributeInterface::class,
            actual: $resultWithStore,
        );
        $this->assertTrue(
            condition: $resultWithStore->usesSourceModel(),
            message: 'uses source model (with store)',
        );
        $this->assertFalse(
            condition: $resultWithStore->allowsMultipleValues(),
            message: 'allows multiple values (with store)',
        );
    }

    public function testExecute_WithAttributeFixture_Multiselect(): void
    {
        $this->createAttribute([
            'key' => 'klevu_test_attribute_multiselect',
            'code' => 'klevu_test_attribute_multiselect',
            'label' => 'Klevu Test Attribute (Multiselect)',
            'attribute_type' => 'multiselect',
            'index_as' => IndexType::INDEX,
            'options' => [
                'Option 1',
                'Option 2',
            ],
        ]);
        $attributeFixture = $this->attributeFixturePool->get('klevu_test_attribute_multiselect');
        $magentoAttribute = $attributeFixture->getAttribute();

        $convertEavAttributeToIndexingAttributeAction = $this->instantiateTestObject();

        $resultWithoutStore = $convertEavAttributeToIndexingAttributeAction->execute(
            entityType: 'KLEVU_PRODUCT',
            attribute: $magentoAttribute,
            store: null,
        );
        $this->assertInstanceOf(
            expected: MagentoAttributeInterface::class,
            actual: $resultWithoutStore,
        );
        $this->assertTrue(
            condition: $resultWithoutStore->usesSourceModel(),
            message: 'uses source model (without store)',
        );
        $this->assertTrue(
            condition: $resultWithoutStore->allowsMultipleValues(),
            message: 'allows multiple values (without store)',
        );

        $resultWithStore = $convertEavAttributeToIndexingAttributeAction->execute(
            entityType: 'KLEVU_PRODUCT',
            attribute: $magentoAttribute,
            store: $this->storeManager->getStore('default'),
        );
        $this->assertInstanceOf(
            expected: MagentoAttributeInterface::class,
            actual: $resultWithStore,
        );
        $this->assertTrue(
            condition: $resultWithStore->usesSourceModel(),
            message: 'uses source model (with store)',
        );
        $this->assertTrue(
            condition: $resultWithStore->allowsMultipleValues(),
            message: 'allows multiple values (with store)',
        );
    }

    /**
     * @testWith ["category_ids", "listCategory"]
     *           ["klevu_image", "image"]
     *           ["klevu_rating", "rating"]
     *           ["klevu_rating_count", "ratingCount"]
     *           ["quantity_and_stock_status", "inStock"]
     *           ["short_description", "shortDescription"]
     *           ["url_key", "url"]
     */
    public function testExecute_WithAttributeMapping(
        string $existingAttributeCode,
        string $expectedMappedAttributeName,
    ): void {
        try {
            $magentoAttribute = $this->productAttributeRepository->get(
                attributeCode: $existingAttributeCode,
            );
        } catch (NoSuchEntityException) {
            $this->markTestSkipped(
                message: sprintf(
                    'Could not find load existing attribute "%s"',
                    $existingAttributeCode,
                ),
            );
        }

        $convertEavAttributeToIndexingAttributeAction = $this->instantiateTestObject();

        $resultWithoutStore = $convertEavAttributeToIndexingAttributeAction->execute(
            entityType: 'KLEVU_PRODUCT',
            attribute: $magentoAttribute,
            store: null,
        );

        $this->assertInstanceOf(
            expected: MagentoAttributeInterface::class,
            actual: $resultWithoutStore,
        );
        $this->assertSame(
            expected: $existingAttributeCode,
            actual: $resultWithoutStore->getAttributeCode(),
            message: 'attribute_code (without store)',
        );
        $this->assertSame(
            expected: $expectedMappedAttributeName,
            actual: $resultWithoutStore->getKlevuAttributeName(),
            message: 'klevu_attribute_name (without store)',
        );

        $resultWithStore = $convertEavAttributeToIndexingAttributeAction->execute(
            entityType: 'KLEVU_PRODUCT',
            attribute: $magentoAttribute,
            store: $this->storeManager->getStore('default'),
        );

        $this->assertInstanceOf(
            expected: MagentoAttributeInterface::class,
            actual: $resultWithStore,
        );
        $this->assertSame(
            expected: $existingAttributeCode,
            actual: $resultWithStore->getAttributeCode(),
            message: 'attribute_code (with store)',
        );
        $this->assertSame(
            expected: $expectedMappedAttributeName,
            actual: $resultWithStore->getKlevuAttributeName(),
            message: 'klevu_attribute_name (with store)',
        );
    }

    /**
     * @testWith ["visibility", "MULTIVALUE"]
     */
    public function testExecute_WithAttributeTypeMapping_ByAttributeCode(
        string $existingAttributeCode,
        string $expectedDataTypeValue,
    ): void {
        try {
            $magentoAttribute = $this->productAttributeRepository->get(
                attributeCode: $existingAttributeCode,
            );
        } catch (NoSuchEntityException) {
            $this->markTestSkipped(
                message: sprintf(
                    'Could not find load existing attribute "%s"',
                    $existingAttributeCode,
                ),
            );
        }

        $convertEavAttributeToIndexingAttributeAction = $this->instantiateTestObject();

        $resultWithoutStore = $convertEavAttributeToIndexingAttributeAction->execute(
            entityType: 'KLEVU_PRODUCT',
            attribute: $magentoAttribute,
            store: null,
        );

        $this->assertInstanceOf(
            expected: MagentoAttributeInterface::class,
            actual: $resultWithoutStore,
        );
        $this->assertEquals(
            expected: DataType::from($expectedDataTypeValue),
            actual: $resultWithoutStore->getKlevuAttributeType(),
            message: 'klevu_attribute_type (without store)',
        );

        $resultWithStore = $convertEavAttributeToIndexingAttributeAction->execute(
            entityType: 'KLEVU_PRODUCT',
            attribute: $magentoAttribute,
            store: $this->storeManager->getStore('default'),
        );

        $this->assertInstanceOf(
            expected: MagentoAttributeInterface::class,
            actual: $resultWithStore,
        );
        $this->assertEquals(
            expected: DataType::from($expectedDataTypeValue),
            actual: $resultWithStore->getKlevuAttributeType(),
            message: 'klevu_attribute_type (without store)',
        );
    }

    /**
     * @return mixed[][]
     */
    public static function dataProvider_testExecute_WithAttributeTypeMapping_Determined(): array
    {
        return [
            [ // Date Attribute
                [
                    'code' => 'klevu_test_attribute_datetime',
                    'label' => 'Klevu Test Attribute (Datetime)',
                    'attribute_type' => 'datetime',
                    'backend_model' => 'Magento\Eav\Model\Entity\Attribute\Backend\Datetime',
                    'backend_type' => 'datetime',
                    'frontend_model' => 'Magento\Eav\Model\Entity\Attribute\Frontend\Datetime',
                    'frontend_input' => 'date',
                    'source_model' => null,
                    'index_as' => IndexType::INDEX,
                ],
                DataType::STRING,
            ],
            [ // Boolean Attribute
                [
                    'code' => 'klevu_test_attribute_boolean',
                    'label' => 'Klevu Test Attribute (Boolean)',
                    'attribute_type' => 'boolean',
                    'backend_model' => null,
                    'backend_type' => 'int',
                    'frontend_model' => null,
                    'frontend_input' => 'select',
                    'source_model' => 'Magento\Eav\Model\Entity\Attribute\Source\Boolean',
                    'index_as' => IndexType::INDEX,
                ],
                DataType::STRING,
            ],
            [ // MultiValue Attribute
                [
                    'code' => 'klevu_test_attribute_multivalue',
                    'label' => 'Klevu Test Attribute (Multivalue)',
                    'attribute_type' => 'multiselect',
                    'backend_model' => 'Magento\Catalog\Model\Category\Attribute\Backend\Sortby',
                    'backend_type' => 'text',
                    'frontend_model' => null,
                    'frontend_input' => 'multiselect',
                    'source_model' => 'Magento\Catalog\Model\Category\Attribute\Source\Sortby',
                    'index_as' => IndexType::INDEX,
                    'options' => [
                        'Option 1',
                    ],
                ],
                DataType::MULTIVALUE,
            ],
            [ // Enum Attribute
                [
                    'code' => 'klevu_test_attribute_enum',
                    'label' => 'Klevu Test Attribute (Enum)',
                    'attribute_type' => 'enum',
                    'backend_model' => null,
                    'backend_type' => 'int',
                    'frontend_model' => null,
                    'frontend_input' => 'select',
                    'source_model' => 'Magento\Eav\Model\Entity\Attribute\Source\Table',
                    'index_as' => IndexType::INDEX,
                    'options' => [
                        'Option 1',
                    ],
                ],
                DataType::STRING, // Update once supported in Klevu Indexing v3
            ],
            [ // Numeric Attribute
                [
                    'code' => 'klevu_test_attribute_number',
                    'label' => 'Klevu Test Attribute (Number)',
                    'attribute_type' => 'price',
                    'backend_model' => null,
                    'backend_type' => 'decimal',
                    'frontend_model' => null,
                    'frontend_input' => 'text',
                    'source_model' => null,
                    'index_as' => IndexType::INDEX,
                ],
                DataType::NUMBER,
            ],
        ];
    }

    /**
     * @dataProvider dataProvider_testExecute_WithAttributeTypeMapping_Determined
     *
     * @param mixed[] $attributeData
     * @param DataType|null $expectedDataType
     *
     * @return void
     * @throws NoSuchEntityException
     * @throws AttributeMappingMissingException
     */
    public function testExecute_WithAttributeTypeMapping_Determined(
        array $attributeData,
        ?DataType $expectedDataType,
    ): void {
        $this->createAttribute(
            attributeData: array_merge(
                $attributeData,
                [
                    'key' => 'klevu_test_attribute_attributeMappingDetermined',
                ],
            ),
        );
        $attributeFixture = $this->attributeFixturePool->get('klevu_test_attribute_attributeMappingDetermined');
        $magentoAttribute = $attributeFixture->getAttribute();

        $convertEavAttributeToIndexingAttributeAction = $this->instantiateTestObject();

        $resultWithoutStore = $convertEavAttributeToIndexingAttributeAction->execute(
            entityType: 'KLEVU_PRODUCT',
            attribute: $magentoAttribute,
            store: null,
        );

        $this->assertInstanceOf(
            expected: MagentoAttributeInterface::class,
            actual: $resultWithoutStore,
        );
        $this->assertEquals(
            expected: $expectedDataType,
            actual: $resultWithoutStore->getKlevuAttributeType(),
            message: 'klevu_attribute_type (without store)',
        );

        $resultWithStore = $convertEavAttributeToIndexingAttributeAction->execute(
            entityType: 'KLEVU_PRODUCT',
            attribute: $magentoAttribute,
            store: $this->storeManager->getStore('default'),
        );

        $this->assertInstanceOf(
            expected: MagentoAttributeInterface::class,
            actual: $resultWithStore,
        );
        $this->assertEquals(
            expected: $expectedDataType,
            actual: $resultWithStore->getKlevuAttributeType(),
            message: 'klevu_attribute_type (without store)',
        );
    }

    /**
     * @dataProvider dataProvider_testExecute_WithAttributeTypeMapping_Determined
     *
     * @param mixed[] $attributeData
     * @param DataType|null $expectedDataType
     *
     * @return void
     * @throws NoSuchEntityException
     * @throws AttributeMappingMissingException
     */
    public function testExecute_WithAttributeTypeMapping_Determined_ForStaticAttributes(
        array $attributeData,
        ?DataType $expectedDataType,
    ): void {
        $this->createAttribute(
            attributeData: array_merge(
                $attributeData,
                [
                    'key' => 'klevu_test_attribute_attributeMappingDetermined',
                ],
            ),
        );
        $attributeFixture = $this->attributeFixturePool->get('klevu_test_attribute_attributeMappingDetermined');
        $magentoAttribute = $attributeFixture->getAttribute();

        $convertEavAttributeToIndexingAttributeAction = $this->instantiateTestObject();

        $resultWithoutStore = $convertEavAttributeToIndexingAttributeAction->execute(
            entityType: 'KLEVU_PRODUCT_STATIC',
            attribute: $magentoAttribute,
            store: null,
        );

        $this->assertInstanceOf(
            expected: MagentoAttributeInterface::class,
            actual: $resultWithoutStore,
        );
        $this->assertEquals(
            expected: $expectedDataType,
            actual: $resultWithoutStore->getKlevuAttributeType(),
            message: 'klevu_attribute_type (without store)',
        );

        $resultWithStore = $convertEavAttributeToIndexingAttributeAction->execute(
            entityType: 'KLEVU_PRODUCT',
            attribute: $magentoAttribute,
            store: $this->storeManager->getStore('default'),
        );

        $this->assertInstanceOf(
            expected: MagentoAttributeInterface::class,
            actual: $resultWithStore,
        );
        $this->assertEquals(
            expected: $expectedDataType,
            actual: $resultWithStore->getKlevuAttributeType(),
            message: 'klevu_attribute_type (without store)',
        );
    }

    /**
     * @return MockObject&ObserverInterface
     */
    private function getMockObserver(): MockObject
    {
        return $this->getMockBuilder(ObserverInterface::class) // @phpstan-ignore-line return type
            ->disableOriginalConstructor()
            ->getMock();
    }
}
