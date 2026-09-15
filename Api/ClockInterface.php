<?php
/**
 * @package   Kingletas_Foundation
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\Foundation\Api;

use DateTimeImmutable;

/**
 * The current time, in UTC.
 */
interface ClockInterface
{
    public function now(): DateTimeImmutable;
}
