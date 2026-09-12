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
 * The `config.xml` default check against a `system.xml` that nests its groups.
 */
class NestedGroupDefaultsTest extends TestCase
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

    public function testAFieldInANestedGroupWithNoDefaultIsReported(): void
    {
        $moduleDir = $this->moduleWith($this->nestedSystemXml(), null);

        try {
            $this->assertEverySettingHasADefault($moduleDir);
        } catch (ExpectationFailedException $failure) {
            $this->assertStringContainsString(
                'kingletas_demo/alerts/quiet_hours/start has no default',
                $failure->getMessage()
            );

            return;
        }

        $this->fail('A field two groups down with no default in config.xml was not reported');
    }

    public function testAFieldInANestedGroupWithADefaultStaysSilent(): void
    {
        $moduleDir = $this->moduleWith($this->nestedSystemXml(), $this->nestedConfigXml());

        $this->assertEverySettingHasADefault($moduleDir);
    }

    public function testAFieldInANestedGroupIsPathedThroughTheWholeChainOfGroupIds(): void
    {
        $moduleDir = $this->moduleWith($this->nestedSystemXml(), $this->nestedConfigXml());

        $this->assertSame(
            [
                'kingletas_demo/alerts/enabled' => false,
                'kingletas_demo/alerts/quiet_hours/start' => false,
                'kingletas_demo/alerts/quiet_hours/channels/webhook_token' => true,
            ],
            $this->systemXmlPaths($moduleDir)
        );
    }

    /**
     * A section, a group, a group inside it and a third inside that, with one
     * field at each level.
     */
    private function nestedSystemXml(): string
    {
        return <<<'XML'
        <?xml version="1.0"?>
        <config>
            <system>
                <section id="kingletas_demo">
                    <group id="alerts">
                        <field id="enabled" type="select"/>
                        <group id="quiet_hours">
                            <field id="start" type="text"/>
                            <group id="channels">
                                <field id="webhook_token" type="obscure"/>
                            </group>
                        </group>
                    </group>
                </section>
            </system>
        </config>
        XML;
    }

    /**
     * A default for every field the nested `system.xml` declares that is not a
     * credential.
     */
    private function nestedConfigXml(): string
    {
        return <<<'XML'
        <?xml version="1.0"?>
        <config>
            <default>
                <kingletas_demo>
                    <alerts>
                        <enabled>0</enabled>
                        <quiet_hours>
                            <start>22:00</start>
                        </quiet_hours>
                    </alerts>
                </kingletas_demo>
            </default>
        </config>
        XML;
    }

    private function moduleWith(string $systemXml, ?string $configXml): string
    {
        $this->moduleDir = sys_get_temp_dir() . '/kingletas-nested-' . uniqid('', true);
        mkdir($this->moduleDir . '/etc/adminhtml', 0777, true);
        file_put_contents($this->moduleDir . '/etc/adminhtml/system.xml', $systemXml);

        if ($configXml !== null) {
            file_put_contents($this->moduleDir . '/etc/config.xml', $configXml);
        }

        return $this->moduleDir;
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
