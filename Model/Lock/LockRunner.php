<?php
/**
 * @package   Kingletas_Foundation
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\Foundation\Model\Lock;

use Magento\Framework\Lock\LockManagerInterface;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Runs work while holding a named lock shared by every server, and always releases it.
 */
class LockRunner
{
    public function __construct(
        private readonly LockManagerInterface $lockManager,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Returns false without running the work when the lock is not free within the timeout.
     *
     * @param callable(): void $work
     * @param int $timeout Seconds to wait; 0 tries once.
     */
    public function run(string $name, callable $work, int $timeout = 0): bool
    {
        if (!$this->lockManager->lock($name, max(0, $timeout))) {
            return false;
        }

        try {
            $work();
        } finally {
            $this->release($name);
        }

        return true;
    }

    public function isLocked(string $name): bool
    {
        return $this->lockManager->isLocked($name);
    }

    private function release(string $name): void
    {
        try {
            $this->lockManager->unlock($name);
        } catch (Throwable $e) {
            $this->logger->warning(sprintf('Releasing lock "%s" failed.', $name), ['exception' => $e]);
        }
    }
}
