<?php
/**
 * @package   Kingletas_Foundation
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\Foundation\Model\Catalog;

use Kingletas\Foundation\Api\ProductImageUrlResolverInterface;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Helper\ImageFactory as ImageHelperFactory;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * Default resolver, backed by Magento's own image helper and media cache.
 */
class ProductImageUrlResolver implements ProductImageUrlResolverInterface
{
    /**
     * Per-request memo of SKU lookups.
     *
     * @var array<string, ProductInterface|null>
     */
    private array $productsBySku = [];

    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
        private readonly ImageHelperFactory $imageHelperFactory
    ) {
    }

    /**
     * @inheritDoc
     */
    public function resolveByProduct(
        ProductInterface $product,
        string $imageType = self::IMAGE_LARGE,
        array $attributes = []
    ): ?string {
        $helper = $this->imageHelperFactory->create()->init($product, $imageType, $attributes);
        $url = $helper->getUrl();

        return $this->rejectPlaceholder($url);
    }

    /**
     * @inheritDoc
     */
    public function resolveBySku(
        string $sku,
        string $imageType = self::IMAGE_LARGE,
        array $attributes = []
    ): ?string {
        $product = $this->loadBySku($sku);

        return $product === null ? null : $this->resolveByProduct($product, $imageType, $attributes);
    }

    /**
     * @inheritDoc
     */
    public function resolveByFile(
        string $file,
        string $imageType = self::IMAGE_LARGE,
        array $attributes = []
    ): ?string {
        if (trim($file) === '') {
            return null;
        }

        $helper = $this->imageHelperFactory->create();
        // init() needs a product to read the image-type config from; a bare
        // DataObject-free call is not supported, so the file is set afterwards.
        $url = $helper->setImageFile($file)->getUrl();

        return $this->rejectPlaceholder($url);
    }

    private function loadBySku(string $sku): ?ProductInterface
    {
        if (array_key_exists($sku, $this->productsBySku)) {
            return $this->productsBySku[$sku];
        }

        try {
            $product = $this->productRepository->get($sku);
        } catch (NoSuchEntityException) {
            $product = null;
        }

        return $this->productsBySku[$sku] = $product;
    }

    /**
     * Magento returns the placeholder URL rather than null for a product with
     * no image.
     */
    private function rejectPlaceholder(?string $url): ?string
    {
        if ($url === null || $url === '') {
            return null;
        }

        return str_contains($url, '/placeholder/') ? null : $url;
    }
}
