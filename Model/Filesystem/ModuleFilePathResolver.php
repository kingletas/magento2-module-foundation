<?php
/**
 * @package   Kingletas_Foundation
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\Foundation\Model\Filesystem;

use Kingletas\Foundation\Api\ModuleFilePathResolverInterface;
use Magento\Framework\Exception\NotFoundException;
use Magento\Framework\Module\Dir\Reader;

/**
 * Resolves module-relative paths.
 */
class ModuleFilePathResolver implements ModuleFilePathResolverInterface
{
    /**
     * @param string $moduleName Fully qualified module name, e.g. "Acme_Embroidery".
     */
    public function __construct(
        private readonly Reader $moduleReader,
        private readonly string $moduleName
    ) {
    }

    /**
     * @inheritDoc
     */
    public function resolve(string $relativePath): string
    {
        $moduleDir = $this->moduleReader->getModuleDir('', $this->moduleName);

        if ($moduleDir === '') {
            throw new NotFoundException(
                __('Module "%1" is not registered, so its files cannot be resolved.', $this->moduleName)
            );
        }

        $relativePath = trim($relativePath, '/');

        return $relativePath === ''
            ? rtrim($moduleDir, '/')
            : rtrim($moduleDir, '/') . '/' . $relativePath;
    }
}
