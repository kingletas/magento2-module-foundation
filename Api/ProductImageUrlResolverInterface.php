<?php
/**
 * ProductImageUrlResolverInterface.php
 *
 * @package     Commerce_Foundation
 * @copyright   Copyright (c) the Commerce modules authors
 * @license     OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Commerce\Foundation\Api;

use Magento\Catalog\Api\Data\ProductInterface;

/**
 * Resolves a displayable image URL for a product.
 */
interface ProductImageUrlResolverInterface
{
    public const string IMAGE_LARGE = 'product_page_image_large';
    public const string IMAGE_SMALL = 'product_page_image_small';
    public const string IMAGE_THUMBNAIL = 'product_thumbnail_image';

    /**
     * Resolve by product object. Cheapest variant when you already have one.
     *
     * @param array<string, mixed> $attributes Implementation-specific hints
     *                                         (width, height, crop, format).
     *
     * @return string|null Absolute URL, or null when the product has no image.
     */
    public function resolveByProduct(
        ProductInterface $product,
        string $imageType = self::IMAGE_LARGE,
        array $attributes = []
    ): ?string;

    /**
     * Resolve by SKU. Implementations are expected to cache the lookup.
     *
     * @param array<string, mixed> $attributes
     */
    public function resolveBySku(
        string $sku,
        string $imageType = self::IMAGE_LARGE,
        array $attributes = []
    ): ?string;

    /**
     * Resolve directly from a media-gallery file path.
     *
     * @param array<string, mixed> $attributes
     */
    public function resolveByFile(
        string $file,
        string $imageType = self::IMAGE_LARGE,
        array $attributes = []
    ): ?string;
}
