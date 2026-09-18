<?php
/**
 * @package   Kingletas_Foundation
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\Foundation\Test\Unit\Support;

use Kingletas\Foundation\Test\Support\WiringAssertions;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\TestCase;

/**
 * Which directories the analysis check treats as the module's own code.
 */
class CodeDirectoriesAnalysedTest extends TestCase
{
    use WiringAssertions;

    private string $moduleDir = '';

    protected function tearDown(): void
    {
        if ($this->moduleDir !== '') {
            $this->removeDirectory($this->moduleDir);
            $this->moduleDir = '';
        }
    }

    public function testADirectoryOfTheModulesOwnCodeThatNothingAnalysesIsReported(): void
    {
        $moduleDir = $this->moduleWithDirectories(['Model', 'Console'], analysed: ['Model']);

        try {
            $this->assertEveryCodeDirectoryIsAnalysed($moduleDir);
        } catch (ExpectationFailedException $failure) {
            $this->assertStringContainsString('leaves out Console', $failure->getMessage());

            return;
        }

        $this->fail('A directory of the module\'s own code that no analyser is pointed at was not reported');
    }

    public function testEveryCodeDirectoryBeingAnalysedStaysSilent(): void
    {
        $moduleDir = $this->moduleWithDirectories(['Model', 'Console'], analysed: ['Model', 'Console']);

        $this->assertEveryCodeDirectoryIsAnalysed($moduleDir);
    }

    /**
     * Somebody else's code, a developer's scratch space and the directories a
     * module keeps for tests, fixtures and packaging are not what an analyser
     * should be pointed at. A checkout that has run composer install has a
     * vendor directory full of PHP, and demanding it be analysed fails every
     * module that has its dependencies installed.
     */
    #[DataProvider('directoriesThatAreNotTheModulesOwnCode')]
    public function testADirectoryThatIsNotTheModulesOwnCodeIsNotDemanded(string $directory): void
    {
        $moduleDir = $this->moduleWithDirectories(['Model', $directory], analysed: ['Model']);

        $this->assertEveryCodeDirectoryIsAnalysed($moduleDir);
    }

    /**
     * @return array<string, string[]>
     */
    public static function directoriesThatAreNotTheModulesOwnCode(): array
    {
        return [
            'composer dependencies' => ['vendor'],
            'disposable output' => ['local.d'],
            'the test suites' => ['Test'],
            'packaging scripts' => ['packaging'],
            'a javascript build' => ['node_modules'],
            'runtime scratch' => ['var'],
            'view files' => ['view'],
        ];
    }

    /**
     * A module directory holding a PHP file in each named directory, and
     * analysis settings pointed at the ones given.
     *
     * @param string[] $directories
     * @param string[] $analysed
     */
    private function moduleWithDirectories(array $directories, array $analysed): string
    {
        $this->moduleDir = sys_get_temp_dir() . '/kingletas-code-dirs-' . uniqid('', true);
        mkdir($this->moduleDir, 0777, true);

        foreach ($directories as $directory) {
            mkdir($this->moduleDir . '/' . $directory, 0777, true);
            file_put_contents($this->moduleDir . '/' . $directory . '/Thing.php', "<?php\n");
        }

        $list = implode(',', $analysed);
        file_put_contents(
            $this->moduleDir . '/composer.json',
            json_encode(['scripts' => ['md' => "phpmd {$list} text phpmd.xml.dist"]], JSON_PRETTY_PRINT)
        );

        $paths = implode("\n", array_map(static fn (string $d): string => "        - {$d}", $analysed));
        file_put_contents(
            $this->moduleDir . '/phpstan.neon.dist',
            "parameters:\n    paths:\n{$paths}\n"
        );

        return $this->moduleDir;
    }

    private function removeDirectory(string $directory): void
    {
        foreach (scandir($directory) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $directory . '/' . $entry;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }

        rmdir($directory);
    }
}
