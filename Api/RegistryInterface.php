<?php
/**
 * RegistryInterface.php
 *
 * @package     Commerce_Foundation
 * @copyright   Copyright (c) the Commerce modules authors
 * @license     OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Commerce\Foundation\Api;

use Magento\Framework\Exception\AlreadyExistsException;

/**
 * A request-scoped key/value store for passing state between layers that have
 * no direct reference to each other (observer -> plugin -> block).
 */
interface RegistryInterface
{
    public function get(string $key, mixed $default = null): mixed;

    /**
     * Store $value under $key.
     *
     * @param bool $graceful When true an existing key is left untouched instead of raising.
     *
     * @throws AlreadyExistsException When $key is taken and $graceful is false.
     */
    public function set(string $key, mixed $value, bool $graceful = false): void;

    public function replace(string $key, mixed $value): void;

    public function has(string $key): bool;

    public function remove(string $key): void;

    public function flush(): void;
}
