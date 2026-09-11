<?php
/**
 * @package   Kingletas_Foundation
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\Foundation\Api;

/**
 * Maps a simple product's SKU to the SKU of the configurable that owns it.
 */
interface ConfigurableParentSkuResolverInterface
{
    /**
     * @return string|null Parent SKU, or null when the SKU is unknown or is
     *                     itself standalone.
     */
    public function resolve(string $childSku): ?string;

    /**
     * Resolve many SKUs at once.
     *
     * @param string[] $childSkus
     * @return array<string, string> Child SKU => parent SKU, omitting misses.
     */
    public function resolveMany(array $childSkus): array;
}
