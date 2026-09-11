<?php
/**
 * @package   Kingletas_Foundation
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\Foundation\Model\Security;

use Kingletas\Foundation\Api\TokenGeneratorInterface;
use InvalidArgumentException;

/**
 * Default token generator, backed by random_bytes() and a SHA-2 digest.
 */
class TokenGenerator implements TokenGeneratorInterface
{
    /**
     * Below this an exhaustive search stops being unreasonable, so it is a
     * floor rather than a default: callers asking for less silently get more.
     */
    public const int MIN_BYTES = 16;

    public const string DEFAULT_ALGORITHM = 'sha256';

    /**
     * @param string $algorithm Any algorithm reported by hash_algos().
     *
     * @throws InvalidArgumentException When the algorithm is not available.
     */
    public function __construct(
        private readonly string $algorithm = self::DEFAULT_ALGORITHM
    ) {
        if (!in_array($this->algorithm, hash_algos(), true)) {
            throw new InvalidArgumentException(
                sprintf('Hashing algorithm "%s" is not available on this platform.', $this->algorithm)
            );
        }
    }

    /**
     * @inheritDoc
     */
    public function generate(int $bytes = self::MIN_BYTES): string
    {
        return bin2hex(random_bytes(max($bytes, self::MIN_BYTES)));
    }

    /**
     * @inheritDoc
     */
    public function hash(string $token): string
    {
        return hash($this->algorithm, $token);
    }

    /**
     * @inheritDoc
     */
    public function matches(string $candidate, string $storedHash): bool
    {
        return hash_equals($storedHash, $this->hash($candidate));
    }
}
