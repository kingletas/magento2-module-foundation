<?php
/**
 * AbstractColumnMigrator.php
 *
 * @package     Commerce_Foundation
 * @copyright   Copyright (c) the Commerce modules authors
 * @license     OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Commerce\Foundation\Model\Setup\Patch;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Psr\Log\LoggerInterface;
use Throwable;
use Zend_Db_Expr;

/**
 * Copies a column from one table to another during a data patch.
 */
abstract class AbstractColumnMigrator implements DataPatchInterface
{
    public const int DEFAULT_CHUNK_SIZE = 50000;

    /**
     * @param string      $sourceTable  Unprefixed table holding the values to copy.
     * @param string      $targetTable  Unprefixed table receiving them.
     * @param string      $sourceColumn Column to read.
     * @param string|null $targetColumn Column to write; defaults to $sourceColumn.
     * @param string      $primaryKey   Column joining the two tables.
     * @param int         $chunkSize    Rows per statement.
     */
    public function __construct(
        protected readonly ResourceConnection $resourceConnection,
        protected readonly LoggerInterface $logger,
        protected readonly string $sourceTable,
        protected readonly string $targetTable,
        protected readonly string $sourceColumn,
        protected readonly ?string $targetColumn = null,
        protected readonly string $primaryKey = 'entity_id',
        protected readonly int $chunkSize = self::DEFAULT_CHUNK_SIZE
    ) {
    }

    /**
     * @inheritDoc
     */
    public function apply(): static
    {
        $connection = $this->getConnection();
        $source = $this->resourceConnection->getTableName($this->sourceTable);
        $target = $this->resourceConnection->getTableName($this->targetTable);
        $targetColumn = $this->targetColumn ?? $this->sourceColumn;

        // A patch may legitimately run against a schema where the column was
        // never added (module installed but feature never enabled).
        if (!$connection->tableColumnExists($target, $targetColumn)
            || !$connection->tableColumnExists($source, $this->sourceColumn)
        ) {
            return $this;
        }

        try {
            $this->copyInChunks($connection, $source, $target, $targetColumn);
        } catch (Throwable $e) {
            $this->logger->critical(
                sprintf(
                    'Failed migrating %s.%s to %s.%s: %s',
                    $this->sourceTable,
                    $this->sourceColumn,
                    $this->targetTable,
                    $targetColumn,
                    $e->getMessage()
                ),
                ['exception' => $e]
            );

            // Rethrow: a half-applied migration recorded as complete is worse
            // than a visibly failed one.
            throw $e;
        }

        return $this;
    }

    private function copyInChunks(
        AdapterInterface $connection,
        string $source,
        string $target,
        string $targetColumn
    ): void {
        $pk = $connection->quoteIdentifier($this->primaryKey);
        $bounds = $connection->fetchRow(
            $connection->select()
                ->from($source, [
                    'min_id' => new Zend_Db_Expr("MIN({$pk})"),
                    'max_id' => new Zend_Db_Expr("MAX({$pk})"),
                ])
                ->where("{$connection->quoteIdentifier($this->sourceColumn)} IS NOT NULL")
        );

        if (empty($bounds['min_id']) && $bounds['min_id'] !== 0 && $bounds['min_id'] !== '0') {
            return;
        }

        $min = (int) $bounds['min_id'];
        $max = (int) $bounds['max_id'];

        $sql = sprintf(
            'UPDATE %s AS t INNER JOIN %s AS s ON t.%s = s.%s'
            . ' SET t.%s = s.%s'
            . ' WHERE s.%s IS NOT NULL AND s.%s >= ? AND s.%s < ?',
            $connection->quoteIdentifier($target),
            $connection->quoteIdentifier($source),
            $pk,
            $pk,
            $connection->quoteIdentifier($targetColumn),
            $connection->quoteIdentifier($this->sourceColumn),
            $connection->quoteIdentifier($this->sourceColumn),
            $pk,
            $pk
        );

        // Each chunk is its own transaction, so a failure part-way through
        // leaves earlier chunks committed and the patch safe to re-run.
        for ($start = $min; $start <= $max; $start += $this->chunkSize) {
            $connection->query($sql, [$start, $start + $this->chunkSize]);
        }
    }

    protected function getConnection(): AdapterInterface
    {
        return $this->resourceConnection->getConnection();
    }

    /**
     * @inheritDoc
     */
    public static function getDependencies(): array
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    public function getAliases(): array
    {
        return [];
    }
}
