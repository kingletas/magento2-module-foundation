<?php
/**
 * @package   Kingletas_Foundation
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\Foundation\Test\Unit\Support;

use Kingletas\Foundation\Test\Support\WiringAssertions;
use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\TestCase;

/**
 * The generated page factory check against a module whose root is its PSR-4 root, as in a standalone checkout.
 */
class RegisteredModulePageFactoryTest extends TestCase
{
    use WiringAssertions;

    private string $moduleDir = '';

    /** @var callable|null */
    private $autoloader = null;

    protected function tearDown(): void
    {
        if ($this->autoloader !== null) {
            spl_autoload_unregister($this->autoloader);
            $this->autoloader = null;
        }

        if ($this->moduleDir !== '') {
            $this->removeDirectory($this->moduleDir);
            $this->moduleDir = '';
        }
    }

    public function testAnAlreadyRegisteredModuleIsCheckedWithoutRegisteringItAgain(): void
    {
        $moduleDir = $this->moduleWith('Magento\Framework\View\Result\PageFactory');

        $this->assertNothingTakesTheGeneratedPageFactory($moduleDir);
    }

    public function testTheGeneratedPageFactoryIsStillReportedInAnAlreadyRegisteredModule(): void
    {
        $moduleDir = $this->moduleWith('Magento\Backend\Model\View\Result\PageFactory');

        try {
            $this->assertNothingTakesTheGeneratedPageFactory($moduleDir);
        } catch (ExpectationFailedException $failure) {
            $this->assertStringContainsString(
                'Controller\Adminhtml\Index\Index takes Magento\Backend\Model\View\Result\PageFactory',
                $failure->getMessage()
            );

            return;
        }

        $this->fail('A controller taking the generated page factory was not reported');
    }

    /**
     * A module mapped from its own root, with a registration.php that fails if it runs again.
     */
    private function moduleWith(string $pageFactory): string
    {
        $namespace = 'Kingletas\RegistrationDemo' . str_replace('.', '', uniqid('', true));
        $this->moduleDir = sys_get_temp_dir() . '/kingletas-registered-' . uniqid('', true);
        mkdir($this->moduleDir . '/Controller/Adminhtml/Index', 0777, true);

        file_put_contents(
            $this->moduleDir . '/composer.json',
            (string) json_encode(['autoload' => ['psr-4' => [$namespace . '\\' => '']]])
        );
        file_put_contents(
            $this->moduleDir . '/registration.php',
            "<?php\nthrow new \\LogicException('registration.php ran a second time');\n"
        );
        file_put_contents(
            $this->moduleDir . '/Controller/Adminhtml/Index/Index.php',
            "<?php\nnamespace {$namespace}\\Controller\\Adminhtml\\Index;\n\n"
            . "class Index\n{\n    public function __construct(\\{$pageFactory} \$pageFactory)\n    {\n    }\n}\n"
        );

        $this->autoloader = $this->psr4Autoloader($namespace . '\\', $this->moduleDir);
        spl_autoload_register($this->autoloader);

        return $this->moduleDir;
    }

    /**
     * Includes a file for every name under the prefix, the way Composer's class loader does.
     */
    private function psr4Autoloader(string $prefix, string $directory): callable
    {
        return static function (string $class) use ($prefix, $directory): void {
            if (!str_starts_with($class, $prefix)) {
                return;
            }

            $file = $directory . '/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';

            if (is_file($file)) {
                include $file;
            }
        };
    }

    private function removeDirectory(string $directory): void
    {
        foreach (scandir($directory) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $directory . '/' . $entry;

            if (is_dir($path)) {
                $this->removeDirectory($path);
                continue;
            }

            unlink($path);
        }

        rmdir($directory);
    }
}
