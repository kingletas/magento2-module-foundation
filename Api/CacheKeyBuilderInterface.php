<?php
/**
 * @package   Commerce_Foundation
 * @copyright Copyright (c) the Commerce modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Commerce\Foundation\Api;

/**
 * Builds collision-free cache keys for a single logical cache namespace.
 */
interface CacheKeyBuilderInterface
{
    /**
     * Build a cache key from the given parts.
     */
    public function build(mixed ...$parts): string;

    /**
     * Cache tags this namespace writes under, for targeted invalidation.
     *
     * @return string[]
     */
    public function getTags(): array;

    public function getLifetime(): ?int;
}
