<?php
/**
 * ModuleConfig.php
 *
 * @package     Commerce_Foundation
 * @copyright   Copyright (c) the Commerce modules authors
 * @license     OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Commerce\Foundation\Model\Config;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * Typed, scope-aware reader for one `core_config_data` section.
 */
class ModuleConfig
{
    /**
     * @param string $section Config section id, e.g. "acme_embroidery".
     */
    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly string $section
    ) {
    }

    public function getSection(): string
    {
        return $this->section;
    }

    /**
     * Read a boolean flag. Handles "0"/"1"/"true"/"" consistently.
     *
     * @param string $path Path below the section, e.g. "general/enabled".
     */
    public function isSetFlag(string $path, ?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            $this->qualify($path),
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function getString(string $path, string $default = '', ?int $storeId = null): string
    {
        $value = $this->getValue($path, $storeId);

        return is_scalar($value) && (string) $value !== '' ? (string) $value : $default;
    }

    public function getInt(string $path, int $default = 0, ?int $storeId = null): int
    {
        $value = $this->getValue($path, $storeId);

        return is_numeric($value) ? (int) $value : $default;
    }

    /**
     * Read an integer that must be positive, e.g. a batch size or a TTL.
     */
    public function getPositiveInt(string $path, int $default, ?int $storeId = null): int
    {
        $value = $this->getInt($path, $default, $storeId);

        return $value > 0 ? $value : $default;
    }

    public function getFloat(string $path, float $default = 0.0, ?int $storeId = null): float
    {
        $value = $this->getValue($path, $storeId);

        return is_numeric($value) ? (float) $value : $default;
    }

    /**
     * Read a comma-separated list, trimmed and stripped of empty entries.
     *
     * @return string[]
     */
    public function getList(string $path, ?int $storeId = null, string $separator = ','): array
    {
        $raw = $this->getString($path, '', $storeId);

        if ($raw === '') {
            return [];
        }

        $parts = array_map('trim', explode($separator, $raw));

        return array_values(array_filter($parts, static fn (string $part): bool => $part !== ''));
    }

    public function getValue(string $path, ?int $storeId = null): mixed
    {
        return $this->scopeConfig->getValue(
            $this->qualify($path),
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    private function qualify(string $path): string
    {
        return $this->section . '/' . ltrim($path, '/');
    }
}
