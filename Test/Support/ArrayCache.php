<?php
/**
 * @package   Kingletas_Foundation
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\Foundation\Test\Support;

use LogicException;
use Magento\Framework\App\CacheInterface;

/**
 * An application cache in an array, counting loads so a test can assert how often it was read.
 */
class ArrayCache implements CacheInterface
{
    /** @var array<string, string> */
    public array $entries = [];

    public int $loads = 0;

    public function getFrontend()
    {
        throw new LogicException('An array cache has no frontend.');
    }

    public function load($identifier)
    {
        $this->loads++;

        return $this->entries[$identifier] ?? false;
    }

    public function save($data, $identifier, $tags = [], $lifeTime = null)
    {
        $this->entries[$identifier] = (string) $data;

        return true;
    }

    public function remove($identifier)
    {
        unset($this->entries[$identifier]);

        return true;
    }

    public function clean($tags = [])
    {
        return true;
    }
}
