<?php
/**
 * @package   Kingletas_Foundation
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\Foundation\Api;

/**
 * Generates and verifies opaque, unguessable tokens.
 */
interface TokenGeneratorInterface
{
    /**
     * Create a cryptographically secure token as a lowercase hex string.
     *
     * @param int $bytes Entropy in bytes; implementations enforce a sane floor.
     *
     * @return string Hex string of 2 * $bytes characters.
     *
     * @throws \Random\RandomException When the platform CSPRNG is unavailable.
     */
    public function generate(int $bytes = 16): string;

    /**
     * Hash a token for at-rest storage.
     */
    public function hash(string $token): string;

    /**
     * Compare a candidate token against a stored digest in constant time.
     */
    public function matches(string $candidate, string $storedHash): bool;
}
