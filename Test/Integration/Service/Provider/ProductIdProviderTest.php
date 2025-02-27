<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Test\Integration\Service\Provider;

use Klevu\Indexing\Test\Integration\Traits\IndexingEntitiesTrait;
use Klevu\IndexingApi\Service\Provider\ProductIdProviderInterface;
use Klevu\IndexingProducts\Service\Provider\ProductIdProvider;
use Klevu\TestFixtures\Catalog\ProductTrait;
use Klevu\TestFixtures\Store\StoreFixturesPool;
use Klevu\TestFixtures\Store\StoreTrait;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Klevu\TestFixtures\Traits\TestInterfacePreferenceTrait;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\AbstractModel;
use Magento\Catalog\Model\ResourceModel\Product as ProductResourceModel;
use Magento\Eav\Model\Entity;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\EntityManager\EntityMetadataInterface;
use Magento\Framework\EntityManager\MetadataPool;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use TddWizard\Fixtures\Catalog\ProductFixturePool;

/**
 * @covers \Klevu\IndexingProducts\Service\Provider\ProductIdProvider
 * @method ProductIdProviderInterface instantiateTestObject(?array $arguments = null)
 * @method ProductIdProviderInterface instantiateTestObjectFromInterface(?array $arguments = null)
 */
class ProductIdProviderTest extends TestCase
{
    use IndexingEntitiesTrait;
    use ObjectInstantiationTrait;
    use ProductTrait;
    use StoreTrait;
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
        $this->implementationFqcn = ProductIdProvider::class;
        $this->interfaceFqcn = ProductIdProviderInterface::class;
        $this->objectManager = Bootstrap::getObjectManager();
        $this->storeFixturesPool = $this->objectManager->get(StoreFixturesPool::class);
        $this->productFixturePool = $this->objectManager->get(ProductFixturePool::class);
    }

    /**
     * @return void
     * @throws \Exception
     */
    protected function tearDown(): void
    {
        parent::tearDown();

        $this->productFixturePool->rollback();
        $this->storeFixturesPool->rollback();
    }

    public function testGetBySku_ReturnsNull_WhenSkuDoesNotExist(): void
    {
        $provider = $this->instantiateTestObject();
        $productId = $provider->getBySku(sku: 'some-sku');

        $this->assertNull($productId);
    }

    public function testGetBySkus_ReturnsEmptyArray_WhenSkuDoesNotExist(): void
    {
        $provider = $this->instantiateTestObject();
        $productIds = $provider->getBySkus(skus: ['some-sku']);

        $this->assertEmpty(actual: $productIds, message: 'Product IDs empty');
    }

    /**
     * @magentoAppIsolation enabled
     * @magentoDbIsolation disabled
     */
    public function testGetBySku_ReturnsProductId(): void
    {
        $this->createProduct();
        $productFixture = $this->productFixturePool->get('test_product');

        $provider = $this->instantiateTestObject();
        $productId = $provider->getBySku(sku: $productFixture->getSku());

        $this->assertSame(expected: (int)$productFixture->getId(), actual: $productId);
    }

    /**
     * @magentoAppIsolation enabled
     * @magentoDbIsolation disabled
     */
    public function testGetBySkus_ReturnsArrayOfProductIds(): void
    {
        $this->createProduct([
            'key' => 'test_product_1',
            'name' => 'Test Product 1',
            'sku' => 'TestProduct1',
        ]);
        $product1Fixture = $this->productFixturePool->get('test_product_1');

        $this->createProduct([
            'key' => 'test_product_2',
            'name' => 'Test Product 2',
            'sku' => 'TestProduct2',
        ]);
        $product2Fixture = $this->productFixturePool->get('test_product_2');

        $provider = $this->instantiateTestObject();
        $productIds = $provider->getBySkus(skus: [$product1Fixture->getSku(), $product2Fixture->getSku()]);

        $this->assertArrayHasKey(key: $product1Fixture->getSku(), array: $productIds);
        $this->assertSame(expected: (int)$product1Fixture->getId(), actual: $productIds[$product1Fixture->getSku()]);

        $this->assertArrayHasKey(key: $product2Fixture->getSku(), array: $productIds);
        $this->assertSame(expected: (int)$product2Fixture->getId(), actual: $productIds[$product2Fixture->getSku()]);
    }

    public function testGetByLinkFields_ReturnsProvidedDataFormMagentoOpenSource(): void
    {
        $mockProductMetaData = $this->getMockBuilder(EntityMetadataInterface::class)
            ->getMock();
        $mockProductMetaData->expects($this->once())
            ->method('getLinkField')
            ->willReturn(Entity::DEFAULT_ENTITY_ID_FIELD);

        $mockMetaDataPool = $this->getMockBuilder(MetadataPool::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockMetaDataPool->expects($this->once())
            ->method('getMetadata')
            ->with(ProductInterface::class)
            ->willReturn($mockProductMetaData);

        $data = [1, 2, 3];

        $provider = $this->instantiateTestObject([
            'metadataPool' => $mockMetaDataPool,
        ]);
        $result = $provider->getByLinkFields($data);

        $this->assertSame(expected: $data, actual: $result);
    }

    public function testGetByLinkFields_ReturnsMappedDataFormAdobeCommerce(): void
    {
        $expected = [1, 2, 4567];
        $mockProductMetaData = $this->getMockBuilder(EntityMetadataInterface::class)
            ->getMock();
        $mockProductMetaData->expects($this->once())
            ->method('getLinkField')
            ->willReturn('row_id');

        $mockMetaDataPool = $this->getMockBuilder(MetadataPool::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockMetaDataPool->expects($this->once())
            ->method('getMetadata')
            ->with(ProductInterface::class)
            ->willReturn($mockProductMetaData);

        $mockSelect = $this->getMockBuilder(Select::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockSelect->method('from');
        $mockSelect->method('where');

        $mockConnection = $this->getMockBuilder(AdapterInterface::class)
            ->getMock();
        $mockConnection->expects($this->once())
            ->method('select')
            ->willReturn($mockSelect);
        $mockConnection->expects($this->once())
            ->method('fetchCol')
            ->willReturn($expected);

        $mockProductResourceModel = $this->getMockBuilder(ProductResourceModel::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockProductResourceModel->expects($this->once())
            ->method('getConnection')
            ->willReturn($mockConnection);

        $data = [1, 2, 3];

        $provider = $this->instantiateTestObject([
            'metadataPool' => $mockMetaDataPool,
            'productResourceModel' => $mockProductResourceModel,
        ]);
        $result = $provider->getByLinkFields($data);

        $this->assertSame(expected: $expected, actual: $result);
    }

    /**
     * @magentoAppIsolation enabled
     * @magentoDbIsolation disabled
     */
    public function testGetByLinkFields_ForCurrentMagentoVersion(): void
    {
        $metadataPool = $this->objectManager->get(MetadataPool::class);
        $productMetadata = $metadataPool->getMetadata(ProductInterface::class);

        $this->createProduct();
        $productFixture = $this->productFixturePool->get('test_product');
        /** @var AbstractModel&ProductInterface $product */
        $product = $productFixture->getProduct();

        $provider = $this->instantiateTestObject();
        $result = $provider->getByLinkFields([(int)$product->getData($productMetadata->getLinkField())]);

        $this->assertSame(
            expected: [(int)$product->getId()],
            actual: $result,
        );
    }
}
