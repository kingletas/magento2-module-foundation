<?php
/**
 * @package   Commerce_Foundation
 * @copyright Copyright (c) the Commerce modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Commerce\Foundation\Test\Support;

use Magento\Framework\App\Config\ScopeConfigInterface;

/**
 * A scope config backed by an array, that counts what is asked of it.
 *
 * @see BudgetAssertions
 */
class CountingScopeConfig implements ScopeConfigInterface
{
    /** @var array<int, array{path: string, scope: string, code: string}> */
    private array $reads = [];

    /**
     * @param array<string, mixed> $values Keyed by full config path. A path may
     *                                     also be suffixed with "|<storeId>" to
     *                                     give one store a different answer.
     */
    public function __construct(private array $values = [])
    {
    }

    /**
     * Untyped, mirroring `ScopeConfigInterface`: adding a parameter type to an
     * untyped interface method is a load-time fatal, not a tightening.
     *
     * @param  string      $path
     * @param  string      $scopeType
     * @param  string|null $scopeCode
     * @return mixed
     */
    public function getValue($path, $scopeType = self::SCOPE_TYPE_DEFAULT, $scopeCode = null)
    {
        $this->reads[] = [
            'path' => (string) $path,
            'scope' => (string) $scopeType,
            'code' => $scopeCode === null ? '' : (string) $scopeCode,
        ];

        $scoped = $path . '|' . ($scopeCode ?? '');

        return $this->values[$scoped] ?? $this->values[$path] ?? null;
    }

    /**
     * @param  string      $path
     * @param  string      $scopeType
     * @param  string|null $scopeCode
     * @return bool
     */
    public function isSetFlag($path, $scopeType = self::SCOPE_TYPE_DEFAULT, $scopeCode = null)
    {
        $value = $this->getValue($path, $scopeType, $scopeCode);

        // Magento's own coercion: "0" is false and "1" is true, and neither is
        // a PHP truthiness question.
        return !($value === null || $value === '' || $value === '0' || $value === 0 || $value === false);
    }

    /**
     * How many reads have happened, in total or for one path.
     */
    public function reads(?string $path = null): int
    {
        if ($path === null) {
            return count($this->reads);
        }

        return count(array_filter($this->reads, static fn (array $read): bool => $read['path'] === $path));
    }

    /**
     * The distinct paths that were read, in the order first seen.
     *
     * @return string[]
     */
    public function pathsRead(): array
    {
        return array_values(array_unique(array_column($this->reads, 'path')));
    }

    /**
     * The store codes a path was read for.
     *
     * @return string[]
     */
    public function scopesReadFor(string $path): array
    {
        $codes = [];

        foreach ($this->reads as $read) {
            if ($read['path'] === $path) {
                $codes[] = $read['code'];
            }
        }

        return array_values(array_unique($codes));
    }

    public function forget(): void
    {
        $this->reads = [];
    }

    /**
     * A breakdown for a failure message: which paths, and how often.
     */
    public function summary(): string
    {
        $counts = [];

        foreach ($this->pathsRead() as $path) {
            $counts[] = sprintf('%s x%d', $path, $this->reads($path));
        }

        return $counts === [] ? 'nothing was read' : implode(', ', $counts);
    }
}
