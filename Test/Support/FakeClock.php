<?php
/**
 * @package   Kingletas_Foundation
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\Foundation\Test\Support;

use DateTimeImmutable;
use DateTimeZone;
use Kingletas\Foundation\Api\ClockInterface;

/**
 * A clock a test moves by hand.
 */
class FakeClock implements ClockInterface
{
    private DateTimeImmutable $now;

    public function __construct(string $now = '2026-09-15 12:00:00')
    {
        $this->now = new DateTimeImmutable($now, new DateTimeZone('UTC'));
    }

    public function now(): DateTimeImmutable
    {
        return $this->now;
    }

    public function advance(string $interval): void
    {
        $this->now = $this->now->modify($interval);
    }
}
