<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Pipeline\Transformer;

use Klevu\IndexingApi\Service\Provider\Catalog\CategoryIdProviderInterface;
use Klevu\Pipelines\Exception\Transformation\InvalidInputDataException;
use Klevu\Pipelines\Model\ArgumentIterator;
use Klevu\Pipelines\Transformer\TransformerInterface;
use Magento\Catalog\Api\Data\ProductInterface;

class ToCategoryIds implements TransformerInterface
{
    /**
     * @var CategoryIdProviderInterface
     */
    private readonly CategoryIdProviderInterface $categoryIdProvider;

    /**
     * @param CategoryIdProviderInterface $categoryIdProvider
     */
    public function __construct(CategoryIdProviderInterface $categoryIdProvider)
    {
        $this->categoryIdProvider = $categoryIdProvider;
    }

    /**
     * @param mixed $data
     * @param ArgumentIterator|null $arguments
     * @param \ArrayAccess<int|string, mixed>|null $context
     *
     * @return int[]|null
     * @throws InvalidInputDataException
     */
    public function transform(
        mixed $data,
        ?ArgumentIterator $arguments = null,
        // phpcs:ignore SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter
        ?\ArrayAccess $context = null,
    ): ?array {
        if (null === $data) {
            return null;
        }
        if (!($data instanceof ProductInterface)) {
            throw new InvalidInputDataException(
                transformerName: $this::class,
                expectedType: ProductInterface::class,
                arguments: $arguments,
                data: $data,
            );
        }

        return $this->categoryIdProvider->get($data);
    }
}
