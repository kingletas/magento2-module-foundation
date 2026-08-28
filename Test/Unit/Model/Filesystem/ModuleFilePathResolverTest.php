<?php
/**
 * @copyright Copyright (c) the Commerce modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Commerce\Foundation\Test\Unit\Model\Filesystem;

use Commerce\Foundation\Model\Filesystem\ModuleFilePathResolver;
use Magento\Framework\Exception\NotFoundException;
use Magento\Framework\Module\Dir\Reader;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The one behaviour that matters here is what happens when the module is not
 * registered.
 */
final class ModuleFilePathResolverTest extends TestCase
{
    public function testARelativePathIsJoinedToTheModuleDirectory(): void
    {
        self::assertSame(
            '/app/vendor/acme/module-feed/etc/feed.csv',
            $this->resolver('/app/vendor/acme/module-feed')->resolve('etc/feed.csv')
        );
    }

    /**
     * An unregistered module must not resolve to "/", which would root every
     * path at the filesystem.
     */
    public function testAnUnregisteredModuleThrowsRatherThanResolvingToTheFilesystemRoot(): void
    {
        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('Acme_Missing');

        $this->resolver('')->resolve('etc/feed.csv');
    }

    #[DataProvider('separatorCases')]
    public function testSeparatorsAreNormalisedToExactlyOne(string $moduleDir, string $relative): void
    {
        self::assertSame(
            '/app/module/etc/feed.csv',
            $this->resolver($moduleDir)->resolve($relative)
        );
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function separatorCases(): array
    {
        return [
            'neither side has one' => ['/app/module', 'etc/feed.csv'],
            'module dir trails one' => ['/app/module/', 'etc/feed.csv'],
            'relative path leads with one' => ['/app/module', '/etc/feed.csv'],
            'both sides have one' => ['/app/module/', '/etc/feed.csv'],
            'relative path trails one too' => ['/app/module/', '/etc/feed.csv/'],
        ];
    }

    public function testAnEmptyRelativePathResolvesToTheModuleDirectoryItself(): void
    {
        self::assertSame('/app/module', $this->resolver('/app/module')->resolve(''));
        self::assertSame('/app/module', $this->resolver('/app/module/')->resolve('/'));
    }

    private function resolver(string $moduleDir): ModuleFilePathResolver
    {
        $reader = $this->createMock(Reader::class);
        $reader->method('getModuleDir')->willReturn($moduleDir);

        return new ModuleFilePathResolver($reader, 'Acme_Missing');
    }
}
