<?php
/**
 * @package   Kingletas_Foundation
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\Foundation\Test\Performance;

use Kingletas\Foundation\Api\CacheKeyBuilderInterface;
use Kingletas\Foundation\Model\Cache\CacheKeyBuilder;
use Kingletas\Foundation\Model\Catalog\ConfigurableParentSkuResolver;
use Kingletas\Foundation\Model\Config\ModuleConfig;
use Kingletas\Foundation\Test\Support\BudgetAssertions;
use Kingletas\Foundation\Test\Support\CountingScopeConfig;
use Magento\Framework\App\CacheInterface;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\EntityManager\EntityMetadataInterface;
use Magento\Framework\EntityManager\MetadataPool;
use Magento\Framework\Serialize\SerializerInterface;
use PHPUnit\Framework\TestCase;

/**
 * What the shared pieces cost the modules built on them.
 */
class SharedReadCostTest extends TestCase
{
    use BudgetAssertions;

    private const SECTION = 'kingletas_example';

    private int $queries = 0;

    /** @var array<int, array<string, string>> */
    private array $rows = [];

    protected function setUp(): void
    {
        $this->queries = 0;
        $this->rows = [];
    }

    /**
     * The call site is a `foreach` over order items, answered here in one query
     * for the batch.
     */
    public function testResolvingParentSkusCostsTheSameWhateverTheBatchSize(): void
    {
        $this->assertConstantCost(
            'queries while resolving configurable parents',
            function (int $skus): int {
                $this->queries = 0;
                $this->rows = $this->catalogue($skus);

                $this->resolver()->resolveMany($this->childSkus($skus));

                return $this->queries;
            }
        );
    }

    /**
     * Four modules resolve parents, and on a product save they run in the same
     * request one after another.
     */
    public function testAskingTheSameQuestionAgainCostsNothing(): void
    {
        $this->rows = $this->catalogue(50);
        $resolver = $this->resolver();
        $skus = $this->childSkus(50);

        $resolver->resolveMany($skus);
        $afterFirst = $this->queries;

        $resolver->resolveMany($skus);

        $this->assertCostAtMost('a repeated resolution', $afterFirst, $this->queries);
    }

    /**
     * A SKU with no parent is remembered as having none.
     */
    public function testAKnownAbsenceIsNotLookedUpAgain(): void
    {
        $this->rows = [];
        $resolver = $this->resolver();

        $resolver->resolve('STANDALONE-SKU');
        $afterFirst = $this->queries;

        $resolver->resolve('STANDALONE-SKU');

        $this->assertCostAtMost('re-asking about a SKU with no parent', $afterFirst, $this->queries);
    }

    /**
     * `getPositiveInt()` reads the setting once rather than twice.
     */
    public function testEachTypedGetterReadsItsSettingExactlyOnce(): void
    {
        $scopeConfig = new CountingScopeConfig([
            self::SECTION . '/general/enabled' => '1',
            self::SECTION . '/general/name' => 'a name',
            self::SECTION . '/general/batch_size' => '500',
            self::SECTION . '/general/ratio' => '1.5',
            self::SECTION . '/general/recipients' => 'a@example.com, b@example.com',
        ]);

        $config = new ModuleConfig($scopeConfig, self::SECTION);

        $config->isSetFlag('general/enabled');
        $config->getString('general/name');
        $config->getInt('general/batch_size');
        $config->getPositiveInt('general/batch_size', 100);
        $config->getFloat('general/ratio');
        $config->getList('general/recipients');

        $this->assertCostAtMost('six typed reads', 6, $scopeConfig->reads(), $scopeConfig->summary());
    }

    /**
     * The fallback path is the one taken on a fresh install, which is also the
     * one nobody profiles.
     */
    public function testFallingBackToADefaultCostsOneReadToo(): void
    {
        $scopeConfig = new CountingScopeConfig([]);
        $config = new ModuleConfig($scopeConfig, self::SECTION);

        $config->getPositiveInt('general/batch_size', 100);
        $config->getString('general/name', 'fallback');
        $config->getList('general/recipients');

        $this->assertCostAtMost('three reads that all fall back', 3, $scopeConfig->reads(), $scopeConfig->summary());
    }

