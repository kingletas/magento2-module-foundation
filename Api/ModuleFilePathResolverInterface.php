<?php
/**
 * @package   Kingletas_Foundation
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\Foundation\Api;

use Magento\Framework\Exception\NotFoundException;

/**
 * Resolves a path relative to a module's own directory into an absolute path.
 */
interface ModuleFilePathResolverInterface
{
    /**
     * @param string $relativePath Path below the module root, e.g. "data/colors.csv".
     *
     * @return string Absolute, normalised path.
     *
     * @throws NotFoundException When the module is not registered.
     */
    public function resolve(string $relativePath): string;
}
