<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Block\Adminhtml\Product\Attribute\Edit\Tab;

use Klevu\Configuration\Test\Integration\Controller\Adminhtml\GetAdminFrontNameTrait;
use Klevu\IndexingApi\Model\Source\IndexType;
use Klevu\IndexingProducts\Model\Source\Aspect;
use Klevu\TestFixtures\Catalog\Attribute\AttributeFixturePool;
use Klevu\TestFixtures\Catalog\AttributeTrait;
use Magento\Catalog\Api\Data\ProductAttributeInterface;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Eav\Api\AttributeRepositoryInterface;
use Magento\Framework\Exception\AuthenticationException;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\Request as TestFrameworkRequest;
use Magento\TestFramework\Response as TestFrameworkResponse;
use Magento\TestFramework\TestCase\AbstractBackendController as AbstractBackendControllerTestCase;

/**
 * @covers \Klevu\IndexingProducts\Block\Adminhtml\Product\Attribute\Edit\Tab\KlevuIndexingProperties::class
 */
class KlevuIndexingPropertiesOutputTest extends AbstractBackendControllerTestCase
{
    use AttributeTrait;
    use GetAdminFrontNameTrait;

    /**
     * @var ObjectManagerInterface|null
     */
    private ?ObjectManagerInterface $objectManager = null; // @phpstan-ignore-line

    /**
     * @return void
     * @throws AuthenticationException
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->uri = $this->getAdminFrontName() . '/catalog/product_attribute/edit';
        $this->resource = 'Klevu_Indexing::indexing';
        $this->objectManager = Bootstrap::getObjectManager();
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
    }

    public function testAclHasAccess(): void
    {
        // Test ACL has action is not required here.
    }

    public function testAclNoAccess(): void
    {
        // Test ACL denying access is not required here.
    }

    /**
     * @dataProvider dataProvider_testToHtml_AddsNote_WhenFieldsetIsCoreKlevuAttribute
     * @magentoAppArea adminhtml
     */
    public function testToHtml_AddsNote_WhenFieldsetIsCoreKlevuAttribute(string $attributeCode): void
    {
        $attributeRegistry = $this->objectManager->get(AttributeRepositoryInterface::class);
        $attribute = $attributeRegistry->get(entityTypeCode: 'catalog_product', attributeCode: $attributeCode);
        if (!$attribute->getAttributeId()) {
            $this->markTestSkipped('Attribute does not exist. Move onto next.');
        }

        /** @var TestFrameworkRequest $request */
        $request = $this->getRequest();
        $request->setParam(key: 'attribute_id', value: $attribute->getId());
        $this->dispatch(uri: $this->uri);

        /** @var TestFrameworkResponse $response */
        $response = $this->getResponse();
        $responseBody = $response->getBody();

        $this->assertSame(expected: 200, actual: $response->getStatusCode());

        $matches = [];
        preg_match(
            pattern: '#<div id="klevu_default_indexed_attribute" class="control-value admin__field-value">'
            . 'This attribute is used as a standard Klevu attribute and is automatically indexed to Klevu\.'
            . '<br/>'
            . 'Sync settings can not be changed via the admin for this attribute\.</div>#',
            subject: $responseBody,
            matches: $matches,
        );
        $this->assertCount(
            expectedCount: 1,
            haystack: $matches,
            message: 'Show core attribute message',
        );

        $matches = [];
        preg_match(
            pattern: '#Register with Klevu#',
            subject: $responseBody,
            matches: $matches,
        );
        $this->assertCount(
            expectedCount: 0,
            haystack: $matches,
            message: 'Do not show dropdown',
        );

        $matches = [];
        preg_match(
            pattern: '#Triggers Update of#',
            subject: $responseBody,
            matches: $matches,
        );
        $this->assertCount(
            expectedCount: 0,
            haystack: $matches,
            message: 'Do not show dropdown',
        );
    }

    /**
     * @return string[][]
     */
    public function dataProvider_testToHtml_AddsNote_WhenFieldsetIsCoreKlevuAttribute(): array
    {
        return [
            [ProductAttributeInterface::CODE_DESCRIPTION],
            [ProductInterface::NAME],
            [ProductInterface::SKU],
            [ProductInterface::VISIBILITY],
        ];
    }