    /**
     * A key is built once per lookup, in front of the thing the cache exists to
     * avoid.
     */
    public function testBuildingAKeyFromScalarsNeverReachesTheSerializer(): void
    {
        $serializations = 0;

        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->method('serialize')->willReturnCallback(
            function ($value) use (&$serializations): string {
                $serializations++;

                return json_encode($value) ?: '';
            }
        );

        $keys = new CacheKeyBuilder($serializer, 'kingletas_example');

        for ($i = 0; $i < 200; $i++) {
            $keys->build(1, $i, 'TOKEN-' . $i, true, null);
        }

        $this->assertSame(
            0,
            $serializations,
            'A key built from scalars should be a join, not a serialization.'
        );
    }

    public function testANonScalarPartIsSerializedOnce(): void
    {
        $serializations = 0;

        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->method('serialize')->willReturnCallback(
            function ($value) use (&$serializations): string {
                $serializations++;

                return json_encode($value) ?: '';
            }
        );

        (new CacheKeyBuilder($serializer, 'kingletas_example'))->build(1, ['a', 'b', 'c'], 'TOKEN');

        $this->assertSame(1, $serializations);
    }

    private function resolver(): ConfigurableParentSkuResolver
    {
        $select = $this->createMock(Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('join')->willReturnSelf();
        $select->method('where')->willReturnSelf();

        $connection = $this->createMock(AdapterInterface::class);
        $connection->method('quoteIdentifier')->willReturnCallback(static fn ($ident): string => '`' . $ident . '`');
        $connection->method('select')->willReturn($select);
        $connection->method('fetchAll')->willReturnCallback(function (): array {
            $this->queries++;

            return $this->rows;
        });

        $resourceConnection = $this->createMock(ResourceConnection::class);
        $resourceConnection->method('getConnection')->willReturn($connection);
        $resourceConnection->method('getTableName')->willReturnCallback(static fn (string $t): string => $t);

        $metadata = $this->createMock(EntityMetadataInterface::class);
        $metadata->method('getLinkField')->willReturn('entity_id');

        $metadataPool = $this->createMock(MetadataPool::class);
        $metadataPool->method('getMetadata')->with(ProductInterface::class)->willReturn($metadata);

        $cacheKeyBuilder = $this->createMock(CacheKeyBuilderInterface::class);
        $cacheKeyBuilder->method('build')->willReturnCallback(
            static fn (...$parts): string => 'parent_sku_' . implode('_', array_map('strval', $parts))
        );
        $cacheKeyBuilder->method('getTags')->willReturn([]);
        $cacheKeyBuilder->method('getLifetime')->willReturn(null);

        return new ConfigurableParentSkuResolver(
            $resourceConnection,
            $metadataPool,
            $this->cache(),
            $cacheKeyBuilder
        );
    }

    /**
     * @return string[]
     */
    private function childSkus(int $count): array
    {
        $skus = [];

        for ($i = 1; $i <= $count; $i++) {
            $skus[] = 'SHIRT-' . $i;
        }

        return $skus;
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function catalogue(int $count): array
    {
        $rows = [];

        for ($i = 1; $i <= $count; $i++) {
            $rows[] = ['child_sku' => 'SHIRT-' . $i, 'parent_sku' => 'SHIRT'];
        }

        return $rows;
    }

    private function cache(): CacheInterface
    {
        $entries = [];

        $cache = $this->createMock(CacheInterface::class);
        $cache->method('load')->willReturnCallback(
            static fn (string $identifier): string|false => $entries[$identifier] ?? false
        );
        $cache->method('save')->willReturnCallback(
            static function (string $data, string $identifier) use (&$entries): bool {
                $entries[$identifier] = $data;

                return true;
            }
        );

        return $cache;
    }
}
