<?php
/**
 * @package   Commerce_Foundation
 * @copyright Copyright (c) the Commerce modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Commerce\Foundation\Test\Support;

use PHPUnit\Framework\TestCase;

/**
 * Every wiring check a module gets by naming its own directory.
 */
abstract class ModuleWiringTestCase extends TestCase
{
    use WiringAssertions;

    /**
     * The module root - the directory holding `composer.json` and `etc/`.
     */
    abstract protected function moduleDir(): string;

    /**
     * Config paths that appear in `system.xml` with no `config.xml` default on
     * purpose.
     *
     * @return string[]
     */
    protected function settingsWithNoDefault(): array
    {
        return [];
    }

    public function testEveryConfigFileParses(): void
    {
        $this->assertEveryConfigFileParses($this->moduleDir());
    }

    public function testEveryObserverNamesSomethingThatCanBeBuilt(): void
    {
        $this->assertEveryObserverExists($this->moduleDir());
    }

    public function testEveryCronJobNamesAMethodThatExists(): void
    {
        $this->assertEveryCronJobIsCallable($this->moduleDir());
    }

    public function testConsumersTopicsAndQueuesAgree(): void
    {
        $this->assertMessageQueueWiringAgrees($this->moduleDir());
    }

    public function testTheConfigAclResourceHangsFromTheCoreChain(): void
    {
        $this->assertAclConfigResourceUsesTheCoreChain($this->moduleDir());
    }

    public function testEveryAdminControllerRequiresLogin(): void
    {
        $this->assertEveryAdminControllerRequiresLogin($this->moduleDir());
    }

    public function testGridDataSourcesAreDeclaredGlobally(): void
    {
        $this->assertGridDataSourcesAreDeclaredGlobally($this->moduleDir());
    }

    public function testEveryConsoleCommandTakesItsNameFromDi(): void
    {
        $this->assertEveryCommandIsNamedInDi($this->moduleDir());
    }

    public function testEveryWebApiRouteIsServiceable(): void
    {
        $this->assertEveryWebApiRouteIsServiceable($this->moduleDir());
    }

    public function testEverySettingHasADefault(): void
    {
        $this->assertEverySettingHasADefault($this->moduleDir(), $this->settingsWithNoDefault());
    }
}
