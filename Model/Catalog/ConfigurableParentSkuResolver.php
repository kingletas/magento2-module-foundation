<?php
/**
 * ConfigurableParentSkuResolver.php
 *
 * @package     Commerce_Foundation
 * @copyright   Copyright (c) the Commerce modules authors
 * @license     OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Commerce\Foundation\Model\Catalog;

use Commerce\Foundation\Api\CacheKeyBuilderInterface;
use Commerce\Foundation\Api\ConfigurableParentSkuResolverInterface;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\EntityManager\MetadataPool;

/**
 * Resolves configurable parents with one query per batch.
 */
class ConfigurableParentSkuResolver implements ConfigurableParentSkuResolverInterface
{
    private const string CACHE_MISS = "\0miss\0";

    /**
     * Per-request memo, including negatives.
     *
     * @var array<string, string|null>
     */
    private array $memo = [];

    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly MetadataPool $metadataPool,
        private readonly CacheInterface $cache,
        private readonly CacheKeyBuilderInterface $cacheKeyBuilder
    ) {
    }

    /**
     * @inheritDoc
     */
    public function resolve(string $childSku): ?string
    {
        return $this->resolveMany([$childSku])[$childSku] ?? null;
    }

    /**
     * @inheritDoc
     */
    public function resolveMany(array $childSkus): array
    {
        $childSkus = array_values(array_unique(array_filter(
            array_map('strval', $childSkus),
            static fn (string $sku): bool => $sku !== ''
        )));

        if ($childSkus === []) {
            return [];
        }

        $resolved = [];
        $pending = [];

        foreach ($childSkus as $sku) {
            $cached = $this->readCached($sku);

            if ($cached === self::CACHE_MISS) {
                $pending[] = $sku;
                continue;
            }

            if ($cached !== null) {
                $resolved[$sku] = $cached;
            }
        }

        if ($pending !== []) {
            $resolved += $this->fetch($pending);
        }

        return $resolved;
    }

    /**
     * One query for the whole batch.
     *
     * @param string[] $childSkus
     *
     * @return array<string, string>
     */
    private function fetch(array $childSkus): array
    {
        $connection = $this->resourceConnection->getConnection();
        $productTable = $this->resourceConnection->getTableName('catalog_product_entity');
        $linkTable = $this->resourceConnection->getTableName('catalog_product_super_link');

        // entity_id on Open Source, row_id where content staging is enabled.
        $linkField = $this->metadataPool
            ->getMetadata(ProductInterface::class)
            ->getLinkField();

        $select = $connection->select()
            ->from(['child' => $productTable], ['child_sku' => 'sku'])
            ->join(
                ['link' => $linkTable],
                'link.product_id = child.entity_id',
                []
            )
            ->join(
                ['parent' => $productTable],
                sprintf('parent.%s = link.parent_id', $connection->quoteIdentifier($linkField)),
                ['parent_sku' => 'sku']
            )
            ->where('child.sku IN (?)', $childSkus);

        $found = [];

        foreach ($connection->fetchAll($select) as $row) {
            // A simple can belong to more than one configurable.
            $childSku = (string) $row['child_sku'];
            $parentSku = (string) $row['parent_sku'];

            if (!isset($found[$childSku]) || strcmp($parentSku, $found[$childSku]) < 0) {
                $found[$childSku] = $parentSku;
            }
        }

        // Cache negatives as well, so a standalone SKU is not re-queried forever.
        foreach ($childSkus as $sku) {
            $this->writeCached($sku, $found[$sku] ?? null);
        }

        return $found;
    }

    /**
     * Three-state read: a parent SKU, null for a cached negative, or the
     * CACHE_MISS sentinel meaning "not cached, go and look it up".
     */
    private function readCached(string $sku): ?string
    {
        if (array_key_exists($sku, $this->memo)) {
            return $this->memo[$sku];
        }

        $entry = $this->cache->load($this->cacheKeyBuilder->build($sku));

        if ($entry === false || $entry === null) {
            return self::CACHE_MISS;
        }

        // An empty stored entry is a cached negative, not an absent one.
        return $this->memo[$sku] = ($entry === '' ? null : (string) $entry);
    }

    private function writeCached(string $sku, ?string $parentSku): void
    {
        $this->memo[$sku] = $parentSku;

        $this->cache->save(
            $parentSku ?? '',
            $this->cacheKeyBuilder->build($sku),
            $this->cacheKeyBuilder->getTags(),
            $this->cacheKeyBuilder->getLifetime()
        );
    }
}
