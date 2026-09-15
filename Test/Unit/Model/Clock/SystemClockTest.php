<?php
/**
 * @package   Kingletas_Foundation
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\Foundation\Test\Unit\Model\Clock;

use Kingletas\Foundation\Model\Clock\SystemClock;
use PHPUnit\Framework\TestCase;

class SystemClockTest extends TestCase
{
    public function testTheTimeIsNowAndInUtc(): void
    {
        $before = time();
        $now = (new SystemClock())->now();

        $this->assertSame('UTC', $now->getTimezone()->getName());
        $this->assertGreaterThanOrEqual($before, $now->getTimestamp());
        $this->assertLessThanOrEqual(time(), $now->getTimestamp());
    }
}
