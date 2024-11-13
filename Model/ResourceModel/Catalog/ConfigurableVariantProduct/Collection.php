<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Model\ResourceModel\Catalog\ConfigurableVariantProduct;

use Klevu\IndexingProducts\Model\ResourceModel\Catalog\ProductCollectionTrait;
use Klevu\IndexingProducts\Model\ResourceModel\Product\Collection as ProductCollection;
use Klevu\IndexingProducts\Service\Modifier\Catalog\Product\Collection\AddParentAttributeToCollectionModifierInterface;
use Klevu\IndexingProducts\Service\Provider\Catalog\Product\Collection\AddParentAttributeToCollectionModifierProviderInterface; //phpcs:ignore Generic.Files.LineLength.TooLong
use Magento\Catalog\Model\Indexer\Category\Product\TableMaintainer;
use Magento\Catalog\Model\Indexer\Product\Flat\State;
use Magento\Catalog\Model\Indexer\Product\Price\PriceTableResolver;
use Magento\Catalog\Model\Product\Gallery\ReadHandler as GalleryReadHandler;
use Magento\Catalog\Model\Product\OptionFactory;
use Magento\Catalog\Model\ResourceModel\Category;
use Magento\Catalog\Model\ResourceModel\Helper;
use Magento\Catalog\Model\ResourceModel\Product\Collection\ProductLimitationFactory;
use Magento\Catalog\Model\ResourceModel\Product\Gallery;
use Magento\Catalog\Model\ResourceModel\Url;
use Magento\CatalogInventory\Helper\Stock as CategoryInventoryStockHelper;
use Magento\CatalogUrlRewrite\Model\Storage\DbStorage;
use Magento\Customer\Api\GroupManagementInterface;
use Magento\Customer\Model\Session;
use Magento\Eav\Model\Config;
use Magento\Eav\Model\Entity;
use Magento\Eav\Model\EntityFactory as EavEntityFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Data\Collection\Db\FetchStrategyInterface;
use Magento\Framework\Data\Collection\EntityFactory as CollectionEntityFactory;
use Magento\Framework\DataObject;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\EntityManager\MetadataPool;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Indexer\DimensionFactory;
use Magento\Framework\Module\Manager;
use Magento\Framework\Stdlib\DateTime;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Framework\Validator\UniversalFactory;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

class Collection extends ProductCollection
{
    use ProductCollectionTrait;

    public const TABLE_ALIAS_ASSOCIATED_PRODUCT = 'associated_product';
    private const FIELD_PLACEHOLDER_PARENT_ID = 'parent_id';

    /**
     * @var AddParentAttributeToCollectionModifierInterface[]
     */
    private readonly array $addParentAttributeToCollectionModifiers;
    /**
     * @var CategoryInventoryStockHelper|mixed
     */
    private readonly mixed $categoryInventoryStockHelper;

    /**
     * @param AddParentAttributeToCollectionModifierProviderInterface $addParentAttributeToCollectionModifierProvider
     * @param CollectionEntityFactory $entityFactory
     * @param LoggerInterface $logger
     * @param FetchStrategyInterface $fetchStrategy
     * @param ManagerInterface $eventManager
     * @param Config $eavConfig
     * @param ResourceConnection $resource
     * @param EavEntityFactory $eavEntityFactory
     * @param Helper $resourceHelper
     * @param UniversalFactory $universalFactory
     * @param StoreManagerInterface $storeManager
     * @param Manager $moduleManager
     * @param State $catalogProductFlatState
     * @param ScopeConfigInterface $scopeConfig
     * @param OptionFactory $productOptionFactory
     * @param Url $catalogUrl
     * @param TimezoneInterface $localeDate
     * @param Session $customerSession
     * @param DateTime $dateTime
     * @param GroupManagementInterface $groupManagement
     * @param AdapterInterface|null $connection
     * @param ProductLimitationFactory|null $productLimitationFactory
     * @param MetadataPool|null $metadataPool
     * @param TableMaintainer|null $tableMaintainer
     * @param PriceTableResolver|null $priceTableResolver
     * @param DimensionFactory|null $dimensionFactory
     * @param Category|null $categoryResourceModel
     * @param DbStorage|null $urlFinder
     * @param GalleryReadHandler|null $productGalleryReadHandler
     * @param Gallery|null $mediaGalleryResource
     * @param CategoryInventoryStockHelper|null $categoryInventoryStockHelper
     */
    public function __construct(
        AddParentAttributeToCollectionModifierProviderInterface $addParentAttributeToCollectionModifierProvider,
        CollectionEntityFactory $entityFactory,
        LoggerInterface $logger,
        FetchStrategyInterface $fetchStrategy,
        ManagerInterface $eventManager,
        Config $eavConfig,
        ResourceConnection $resource,
        EavEntityFactory $eavEntityFactory,
        Helper $resourceHelper,
        UniversalFactory $universalFactory,
        StoreManagerInterface $storeManager,
        Manager $moduleManager,
        State $catalogProductFlatState,
        ScopeConfigInterface $scopeConfig,
        OptionFactory $productOptionFactory,
        Url $catalogUrl,
        TimezoneInterface $localeDate,
        Session $customerSession,
        DateTime $dateTime,
        GroupManagementInterface $groupManagement,
        // phpcs:disable SlevomatCodingStandard.TypeHints.NullableTypeForNullDefaultValue.NullabilityTypeMissing
        AdapterInterface $connection = null,
        ProductLimitationFactory $productLimitationFactory = null,
        MetadataPool $metadataPool = null,
        TableMaintainer $tableMaintainer = null,
        PriceTableResolver $priceTableResolver = null,
        DimensionFactory $dimensionFactory = null,
        Category $categoryResourceModel = null,
        DbStorage $urlFinder = null,
        GalleryReadHandler $productGalleryReadHandler = null,
        Gallery $mediaGalleryResource = null,
        CategoryInventoryStockHelper $categoryInventoryStockHelper = null,
        // phpcs:enable SlevomatCodingStandard.TypeHints.NullableTypeForNullDefaultValue.NullabilityTypeMissing
    ) {
        parent::__construct(
            entityFactory: $entityFactory,
            logger: $logger,
            fetchStrategy: $fetchStrategy,
            eventManager: $eventManager,
            eavConfig: $eavConfig,
            resource: $resource,
            eavEntityFactory: $eavEntityFactory,
            resourceHelper: $resourceHelper,
            universalFactory: $universalFactory,
            storeManager: $storeManager,
            moduleManager: $moduleManager,
            catalogProductFlatState: $catalogProductFlatState,
            scopeConfig: $scopeConfig,
            productOptionFactory: $productOptionFactory,
            catalogUrl: $catalogUrl,
            localeDate: $localeDate,
            customerSession: $customerSession,
            dateTime: $dateTime,
            groupManagement: $groupManagement,
            connection: $connection,
            productLimitationFactory: $productLimitationFactory,
            metadataPool: $metadataPool,
            tableMaintainer: $tableMaintainer,
            priceTableResolver: $priceTableResolver,
            dimensionFactory: $dimensionFactory,
            categoryResourceModel: $categoryResourceModel,
            urlFinder: $urlFinder,
            productGalleryReadHandler: $productGalleryReadHandler,
            mediaGalleryResource: $mediaGalleryResource,
        );

        $this->addParentAttributeToCollectionModifiers = $addParentAttributeToCollectionModifierProvider->get();
        $objectManager = ObjectManager::getInstance();
        $this->categoryInventoryStockHelper = $categoryInventoryStockHelper
            ?: $objectManager->get(CategoryInventoryStockHelper::class);
    }

