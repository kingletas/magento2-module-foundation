<?php
/**
 * @copyright Copyright (c) the Commerce modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Commerce\Foundation\Test\Unit\Model\Catalog;

use Commerce\Foundation\Api\CacheKeyBuilderInterface;
use Commerce\Foundation\Model\Catalog\ConfigurableParentSkuResolver;
use Commerce\Foundation\Test\Unit\Fake\ArrayCache;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\EntityManager\EntityMetadataInterface;
use Magento\Framework\EntityManager\MetadataPool;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Three things are being pinned down here, and none of them is "it returns a
 * parent SKU".
 */
final class ConfigurableParentSkuResolverTest extends TestCase
{
    private AdapterInterface&MockObject $connection;
    private ResourceConnection&MockObject $resourceConnection;
    private ArrayCache $cache;
    private string $linkField = 'entity_id';

    /** @var array<int, array<string, string>> Rows the next fetchAll returns. */
    private array $rows = [];

    /** @var array<int, string> Join conditions seen, in order. */
    private array $joinConditions = [];

    public int $queries = 0;

    protected function setUp(): void
    {
        $this->rows = [];
        $this->joinConditions = [];
        $this->queries = 0;
        $this->cache = new ArrayCache();

        $this->connection = $this->createMock(AdapterInterface::class);
        $this->connection->method('quoteIdentifier')
            ->willReturnCallback(static fn ($ident): string => '`' . $ident . '`');
        $this->connection->method('select')->willReturnCallback(fn (): Select => $this->newSelect());
        $this->connection->method('fetchAll')->willReturnCallback(function (): array {
            $this->queries++;

            return $this->rows;
        });

        $this->resourceConnection = $this->createMock(ResourceConnection::class);
        $this->resourceConnection->method('getConnection')->willReturn($this->connection);
        $this->resourceConnection->method('getTableName')
            ->willReturnCallback(static fn (string $table): string => $table);
    }

    public function testAChildResolvesToItsParent(): void
    {
        $this->rows = [['child_sku' => 'SHIRT-M', 'parent_sku' => 'SHIRT']];

        self::assertSame('SHIRT', $this->resolver()->resolve('SHIRT-M'));
    }

    public function testAChildWithNoParentResolvesToNull(): void
    {
        $this->rows = [];

        self::assertNull($this->resolver()->resolve('STANDALONE'));
    }

    /**
     * The reason this class exists. Whatever the batch size, it is one query.
     */
    public function testAWholeBatchCostsOneQuery(): void
    {
        $this->rows = [
            ['child_sku' => 'A-1', 'parent_sku' => 'A'],
            ['child_sku' => 'B-1', 'parent_sku' => 'B'],
            ['child_sku' => 'C-1', 'parent_sku' => 'C'],
        ];

        $resolved = $this->resolver()->resolveMany(['A-1', 'B-1', 'C-1', 'D-1']);

        self::assertSame(1, $this->queries);
        self::assertSame(['A-1' => 'A', 'B-1' => 'B', 'C-1' => 'C'], $resolved);
    }

    public function testTheSecondCallForTheSameSkuCostsNoQuery(): void
    {
        $this->rows = [['child_sku' => 'SHIRT-M', 'parent_sku' => 'SHIRT']];
        $resolver = $this->resolver();

        $resolver->resolve('SHIRT-M');
        $resolver->resolve('SHIRT-M');

        self::assertSame(1, $this->queries);
    }

    /**
     * A standalone SKU is the majority of most catalogues.
     */
    public function testANegativeAnswerIsRememberedToo(): void
    {
        $this->rows = [];
        $resolver = $this->resolver();

        $resolver->resolve('STANDALONE');
        $resolver->resolve('STANDALONE');
        $resolver->resolve('STANDALONE');

        self::assertSame(1, $this->queries);
    }

    public function testANegativeIsWrittenToTheSharedCacheAsAnEmptyString(): void
    {
        $this->rows = [];

        $this->resolver()->resolve('STANDALONE');

        self::assertSame([['identifier' => 'key:STANDALONE', 'data' => '']], $this->cache->saves);
    }

    /**
     * A cached negative survives the round trip as "no parent" rather than as
     * nothing cached.
     */
    public function testACachedNegativeIsReadBackWithoutQuerying(): void
    {
        $this->cache->entries['key:STANDALONE'] = '';

        self::assertNull($this->resolver()->resolve('STANDALONE'));
        self::assertSame(0, $this->queries);
    }

