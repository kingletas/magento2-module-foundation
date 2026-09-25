<?php
/**
 * @package   Kingletas_Foundation
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\Foundation\Test\Unit\Support;

use Kingletas\Foundation\Test\Support\WiringAssertions;
use Magento\Framework\Component\ComponentRegistrar;
use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\TestCase;

/**
 * Where the layout check looks for a template another module owns.
 */
class LayoutTemplateOwnerTest extends TestCase
{
    use WiringAssertions;

    private string $root = '';

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/kingletas-template-owner-' . uniqid('', true);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->root)) {
            $this->removeDirectory($this->root);
        }
    }

    /**
     * A clone installs the module it borrows from under vendor/, not beside it, and Magento knows it from there.
     */
    public function testATemplateInARegisteredModuleOutsideTheTreeIsFound(): void
    {
        $owner = 'Kingletas_Owner' . bin2hex(random_bytes(4));
        $ownerDir = $this->root . '/clone/vendor/kingletas/owner';
        $this->write($ownerDir . '/view/adminhtml/templates/element/confirm.phtml', '');
        ComponentRegistrar::register(ComponentRegistrar::MODULE, $owner, $ownerDir);

        $this->assertEveryLayoutNamesSomethingThatExists($this->moduleNaming($owner . '::element/confirm.phtml'));
    }

    public function testATemplateNoModuleHasIsStillReported(): void
    {
        $owner = 'Kingletas_Owner' . bin2hex(random_bytes(4));
        $moduleDir = $this->moduleNaming($owner . '::element/confirm.phtml');

        try {
            $this->assertEveryLayoutNamesSomethingThatExists($moduleDir);
        } catch (ExpectationFailedException $failure) {
            $this->assertStringContainsString('names the template ' . $owner, $failure->getMessage());

            return;
        }

        $this->fail('A template that no module has was not reported');
    }

    private function moduleNaming(string $template): string
    {
        $moduleDir = $this->root . '/clone';
        $this->write($moduleDir . '/etc/module.xml', '<config><module name="Kingletas_Borrower"/></config>');
        $this->write(
            $moduleDir . '/view/adminhtml/layout/borrower_index.xml',
            '<page><body><block class="Magento\Framework\View\Element\Template" template="'
                . $template . '"/></body></page>'
        );

        return $moduleDir;
    }

    private function write(string $file, string $contents): void
    {
        if (!is_dir(dirname($file))) {
            mkdir(dirname($file), 0777, true);
        }

        file_put_contents($file, $contents);
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