    /**
     * @param StoreInterface|null $store
     *
     * @return Collection
     * @throws \Zend_Db_Select_Exception
     * @throws LocalizedException
     */
    public function getConfigurableCollection(?StoreInterface $store = null): Collection
    {
        $this->addAttributeToSelect(attribute: '*');
        /** @var Store $store */
        $this->addStoreFilter($store);
        $this->joinAssociatedProducts();
        $this->joinProductParentAttributes(store: $store);
        $this->joinStock();

        return $this;
    }

    /**
     * @param DataObject $item
     *
     * @return string
     */
    protected function _getItemId(DataObject $item): string
    {
        $parentId = $item->getData(self::FIELD_PLACEHOLDER_PARENT_ID);
        $entityId = $item->getData(Entity::DEFAULT_ENTITY_ID_FIELD);

        return null !== $parentId
            ? $entityId . '-' . $parentId
            : $entityId;
    }

    /**
     * @param DataObject $item
     *
     * @return DataObject
     */
    protected function beforeAddLoadedItem(DataObject $item): DataObject
    {
        foreach ($this->addParentAttributeToCollectionModifiers as $addParentAttributeToCollectionModifier) {
            $addParentAttributeToCollectionModifier->setProductAttributeValue(item: $item);
        }

        return $item;
    }

    /**
     * @param StoreInterface|null $store
     *
     * @return void
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    private function joinProductParentAttributes(?StoreInterface $store = null): void
    {
        foreach ($this->addParentAttributeToCollectionModifiers as $addParentAttributeToCollectionModifier) {
            $addParentAttributeToCollectionModifier->createParentAttributeColumn(
                collection: $this,
                store: $store,
            );
        }
    }

    /**
     * @return void
     * @throws \Zend_Db_Select_Exception
     * @throws LocalizedException
     */
    private function joinAssociatedProducts(): void
    {
        $select = $this->getSelect();
        $from = $select->getPart(part: Select::FROM);
        if (array_key_exists(key: static::TABLE_ALIAS_ASSOCIATED_PRODUCT, array: $from)) {
            return;
        }
        $select->joinInner(
            name: [
                static::TABLE_ALIAS_ASSOCIATED_PRODUCT => $this->getTable(table: 'catalog_product_super_link'),
            ],
            cond: implode(
                ' ' . Select::SQL_AND . ' ',
                [
                    // note in adobe commerce parent_id is linked to row_id, but product_id is linked to entity_id
                    static::TABLE_ALIAS_ASSOCIATED_PRODUCT . '.product_id = e.' . Entity::DEFAULT_ENTITY_ID_FIELD,
                ],
            ),
            cols: [],
        );
        $select->joinInner(
            name: ['parent_entity' => $this->getTable('catalog_product_entity')],
            cond: implode(
                ' ' . Select::SQL_AND . ' ',
                [
                    // phpcs:ignore Generic.Files.LineLength.TooLong
                    'parent_entity.' . $this->getLinkField($this) . ' = ' . static::TABLE_ALIAS_ASSOCIATED_PRODUCT . '.parent_id',
                ],
            ),
            cols: [self::FIELD_PLACEHOLDER_PARENT_ID => Entity::DEFAULT_ENTITY_ID_FIELD],
        );
    }

    /**
     * Ensures is_salebale is set on each variant product
     *
     * @return void
     */
    private function joinStock(): void
    {
        // categoryInventoryStockHelper is deprecated in favour of MSI
        // however, MSI has a plugin on the addInStockFilterToCollection method
        // so we can safely use this in case the merchant has removed MSI.
        $this->categoryInventoryStockHelper->addInStockFilterToCollection(
            collection: $this, // @phpstan-ignore-line incorrect type hint in Magento core
        );
    }
}