    public function testACachedParentIsReadBackWithoutQuerying(): void
    {
        $this->cache->entries['key:SHIRT-M'] = 'SHIRT';

        self::assertSame('SHIRT', $this->resolver()->resolve('SHIRT-M'));
        self::assertSame(0, $this->queries);
    }

    /**
     * A partially cached batch queries for the remainder only.
     */
    public function testOnlyTheUncachedPartOfABatchIsQueried(): void
    {
        $this->cache->entries['key:A-1'] = 'A';
        $this->rows = [['child_sku' => 'B-1', 'parent_sku' => 'B']];

        $resolved = $this->resolver()->resolveMany(['A-1', 'B-1']);

        self::assertSame(1, $this->queries);
        self::assertSame(['A-1' => 'A', 'B-1' => 'B'], $resolved);
    }

    public function testTheJoinUsesEntityIdOnOpenSource(): void
    {
        $this->linkField = 'entity_id';
        $this->rows = [];

        $this->resolver()->resolve('SHIRT-M');

        self::assertContains('parent.`entity_id` = link.parent_id', $this->joinConditions);
    }

    /**
     * The staging bug.
     */
    public function testTheJoinUsesRowIdWhereContentStagingIsEnabled(): void
    {
        $this->linkField = 'row_id';
        $this->rows = [];

        $this->resolver()->resolve('SHIRT-M');

        self::assertContains('parent.`row_id` = link.parent_id', $this->joinConditions);
        self::assertNotContains('parent.`entity_id` = link.parent_id', $this->joinConditions);
    }

    /**
     * A simple can belong to more than one configurable, so the answer is
     * ordered rather than first.
     */
    public function testAChildWithTwoParentsResolvesDeterministically(): void
    {
        $this->rows = [
            ['child_sku' => 'SHIRT-M', 'parent_sku' => 'SHIRT-WINTER'],
            ['child_sku' => 'SHIRT-M', 'parent_sku' => 'SHIRT-CLASSIC'],
        ];

        self::assertSame('SHIRT-CLASSIC', $this->resolver()->resolve('SHIRT-M'));

        // The same rows the other way round must give the same answer.
        $this->rows = array_reverse($this->rows);
        $this->cache = new ArrayCache();

        self::assertSame('SHIRT-CLASSIC', $this->resolver()->resolve('SHIRT-M'));
    }

    public function testAnEmptyBatchCostsNothing(): void
    {
        self::assertSame([], $this->resolver()->resolveMany([]));
        self::assertSame(0, $this->queries);
    }

    public function testBlankAndDuplicateSkusAreDroppedBeforeQuerying(): void
    {
        $this->rows = [['child_sku' => 'A-1', 'parent_sku' => 'A']];

        $resolved = $this->resolver()->resolveMany(['A-1', 'A-1', '']);

        self::assertSame(1, $this->queries);
        self::assertSame(['A-1' => 'A'], $resolved);
    }

    private function resolver(): ConfigurableParentSkuResolver
    {
        $metadata = $this->createMock(EntityMetadataInterface::class);
        $metadata->method('getLinkField')->willReturnCallback(fn (): string => $this->linkField);

        $metadataPool = $this->createMock(MetadataPool::class);
        $metadataPool->method('getMetadata')
            ->willReturnCallback(function (string $entity) use ($metadata): EntityMetadataInterface {
                self::assertSame(ProductInterface::class, $entity);

                return $metadata;
            });

        $keyBuilder = $this->createMock(CacheKeyBuilderInterface::class);
        $keyBuilder->method('build')
            ->willReturnCallback(static fn (mixed ...$parts): string => 'key:' . implode(':', $parts));
        $keyBuilder->method('getTags')->willReturn(['TAG']);
        $keyBuilder->method('getLifetime')->willReturn(3600);

        return new ConfigurableParentSkuResolver(
            $this->resourceConnection,
            $metadataPool,
            $this->cache,
            $keyBuilder
        );
    }

    private function newSelect(): Select
    {
        $select = $this->createMock(Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('where')->willReturnSelf();
        $select->method('join')->willReturnCallback(
            function ($name, $condition) use ($select): Select {
                $this->joinConditions[] = (string) $condition;

                return $select;
            }
        );

        return $select;
    }
}
