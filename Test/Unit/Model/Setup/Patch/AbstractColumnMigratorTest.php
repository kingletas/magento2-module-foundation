<?php
/**
 * AbstractColumnMigratorTest.php
 *
 * @package     Commerce_Foundation
 * @copyright   Copyright (c) the Commerce modules authors
 * @license     OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Commerce\Foundation\Test\Unit\Model\Setup\Patch;

use Commerce\Foundation\Model\Setup\Patch\AbstractColumnMigrator;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use RuntimeException;

class AbstractColumnMigratorTest extends TestCase
{
    private AdapterInterface&MockObject $connection;

    /** @var array<int, array{sql: string, bind: array<int, int>}> */
    private array $queries = [];

    /** @var array<string, bool> */
    private array $existingColumns = [
        'pfx_source.legacy_colour' => true,
        'pfx_target.featured_colour' => true,
    ];

    /** @var array<string, mixed> */
    private array $bounds = ['min_id' => 1, 'max_id' => 1];

    protected function setUp(): void
    {
        $this->queries = [];

        $select = $this->createMock(Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('where')->willReturnSelf();

        $this->connection = $this->createMock(AdapterInterface::class);
        $this->connection->method('select')->willReturn($select);
        $this->connection->method('quoteIdentifier')
            ->willReturnCallback(static fn ($id): string => '`' . $id . '`');
        $this->connection->method('tableColumnExists')
            ->willReturnCallback(fn (string $table, string $column): bool
                => $this->existingColumns[$table . '.' . $column] ?? false);
        $this->connection->method('fetchRow')->willReturnCallback(fn (): array => $this->bounds);
        $this->connection->method('query')->willReturnCallback(
            function (string $sql, array $bind = []) {
                $this->queries[] = ['sql' => $sql, 'bind' => $bind];

                return $this->createMock(\Zend_Db_Statement_Interface::class);
            }
        );
    }

    /**
     * One set-based statement per chunk rather than one update per row.
     */
    public function testOneSetBasedStatementIsIssuedPerChunk(): void
    {
        $this->bounds = ['min_id' => 1, 'max_id' => 100];

        $this->migrator(['chunkSize' => 50])->apply();

        $this->assertCount(2, $this->queries);
        $this->assertSame([1, 51], $this->queries[0]['bind']);
        $this->assertSame([51, 101], $this->queries[1]['bind']);
        $this->assertStringContainsString('UPDATE', $this->queries[0]['sql']);
        $this->assertStringContainsString('INNER JOIN', $this->queries[0]['sql']);
    }

    /**
     * Half-open ranges: a row on a chunk boundary belongs to exactly one
     * statement.
     */
    public function testTheChunkRangesAreHalfOpenSoNoRowIsVisitedTwice(): void
    {
        $this->bounds = ['min_id' => 1, 'max_id' => 100];

        $this->migrator(['chunkSize' => 50])->apply();

        $this->assertStringContainsString('>= ? AND', $this->queries[0]['sql']);
        $this->assertStringContainsString('< ?', $this->queries[0]['sql']);
        $this->assertSame($this->queries[0]['bind'][1], $this->queries[1]['bind'][0]);
    }

    /**
     * Values are copied by the database and never marshalled through PHP.
     */
    public function testTheValuesAreCopiedColumnToColumnRatherThanBoundAsData(): void
    {
        $this->migrator()->apply();

        $this->assertStringContainsString('SET t.`featured_colour` = s.`legacy_colour`', $this->queries[0]['sql']);
        $this->assertSame([1, 50001], $this->queries[0]['bind']);
    }

    public function testTheTargetColumnDefaultsToTheSourceColumnName(): void
    {
        $this->existingColumns = ['pfx_source.colour' => true, 'pfx_target.colour' => true];

        $this->migrator(['sourceColumn' => 'colour', 'targetColumn' => null])->apply();

        $this->assertStringContainsString('SET t.`colour` = s.`colour`', $this->queries[0]['sql']);
    }

    public function testTheTableNamesGoThroughTheResourceSoThePrefixIsApplied(): void
    {
        $this->migrator()->apply();

        $this->assertStringContainsString('`pfx_target`', $this->queries[0]['sql']);
        $this->assertStringContainsString('`pfx_source`', $this->queries[0]['sql']);
    }

    public function testTheJoinUsesTheConfiguredPrimaryKey(): void
    {
        $this->existingColumns = ['pfx_source.legacy_colour' => true, 'pfx_target.featured_colour' => true];

        $this->migrator(['primaryKey' => 'row_id'])->apply();

        $this->assertStringContainsString('ON t.`row_id` = s.`row_id`', $this->queries[0]['sql']);
    }

    /**
     * A schema where the column was never added is a no-op rather than a failed
     * patch.
     */
    public function testAMissingColumnOnEitherSideIsANoOp(): void
    {
        $this->existingColumns = ['pfx_source.legacy_colour' => true];
        $this->migrator()->apply();

        $this->existingColumns = ['pfx_target.featured_colour' => true];
        $this->migrator()->apply();

        $this->assertSame([], $this->queries);
    }

    public function testAnEmptySourceTableIsANoOp(): void
    {
        $this->bounds = ['min_id' => null, 'max_id' => null];

        $this->migrator()->apply();

        $this->assertSame([], $this->queries);
    }

    /**
     * A table whose first id is 0 is not an empty table.
     */
    public function testAZeroPrimaryKeyIsNotMistakenForAnEmptyTable(): void
    {
        $this->bounds = ['min_id' => '0', 'max_id' => '10'];

        $this->migrator(['chunkSize' => 50])->apply();

        $this->assertCount(1, $this->queries);
        $this->assertSame([0, 50], $this->queries[0]['bind']);
    }

    public function testASingleRowStillGetsAStatement(): void
    {
        $this->bounds = ['min_id' => 7, 'max_id' => 7];

        $this->migrator(['chunkSize' => 50])->apply();

        $this->assertCount(1, $this->queries);
        $this->assertSame([7, 57], $this->queries[0]['bind']);
    }

    /**
     * A failed migration must not record itself as applied and never be
     * retried.
     */
    public function testAFailureIsRethrownRatherThanRecordedAsSuccess(): void
    {
        $this->connection = $this->failingConnection();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Deadlock found');

        $this->migrator()->apply();
    }

    public function testAFailureIsLoggedWithBothColumnsNamed(): void
    {
        $this->connection = $this->failingConnection();
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('critical')
            ->with(
                $this->stringContains('source.legacy_colour to target.featured_colour'),
                $this->callback(static fn (array $context): bool => $context['exception'] instanceof RuntimeException)
            );

        try {
            $this->migrator([], $logger)->apply();
            $this->fail('Expected the failure to propagate.');
        } catch (RuntimeException) {
            // Expected; the assertion under test is the log call.
        }
    }

    public function testApplyReturnsThePatchForChaining(): void
    {
        $migrator = $this->migrator();

        $this->assertSame($migrator, $migrator->apply());
    }

    public function testThePatchDeclaresNoDependenciesOrAliasesOfItsOwn(): void
    {
        $this->assertSame([], AbstractColumnMigrator::getDependencies());
        $this->assertSame([], $this->migrator()->getAliases());
    }

    public function testTheDefaultChunkSizeIsBounded(): void
    {
        $this->assertGreaterThan(0, AbstractColumnMigrator::DEFAULT_CHUNK_SIZE);
        $this->assertLessThanOrEqual(100000, AbstractColumnMigrator::DEFAULT_CHUNK_SIZE);
    }

    private function failingConnection(): AdapterInterface&MockObject
    {
        $select = $this->createMock(Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('where')->willReturnSelf();

        $connection = $this->createMock(AdapterInterface::class);
        $connection->method('select')->willReturn($select);
        $connection->method('quoteIdentifier')
            ->willReturnCallback(static fn ($id): string => '`' . $id . '`');
        $connection->method('tableColumnExists')->willReturn(true);
        $connection->method('fetchRow')->willReturn(['min_id' => 1, 'max_id' => 10]);
        $connection->method('query')->willThrowException(new RuntimeException('Deadlock found'));

        return $connection;
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function migrator(array $overrides = [], ?LoggerInterface $logger = null): AbstractColumnMigrator
    {
        $resourceConnection = $this->createMock(ResourceConnection::class);
        $resourceConnection->method('getConnection')->willReturn($this->connection);
        $resourceConnection->method('getTableName')
            ->willReturnCallback(static fn (string $table): string => 'pfx_' . $table);

        $config = $overrides + [
            'sourceColumn' => 'legacy_colour',
            'targetColumn' => 'featured_colour',
            'primaryKey' => 'entity_id',
            'chunkSize' => AbstractColumnMigrator::DEFAULT_CHUNK_SIZE,
        ];

        return new class (
            $resourceConnection,
            $logger ?? new NullLogger(),
            'source',
            'target',
            $config['sourceColumn'],
            $config['targetColumn'],
            $config['primaryKey'],
            $config['chunkSize']
        ) extends AbstractColumnMigrator {
        };
    }
}