    /**
     * @magentoAppArea adminhtml
     * @testWith ["weee"]
     *           ["image"]
     */
    public function testToHtml_AddsNote_WhenAttributeIsNotSupportedType(string $invalidAttributeType): void
    {
        $this->createAttribute([
            'attribute_type' => $invalidAttributeType,
        ]);
        $attributeFixture = $this->attributeFixturePool->get('test_attribute');

        /** @var TestFrameworkRequest $request */
        $request = $this->getRequest();
        $request->setParam(key: 'attribute_id', value: $attributeFixture->getAttributeId());
        $this->dispatch(uri: $this->uri);

        /** @var TestFrameworkResponse $response */
        $response = $this->getResponse();
        $responseBody = $response->getBody();

        $this->assertSame(expected: 200, actual: $response->getStatusCode());

        $matches = [];
        preg_match(
            pattern: '#<div id="klevu_unsupported_attribute" class="control-value admin__field-value">'
            . 'This attribute is not supported for syncing to Klevu\.</div>#',
            subject: $responseBody,
            matches: $matches,
        );
        $this->assertCount(
            expectedCount: 1,
            haystack: $matches,
            message: 'Show unsupported attribute message',
        );

        $matches = [];
        preg_match(
            pattern: '#Register with Klevu#',
            subject: $responseBody,
            matches: $matches,
        );
        $this->assertCount(
            expectedCount: 0,
            haystack: $matches,
            message: 'Do not show register dropdown',
        );

        $matches = [];
        preg_match(
            pattern: '#Triggers Update of#',
            subject: $responseBody,
            matches: $matches,
        );
        $this->assertCount(
            expectedCount: 0,
            haystack: $matches,
            message: 'Do not show triggers dropdown',
        );
    }

    /**
     * @magentoAppArea adminhtml
     */
    public function testToHtml_AddsSelect_WhenFieldsetIsNonCoreKlevuAttribute(): void
    {
        $this->createAttribute([
            'attribute_type' => 'boolean',
            'index_as' => IndexType::INDEX,
            'aspect' => Aspect::PRICE,
        ]);
        $attributeFixture = $this->attributeFixturePool->get('test_attribute');

        /** @var TestFrameworkRequest $request */
        $request = $this->getRequest();
        $request->setParam(key: 'attribute_id', value: $attributeFixture->getAttributeId());
        $this->dispatch(uri: $this->uri);

        /** @var TestFrameworkResponse $response */
        $response = $this->getResponse();
        $responseBody = $response->getBody();

        $this->assertSame(expected: 200, actual: $response->getStatusCode());

        $matches = [];
        preg_match(
            pattern: '#This attribute is used as a standard Klevu attribute and is automatically indexed to Klevu\.#',
            subject: $responseBody,
            matches: $matches,
        );
        $this->assertCount(
            expectedCount: 0,
            haystack: $matches,
            message: 'Do not show core attribute message',
        );

        $matches = [];
        $pattern = '#<select id="klevu_is_indexable" name="klevu_is_indexable" title="Register with Klevu".*>\s*'
            . '<option value="0".*>No</option>\s*'
            . '<option value="1".*selected="selected">Yes</option>\s*'
            . '</select>#';
        preg_match(
            pattern: $pattern,
            subject: $responseBody,
            matches: $matches,
        );
        $this->assertCount(
            expectedCount: 1,
            haystack: $matches,
            message: 'Show dropdown',
        );

        $matches = [];
        $pattern = '#<select id="klevu_aspect_mapping" name="klevu_aspect_mapping" title="Triggers Update of".*>\s*'
            . '<option value="0".*>Nothing</option>\s*'
            . '<option value="1".*>Everything</option>\s*'
            . '<option value="2".*>Attributes</option>\s*'
            . '<option value="3".*>Relations</option>\s*'
            . '<option value="5".*selected="selected">Price</option>\s*'
            . '<option value="6".*>Stock</option>\s*'
            . '<option value="7".*>Visibility</option>\s*'
            . '</select>#';
        preg_match(
            pattern: $pattern,
            subject: $responseBody,
            matches: $matches,
        );
        $this->assertCount(
            expectedCount: 1,
            haystack: $matches,
            message: 'Show trigger updates dropdown',
        );
    }
}
