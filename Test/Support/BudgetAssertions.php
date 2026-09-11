<?php
/**
 * @package   Kingletas_Foundation
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\Foundation\Test\Support;

/**
 * What a workload costs, asserted as a count rather than a duration.
 *
 * @see CONTRIBUTING.md, "The four kinds of test"
 * @see CountingScopeConfig for the double these are usually pointed at
 */
trait BudgetAssertions
{
    /**
     * The cost does not depend on how much work it is given.
     *
     * @param string             $what    Named in the failure.
     * @param callable(int): int $measure Given a workload size, returns its cost.
     * @param int[]              $sizes   Workload sizes to compare.
     */
    public function assertConstantCost(string $what, callable $measure, array $sizes = [1, 200]): void
    {
        $observed = [];

        foreach ($sizes as $size) {
            $observed[$size] = $measure($size);
        }

        $distinct = array_values(array_unique($observed));

        if (count($distinct) === 1) {
            $this->assertSame($distinct, $distinct);

            return;
        }

        $this->fail(sprintf(
            "%s grows with the size of the work, so something in the loop is a round trip.\n\n  %s\n\n"
            . "Look for a read that could have been hoisted out of the loop, or a per-item lookup that "
            . "could have been one batched call before it.",
            ucfirst($what),
            implode("\n  ", array_map(
                static fn (int $size, int $cost): string => sprintf('%6d items -> %d', $size, $cost),
                array_keys($observed),
                $observed
            ))
        ));
    }

    /**
     * The workload costs no more than this.
     *
     * @param string $what   Named in the failure.
     * @param int    $limit  The most this may cost.
     * @param int    $actual What it cost.
     * @param string $detail Optional breakdown, e.g. CountingScopeConfig::summary().
     */
    public function assertCostAtMost(string $what, int $limit, int $actual, string $detail = ''): void
    {
        $this->assertLessThanOrEqual(
            $limit,
            $actual,
            sprintf(
                '%s cost %d, and the budget is %d.%s',
                ucfirst($what),
                $actual,
                $limit,
                $detail === '' ? '' : "\n  " . $detail
            )
        );
    }

    /**
     * The cost grows one step per batch, and no faster.
     *
     * @param int                $batchSize How many items one round trip covers.
     * @param callable(int): int $measure   Given a workload size, returns its cost.
     * @param int[]              $sizes
     */
    public function assertCostPerBatch(
        string $what,
        int $batchSize,
        callable $measure,
        array $sizes = [1, 200]
    ): void {
        $problems = [];

        foreach ($sizes as $size) {
            $expected = (int) ceil($size / max(1, $batchSize));
            $actual = $measure($size);

            if ($actual > $expected) {
                $problems[] = sprintf(
                    '%d items in batches of %d should cost %d, and cost %d',
                    $size,
                    $batchSize,
                    $expected,
                    $actual
                );
            }
        }

        $this->assertSame(
            [],
            $problems,
            sprintf("%s is not being batched:\n  %s", ucfirst($what), implode("\n  ", $problems))
        );
    }
}
