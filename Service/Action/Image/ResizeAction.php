<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingProducts\Service\Action\Image;

use Klevu\IndexingApi\Service\Action\Image\ResizeActionInterface;
use Magento\Framework\Image;
use Psr\Log\LoggerInterface;

class ResizeAction implements ResizeActionInterface
{
    /**
     * @var LoggerInterface
     */
    private readonly LoggerInterface $logger;

    /**
     * @param LoggerInterface $logger
     */
    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * @param Image $image
     * @param array<mixed|null> $imageParams
     *
     * @return void
     */
    public function execute(Image $image, array $imageParams): void
    {
        if (!$this->validateParams($imageParams)) {
            return;
        }
        $image->resize(
            width: (int)$imageParams['image_width'],
            height: $imageParams['image_height'] ?? null,
        );
    }

    /**
     * @param mixed[] $imageParams
     *
     * @return bool
     */
    private function validateParams(array $imageParams): bool
    {
        $return = true;
        if (!$this->isWidthParameterSet($imageParams['image_width'] ?? null)) {
            // if width is not set do not resize, but don't log an error
            $return = false;
        } elseif (!$this->isWidthParameterValid($imageParams['image_width'])) {
            $this->logger->error(
                message: 'Method: {method}, Error: {message}',
                context: [
                    'method' => __METHOD__,
                    'message' => sprintf(
                        'image_width must be a positive integer, received %s',
                        get_debug_type($imageParams['image_width']),
                    ),
                ],
            );
            $return = false;
        }

        return $return;
    }

    /**
     * @param mixed|null $imageWidth
     *
     * @return bool
     */
    private function isWidthParameterSet(mixed $imageWidth): bool
    {
        return ($imageWidth ?? null)
            && $imageWidth !== null;
    }

    /**
     * @param mixed $width
     *
     * @return bool
     */
    private function isWidthParameterValid(mixed $width): bool
    {
        $return = true;
        if (!is_int($width) || $width <= 0) {
            $return = false;
        }

        return $return;
    }
}
