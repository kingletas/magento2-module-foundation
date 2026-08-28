<?php
/**
 * IssuedLinkTest.php
 *
 * @package     Commerce_Foundation
 * @copyright   Copyright (c) the Commerce modules authors
 * @license     OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Commerce\Foundation\Test\Behaviour;

use Commerce\Foundation\Model\Cache\CacheKeyBuilder;
use Commerce\Foundation\Model\Registry;
use Commerce\Foundation\Model\Security\TokenGenerator;
use Commerce\Foundation\Test\Unit\Fake\ArrayCache;
use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\TestCase;

/**
 * The three pieces a signed link is built from, composed.
 */
class IssuedLinkTest extends TestCase
{
    private TokenGenerator $tokens;
    private CacheKeyBuilder $keys;
    private ArrayCache $cache;
    private Registry $registry;

    protected function setUp(): void
    {
        $this->tokens = new TokenGenerator();
        $this->keys = new CacheKeyBuilder(new Json(), 'commerce_sharecart', ['COMMERCE_SHARED_CART'], 3600);
        $this->cache = new ArrayCache();
        $this->registry = new Registry();
    }

    /**
     * Issue a link, store only its hash, and let the holder back in.
     */
    public function testTheHolderOfAnIssuedTokenIsLetBackIn(): void
    {
        $token = $this->tokens->generate();
        $stored = $this->tokens->hash($token);

        $this->assertNotSame($token, $stored, 'The token itself must never be what is stored.');
        $this->assertTrue($this->tokens->matches($token, $stored));
    }

    /**
     * A flipped character and a truncated token, both of which a prefix
     * comparison would accept.
     */
    public function testAnAlteredOrTruncatedTokenIsRefused(): void
    {
        $token = $this->tokens->generate();
        $stored = $this->tokens->hash($token);

        $altered = $token[0] === 'a' ? 'b' . substr($token, 1) : 'a' . substr($token, 1);

        $this->assertFalse($this->tokens->matches($altered, $stored));
        $this->assertFalse($this->tokens->matches(substr($token, 0, -4), $stored));
        $this->assertFalse($this->tokens->matches('', $stored));
    }

    /**
     * `generate(4)` is what a caller writes when they are thinking about URL
     * length rather than about search space.
     */
    public function testATokenIsNeverShorterThanTheFloor(): void
    {
        $this->assertSame(TokenGenerator::MIN_BYTES * 2, strlen($this->tokens->generate(4)));
    }

    /**
     * The seam: `build()`, `getTags()` and `getLifetime()` are three calls a
     * module hands to one `save()`.
     */
    public function testACachedAnswerIsFoundAgainAndDroppedWhenItsTagIsCleaned(): void
    {
        $key = $this->keys->build(1, 'CART-TOKEN-1');

        $this->cache->save('the rendered cart', $key, $this->keys->getTags(), $this->keys->getLifetime());

        $this->assertSame('the rendered cart', $this->cache->load($this->keys->build(1, 'CART-TOKEN-1')));

        $this->cache->clean($this->keys->getTags());

        $this->assertFalse($this->cache->load($key), 'Cleaning the tag should have dropped the entry.');
    }

    /**
     * A template called with too few parts must not produce a key containing a
     * literal `%s`.
     */
    public function testTwoDifferentLookupsNeverCollideOnOneEntry(): void
    {
        $this->cache->save('store one, first cart', $this->keys->build(1, 'CART-TOKEN-1'), [], null);
        $this->cache->save('store one, second cart', $this->keys->build(1, 'CART-TOKEN-2'), [], null);
        $this->cache->save('store two, first cart', $this->keys->build(2, 'CART-TOKEN-1'), [], null);

        $this->assertSame('store one, first cart', $this->cache->load($this->keys->build(1, 'CART-TOKEN-1')));
        $this->assertSame('store one, second cart', $this->cache->load($this->keys->build(1, 'CART-TOKEN-2')));
        $this->assertSame('store two, first cart', $this->cache->load($this->keys->build(2, 'CART-TOKEN-1')));
    }

    /**
     * A controller resolves the shared cart; a block, built later by the layout
     * and holding no reference to the controller, renders it.
     */
    public function testAnAnswerComputedInOneLayerIsReadableInAnother(): void
    {
        $this->resolveInTheController('CART-TOKEN-1');

        $this->assertSame('CART-TOKEN-1', $this->renderInTheBlock());
    }

    /**
     * `set()` throws when a registry key is taken; `replace()` is the call that
     * means it.
     */
    public function testASecondWriterHasToSayItMeansIt(): void
    {
        $this->registry->set('shared_cart_token', 'CART-TOKEN-1');

        // Graceful: the second writer stands down, and the first value stands.
        $this->registry->set('shared_cart_token', 'CART-TOKEN-2', true);
        $this->assertSame('CART-TOKEN-1', $this->registry->get('shared_cart_token'));

        $this->registry->replace('shared_cart_token', 'CART-TOKEN-2');
        $this->assertSame('CART-TOKEN-2', $this->registry->get('shared_cart_token'));
    }

    private function resolveInTheController(string $token): void
    {
        $this->registry->set('shared_cart_token', $token);
    }

    private function renderInTheBlock(): ?string
    {
        return $this->registry->get('shared_cart_token');
    }
}
