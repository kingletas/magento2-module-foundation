<?php
/**
 * @copyright Copyright (c) the Commerce modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Commerce\Foundation\Test\Unit\Fake;

use Magento\Framework\App\CacheInterface;

/**
 * A cache in an array, which counts what it was asked.
 */
final class ArrayCache implements CacheInterface
{
    /** @var array<string, string> */
    public array $entries = [];

    /** @var array<int, string> */
    public array $loads = [];

    /** @var array<int, array{identifier: string, data: string}> */
    public array $saves = [];

    public function getFrontend()
    {
        return null;
    }

    public function load($identifier)
    {
        $this->loads[] = (string) $identifier;

        return $this->entries[$identifier] ?? false;
    }

    public function save($data, $identifier, $tags = [], $lifeTime = null)
    {
        $this->entries[$identifier] = (string) $data;
        $this->saves[] = ['identifier' => (string) $identifier, 'data' => (string) $data];

        return true;
    }

    public function remove($identifier)
    {
        unset($this->entries[$identifier]);

        return true;
    }

    public function clean($tags = [])
    {
        $this->entries = [];

        return true;
    }
}
