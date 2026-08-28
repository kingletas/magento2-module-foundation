<?php
/**
 * ProductImageUrlResolverTest.php
 *
 * @package     Commerce_Foundation
 * @copyright   Copyright (c) the Commerce modules authors
 * @license     OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Commerce\Foundation\Test\Unit\Model\Catalog;

use Commerce\Foundation\Api\ProductImageUrlResolverInterface;
use Commerce\Foundation\Model\Catalog\ProductImageUrlResolver;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Helper\Image as ImageHelper;
use Magento\Catalog\Helper\ImageFactory as ImageHelperFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ProductImageUrlResolverTest extends TestCase
{
    private ProductRepositoryInterface&MockObject $productRepository;
    private ImageHelper&MockObject $imageHelper;
    private ImageHelperFactory&MockObject $imageHelperFactory;

    protected function setUp(): void
    {
        $this->productRepository = $this->createMock(ProductRepositoryInterface::class);

        $this->imageHelper = $this->createMock(ImageHelper::class);
        $this->imageHelper->method('init')->willReturnSelf();
        $this->imageHelper->method('setImageFile')->willReturnSelf();
        $this->imageHelper->method('getUrl')->willReturn('https://cdn.test/media/large.jpg');

        $this->imageHelperFactory = $this->createMock(ImageHelperFactory::class);
        $this->imageHelperFactory->method('create')->willReturn($this->imageHelper);
    }

    public function testAProductResolvesToItsHelperUrl(): void
    {
        $this->assertSame(
            'https://cdn.test/media/large.jpg',
            $this->resolver()->resolveByProduct($this->product())
        );
    }

    public function testTheImageTypeAndAttributesReachTheHelper(): void
    {
        $product = $this->product();
        $this->imageHelper = $this->createMock(ImageHelper::class);
        $this->imageHelper->expects($this->once())
            ->method('init')
            ->with($product, ProductImageUrlResolverInterface::IMAGE_THUMBNAIL, ['width' => 90])
            ->willReturnSelf();
        $this->imageHelper->method('getUrl')->willReturn('https://cdn.test/media/thumb.jpg');
        $this->imageHelperFactory = $this->createMock(ImageHelperFactory::class);
        $this->imageHelperFactory->method('create')->willReturn($this->imageHelper);

        $this->assertSame(
            'https://cdn.test/media/thumb.jpg',
            $this->resolver()->resolveByProduct(
                $product,
                ProductImageUrlResolverInterface::IMAGE_THUMBNAIL,
                ['width' => 90]
            )
        );
    }

    public function testTheDefaultImageTypeIsTheLargeOne(): void
    {
        $this->imageHelper = $this->createMock(ImageHelper::class);
        $this->imageHelper->expects($this->once())
            ->method('init')
            ->with($this->anything(), ProductImageUrlResolverInterface::IMAGE_LARGE, [])
            ->willReturnSelf();
        $this->imageHelper->method('getUrl')->willReturn('https://cdn.test/media/large.jpg');
        $this->imageHelperFactory = $this->createMock(ImageHelperFactory::class);
        $this->imageHelperFactory->method('create')->willReturn($this->imageHelper);

        $this->resolver()->resolveByProduct($this->product());
    }

    public function testASkuIsLoadedThroughTheRepository(): void
    {
        $this->productRepository->expects($this->once())
            ->method('get')
            ->with('SKU-1')
            ->willReturn($this->product());

        $this->assertSame('https://cdn.test/media/large.jpg', $this->resolver()->resolveBySku('SKU-1'));
    }

    /**
     * A category page asks for the same SKU three or four times over - grid
     * tile, quick view, swatch.
     */
    public function testARepeatedSkuIsLoadedOnlyOnce(): void
    {
        $this->productRepository->expects($this->once())->method('get')->willReturn($this->product());

        $resolver = $this->resolver();
        $resolver->resolveBySku('SKU-1');
        $resolver->resolveBySku('SKU-1');
        $resolver->resolveBySku('SKU-1');
    }

    /**
     * The negative answer is memoised too, so a deleted SKU costs one failed
     * lookup per request.
     */
    public function testARepeatedlyMissingSkuIsLookedUpOnlyOnce(): void
    {
        $this->productRepository->expects($this->once())
            ->method('get')
            ->willThrowException(new NoSuchEntityException(__('No such entity.')));

        $resolver = $this->resolver();

        $this->assertNull($resolver->resolveBySku('GONE'));
        $this->assertNull($resolver->resolveBySku('GONE'));
    }

    /**
     * A SKU in a feed but not in the catalogue is ordinary, not exceptional.
     */
    public function testAnUnknownSkuIsNullRatherThanAnException(): void
    {
        $this->productRepository->method('get')
            ->willThrowException(new NoSuchEntityException(__('No such entity.')));

        $this->assertNull($this->resolver()->resolveBySku('GONE'));
    }

    public function testDifferentSkusAreCachedSeparately(): void
    {
        $this->productRepository->expects($this->exactly(2))->method('get')->willReturn($this->product());

        $resolver = $this->resolver();
        $resolver->resolveBySku('SKU-1');
        $resolver->resolveBySku('SKU-2');
    }

    public function testAFileResolvesThroughTheHelpersImageFile(): void
    {
        $this->imageHelper = $this->createMock(ImageHelper::class);
        $this->imageHelper->expects($this->once())
            ->method('setImageFile')
            ->with('/m/y/my-image.jpg')
            ->willReturnSelf();
        $this->imageHelper->method('getUrl')->willReturn('https://cdn.test/media/my-image.jpg');
        $this->imageHelperFactory = $this->createMock(ImageHelperFactory::class);
        $this->imageHelperFactory->method('create')->willReturn($this->imageHelper);

        $this->assertSame(
            'https://cdn.test/media/my-image.jpg',
            $this->resolver()->resolveByFile('/m/y/my-image.jpg')
        );
    }

    /**
     * An empty file path is a caller's missing value, not a request for
     * whatever the helper does with "".
     */
    public function testABlankFileIsRejectedWithoutTouchingTheHelper(): void
    {
        $this->imageHelperFactory = $this->createMock(ImageHelperFactory::class);
        $this->imageHelperFactory->expects($this->never())->method('create');

        $this->assertNull($this->resolver()->resolveByFile(''));
        $this->assertNull($this->resolver()->resolveByFile('   '));
    }

    /**
     * Magento answers "no image" with the placeholder URL.
     */
    public function testAPlaceholderUrlIsReportedAsNoImage(): void
    {
        $this->imageHelper = $this->createMock(ImageHelper::class);
        $this->imageHelper->method('init')->willReturnSelf();
        $this->imageHelper->method('setImageFile')->willReturnSelf();
        $this->imageHelper->method('getUrl')
            ->willReturn('https://cdn.test/media/catalog/product/placeholder/default/image.jpg');
        $this->imageHelperFactory = $this->createMock(ImageHelperFactory::class);
        $this->imageHelperFactory->method('create')->willReturn($this->imageHelper);

        $resolver = $this->resolver();

        $this->assertNull($resolver->resolveByProduct($this->product()));
        $this->assertNull($resolver->resolveByFile('/m/y/my-image.jpg'));
    }

    public function testAnEmptyHelperUrlIsNull(): void
    {
        $this->imageHelper = $this->createMock(ImageHelper::class);
        $this->imageHelper->method('init')->willReturnSelf();
        $this->imageHelper->method('getUrl')->willReturn('');
        $this->imageHelperFactory = $this->createMock(ImageHelperFactory::class);
        $this->imageHelperFactory->method('create')->willReturn($this->imageHelper);

        $this->assertNull($this->resolver()->resolveByProduct($this->product()));
    }

    /**
     * A real product image whose own path happens to contain the word is not a
     * placeholder; only Magento's `/placeholder/` directory is.
     */
    public function testAnImageWhoseNameMerelyMentionsPlaceholderIsKept(): void
    {
        $this->imageHelper = $this->createMock(ImageHelper::class);
        $this->imageHelper->method('init')->willReturnSelf();
        $this->imageHelper->method('getUrl')
            ->willReturn('https://cdn.test/media/catalog/product/p/l/placeholder-tee.jpg');
        $this->imageHelperFactory = $this->createMock(ImageHelperFactory::class);
        $this->imageHelperFactory->method('create')->willReturn($this->imageHelper);

        $this->assertSame(
            'https://cdn.test/media/catalog/product/p/l/placeholder-tee.jpg',
            $this->resolver()->resolveByProduct($this->product())
        );
    }

    private function resolver(): ProductImageUrlResolver
    {
        return new ProductImageUrlResolver($this->productRepository, $this->imageHelperFactory);
    }

    private function product(): ProductInterface&MockObject
    {
        return $this->createMock(ProductInterface::class);
    }
}
