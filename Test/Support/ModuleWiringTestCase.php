<?php
/**
 * ModuleWiringTestCase.php
 *
 * @package     Commerce_Foundation
 * @copyright   Copyright (c) the Commerce modules authors
 * @license     OSL-3.0 https://opensource.org/licenses/OSL-3.0
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
    abstract protected static function moduleDir(): string;

    /**
     * Config paths that appear in `system.xml` with no `config.xml` default on
     * purpose.
     *
     * @return string[]
     */
    protected static function settingsWithNoDefault(): array
    {
        return [];
    }

    public function testEveryConfigFileParses(): void
    {
        self::assertEveryConfigFileParses(static::moduleDir());
    }

    public function testEveryObserverNamesSomethingThatCanBeBuilt(): void
    {
        self::assertEveryObserverExists(static::moduleDir());
    }

    public function testEveryCronJobNamesAMethodThatExists(): void
    {
        self::assertEveryCronJobIsCallable(static::moduleDir());
    }

    public function testConsumersTopicsAndQueuesAgree(): void
    {
        self::assertMessageQueueWiringAgrees(static::moduleDir());
    }

    public function testTheConfigAclResourceHangsFromTheCoreChain(): void
    {
        self::assertAclConfigResourceUsesTheCoreChain(static::moduleDir());
    }

    public function testEveryAdminControllerRequiresLogin(): void
    {
        self::assertEveryAdminControllerRequiresLogin(static::moduleDir());
    }

    public function testGridDataSourcesAreDeclaredGlobally(): void
    {
        self::assertGridDataSourcesAreDeclaredGlobally(static::moduleDir());
    }

    public function testEveryConsoleCommandTakesItsNameFromDi(): void
    {
        self::assertEveryCommandIsNamedInDi(static::moduleDir());
    }

    public function testEveryWebApiRouteIsServiceable(): void
    {
        self::assertEveryWebApiRouteIsServiceable(static::moduleDir());
    }

    public function testEverySettingHasADefault(): void
    {
        self::assertEverySettingHasADefault(static::moduleDir(), static::settingsWithNoDefault());
    }
}
