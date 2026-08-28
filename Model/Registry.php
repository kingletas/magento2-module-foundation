<?php
/**
 * Registry.php
 *
 * @package     Commerce_Foundation
 * @copyright   Copyright (c) the Commerce modules authors
 * @license     OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Commerce\Foundation\Model;

use Commerce\Foundation\Api\RegistryInterface;
use Magento\Framework\Exception\AlreadyExistsException;

/**
 * In-memory registry.
 */
class Registry implements RegistryInterface
{
    /** @var array<string, mixed> */
    private array $values = [];

    /**
     * @inheritDoc
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return array_key_exists($key, $this->values) ? $this->values[$key] : $default;
    }

    /**
     * @inheritDoc
     */
    public function set(string $key, mixed $value, bool $graceful = false): void
    {
        if (array_key_exists($key, $this->values)) {
            if ($graceful) {
                return;
            }

            throw new AlreadyExistsException(__('Registry key "%1" is already in use.', $key));
        }

        $this->values[$key] = $value;
    }

    /**
     * @inheritDoc
     */
    public function replace(string $key, mixed $value): void
    {
        $this->values[$key] = $value;
    }

    /**
     * @inheritDoc
     */
    public function has(string $key): bool
    {
        return array_key_exists($key, $this->values);
    }

    /**
     * @inheritDoc
     */
    public function remove(string $key): void
    {
        unset($this->values[$key]);
    }

    /**
     * @inheritDoc
     */
    public function flush(): void
    {
        $this->values = [];
    }
}
