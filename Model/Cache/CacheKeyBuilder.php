<?php
/**
 * CacheKeyBuilder.php
 *
 * @package     Commerce_Foundation
 * @copyright   Copyright (c) the Commerce modules authors
 * @license     OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Commerce\Foundation\Model\Cache;

use Commerce\Foundation\Api\CacheKeyBuilderInterface;
use Magento\Framework\Serialize\SerializerInterface;

/**
 * Default cache key builder.
 */
class CacheKeyBuilder implements CacheKeyBuilderInterface
{
    /**
     * @param string   $prefix    Namespace prefix, unique per virtualType.
     * @param string[] $tags      Cache tags written alongside entries in this namespace.
     * @param int|null $lifetime  Entry TTL in seconds; null defers to the backend.
     * @param string   $delimiter Separator between key parts.
     */
    public function __construct(
        private readonly SerializerInterface $serializer,
        private readonly string $prefix,
        private readonly array $tags = [],
        private readonly ?int $lifetime = null,
        private readonly string $delimiter = '_'
    ) {
    }

    /**
     * @inheritDoc
     */
    public function build(mixed ...$parts): string
    {
        $normalised = array_map($this->normalise(...), $parts);
        array_unshift($normalised, $this->prefix);

        return implode($this->delimiter, array_filter($normalised, static fn (string $part): bool => $part !== ''));
    }

    /**
     * @inheritDoc
     */
    public function getTags(): array
    {
        return $this->tags;
    }

    /**
     * @inheritDoc
     */
    public function getLifetime(): ?int
    {
        return $this->lifetime;
    }

    private function normalise(mixed $part): string
    {
        $value = match (true) {
            $part === null => '',
            is_bool($part) => $part ? '1' : '0',
            is_scalar($part) => (string) $part,
            // Arrays and objects are hashed: unbounded input must not produce
            // an unbounded key, and most cache backends cap key length.
            default => hash('xxh128', $this->serializer->serialize($part)),
        };

        // Whitespace and delimiters in a key part would let two distinct inputs
        // render to the same joined key.
        return preg_replace('/[^A-Za-z0-9]+/', '-', $value) ?? '';
    }
}
