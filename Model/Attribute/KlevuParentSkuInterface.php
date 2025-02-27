<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Model\Attribute;

interface KlevuParentSkuInterface
{
    public const ATTRIBUTE_ID = 999999;
    public const ATTRIBUTE_CODE = 'klevu_parent_sku';
    public const ATTRIBUTE_LABEL = 'Parent SKU';
    public const IS_SEARCHABLE = true;
    public const IS_FILTERABLE = false;
    public const IS_RETURNABLE = true;
}
