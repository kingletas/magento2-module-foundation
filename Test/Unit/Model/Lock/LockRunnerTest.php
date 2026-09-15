<?php
/**
 * @package   Kingletas_Foundation
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\Foundation\Test\Unit\Model\Lock;

use Kingletas\Foundation\Model\Lock\LockRunner;
use Magento\Framework\Lock\LockManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

class LockRunnerTest extends TestCase
{
    private LockManagerInterface&MockObject $locks;

    private LoggerInterface&MockObject $logger;

    private LockRunner $runner;

    protected function setUp(): void
    {
        $this->locks = $this->createMock(LockManagerInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->runner = new LockRunner($this->locks, $this->logger);
    }

    public function testWorkRunsUnderTheLockAndTheLockIsReleased(): void
    {
        $this->locks->expects($this->once())->method('lock')->with('job', 5)->willReturn(true);
        $this->locks->expects($this->once())->method('unlock')->with('job')->willReturn(true);
        $ran = false;

        $this->assertTrue($this->runner->run('job', function () use (&$ran): void {
            $ran = true;
        }, 5));
        $this->assertTrue($ran);
    }

    public function testWorkDoesNotRunWhenTheLockIsHeld(): void
    {
        $this->locks->method('lock')->willReturn(false);
        $this->locks->expects($this->never())->method('unlock');
        $ran = false;

        $this->assertFalse($this->runner->run('job', function () use (&$ran): void {
            $ran = true;
        }));
        $this->assertFalse($ran);
    }

    public function testTheLockIsReleasedWhenTheWorkThrows(): void
    {
        $this->locks->method('lock')->willReturn(true);
        $this->locks->expects($this->once())->method('unlock')->with('job');
        $this->expectException(RuntimeException::class);

        $this->runner->run('job', static function (): void {
            throw new RuntimeException('The work failed.');
        });
    }

    public function testAFailedReleaseIsLoggedRatherThanHidingTheResult(): void
    {
        $this->locks->method('lock')->willReturn(true);
        $this->locks->method('unlock')->willThrowException(new RuntimeException('The lock backend went away.'));
        $this->logger->expects($this->once())->method('warning');

        $this->assertTrue($this->runner->run('job', static function (): void {
        }));
    }

    public function testANegativeTimeoutTriesOnce(): void
    {
        $this->locks->expects($this->once())->method('lock')->with('job', 0)->willReturn(false);

        $this->runner->run('job', static function (): void {
        }, -3);
    }

    public function testIsLockedAsksTheLockManager(): void
    {
        $this->locks->method('isLocked')->with('job')->willReturn(true);

        $this->assertTrue($this->runner->isLocked('job'));
    }
}
