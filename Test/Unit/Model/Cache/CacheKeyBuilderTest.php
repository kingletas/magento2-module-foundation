<?php
/**
 * CacheKeyBuilderTest.php
 *
 * @package     Commerce_Foundation
 * @copyright   Copyright (c) the Commerce modules authors
 * @license     OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Commerce\Foundation\Test\Unit\Model\Cache;

use Commerce\Foundation\Model\Cache\CacheKeyBuilder;
use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\TestCase;

class CacheKeyBuilderTest extends TestCase
{
    public function testPrefixesAndJoinsTheParts(): void
    {
        $builder = new CacheKeyBuilder(new Json(), 'acme');

        $this->assertSame('acme_1_SKU-9', $builder->build(1, 'SKU-9'));
    }

    /**
     * A caller passing fewer parts than the template expects must not collide
     * on one key.
     */
    public function testAnyNumberOfPartsIsSafe(): void
    {
        $builder = new CacheKeyBuilder(new Json(), 'acme');

        $this->assertSame('acme', $builder->build());
        $this->assertSame('acme_a', $builder->build('a'));
        $this->assertSame('acme_a_b_c_d_e', $builder->build('a', 'b', 'c', 'd', 'e'));
        $this->assertStringNotContainsString('%', $builder->build('a'));
    }

    public function testDifferentPartsNeverCollide(): void
    {
        $builder = new CacheKeyBuilder(new Json(), 'acme');

        $this->assertNotSame($builder->build('a', 'b'), $builder->build('ab'));
        $this->assertNotSame($builder->build('a', 'b'), $builder->build('b', 'a'));
    }

    /**
     * Unbounded input must not produce an unbounded key: most cache backends
     * cap key length and silently truncate past it, which collides.
     */
    public function testArraysAndObjectsAreHashedToABoundedLength(): void
    {
        $builder = new CacheKeyBuilder(new Json(), 'acme');
        $key = $builder->build(range(1, 10000));

        $this->assertLessThan(100, strlen($key));
        $this->assertStringNotContainsString('Array', $key);
        $this->assertNotSame($builder->build(range(1, 10000)), $builder->build(range(1, 9999)));
    }

    public function testNormalisesUnsafeCharactersAndNulls(): void
    {
        $builder = new CacheKeyBuilder(new Json(), 'acme');

        $this->assertSame('acme_a-b', $builder->build('a b'));
        $this->assertSame('acme_1', $builder->build(null, 1));
        $this->assertSame('acme_1_0', $builder->build(true, false));
    }

    public function testCarriesItsTagsAndLifetime(): void
    {
        $builder = new CacheKeyBuilder(new Json(), 'acme', ['tag_a', 'tag_b'], 3600);

        $this->assertSame(['tag_a', 'tag_b'], $builder->getTags());
        $this->assertSame(3600, $builder->getLifetime());
        $this->assertNull((new CacheKeyBuilder(new Json(), 'acme'))->getLifetime());
    }
}
