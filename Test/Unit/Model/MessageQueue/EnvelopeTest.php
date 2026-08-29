<?php
/**
 * @package   Commerce_Foundation
 * @copyright Copyright (c) the Commerce modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Commerce\Foundation\Test\Unit\Model\MessageQueue;

use Commerce\Foundation\Api\MessageQueueEnvelopeInterface;
use Commerce\Foundation\Model\MessageQueue\Envelope;
use InvalidArgumentException;
use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\TestCase;

class EnvelopeTest extends TestCase
{
    private function envelope(): Envelope
    {
        return new Envelope(new Json());
    }

    public function testTheBodyIsTheSerialisedData(): void
    {
        $envelope = $this->envelope()->setData(['sku' => 'SKU-1', 'qty' => 3]);

        $this->assertSame('{"sku":"SKU-1","qty":3}', $envelope->getBody());
    }

    public function testAnEmptyEnvelopeStillProducesAValidBody(): void
    {
        $this->assertSame('[]', $this->envelope()->getBody());
    }

    /**
     * A consumer can tell a key the producer never set from one set to nothing.
     */
    public function testAMissingKeyIsNullRatherThanAnEmptyArray(): void
    {
        $envelope = $this->envelope()->set('tags', []);

        $this->assertNull($envelope->get('absent'));
        $this->assertSame([], $envelope->get('tags'));
        $this->assertTrue($envelope->has('tags'));
        $this->assertFalse($envelope->has('absent'));
    }

    /**
     * `array_key_exists` rather than `isset`: a producer that sent null said
     * something.
     */
    public function testAnExplicitNullIsPresentRatherThanMissing(): void
    {
        $envelope = $this->envelope()->set('store_id', null);

        $this->assertTrue($envelope->has('store_id'));
        $this->assertNull($envelope->get('store_id', 'fallback'));
    }

    public function testTheSuppliedDefaultIsReturnedForAMissingKey(): void
    {
        $this->assertSame(7, $this->envelope()->get('attempts', 7));
    }

    public function testSetOverwritesAndAllReturnsEverything(): void
    {
        $envelope = $this->envelope()->setData(['a' => 1])->set('b', 2)->set('a', 9);

        $this->assertSame(['a' => 9, 'b' => 2], $envelope->all());
    }

    public function testTheFluentSettersReturnTheSameEnvelope(): void
    {
        $envelope = $this->envelope();

        $this->assertSame($envelope, $envelope->set('a', 1));
        $this->assertSame($envelope, $envelope->setData([]));
        $this->assertSame($envelope, $envelope->reset());
        $this->assertSame($envelope, $envelope->populate('{"a":1}'));
    }

    public function testResetEmptiesTheEnvelope(): void
    {
        $envelope = $this->envelope()->setData(['a' => 1])->reset();

        $this->assertSame([], $envelope->all());
        $this->assertFalse($envelope->has('a'));
    }

    public function testPropertiesAreReadFromTheirReservedKey(): void
    {
        $envelope = $this->envelope()
            ->set(MessageQueueEnvelopeInterface::PROPERTY_KEY, ['delivery_mode' => 2]);

        $this->assertSame(['delivery_mode' => 2], $envelope->getProperties());
    }

    public function testPropertiesDefaultToAnEmptyArray(): void
    {
        $this->assertSame([], $this->envelope()->getProperties());
    }

    /**
     * A message crossing a queue boundary can carry anything under the
     * properties key.
     */
    public function testNonArrayPropertiesAreDiscardedRatherThanReturnedAsIs(): void
    {
        $envelope = $this->envelope()->set(MessageQueueEnvelopeInterface::PROPERTY_KEY, 'garbage');

        $this->assertSame([], $envelope->getProperties());
    }

    public function testPopulateReplacesTheContentsRatherThanMergingIntoThem(): void
    {
        $envelope = $this->envelope()->setData(['stale' => 'value'])->populate('{"fresh":"value"}');

        $this->assertSame(['fresh' => 'value'], $envelope->all());
        $this->assertFalse($envelope->has('stale'));
    }

    public function testPopulateRoundTripsABody(): void
    {
        $data = ['sku' => 'SKU-1', 'tags' => ['a', 'b'], 'nested' => ['x' => 1]];
        $envelope = $this->envelope()->populate($this->envelope()->setData($data)->getBody());

        $this->assertSame($data, $envelope->all());
    }

    /**
     * A body that decodes to a scalar is a malformed message.
     */
    public function testASerialisedScalarIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->envelope()->populate('"just a string"');
    }

    public function testANullBodyIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->envelope()->populate('null');
    }

    /**
     * Rejection leaves nothing half-populated, because the failed message is
     * retried.
     */
    public function testARejectedBodyLeavesTheEarlierContentsIntact(): void
    {
        $envelope = $this->envelope()->setData(['a' => 1]);

        try {
            $envelope->populate('42');
            $this->fail('Expected the malformed body to be rejected.');
        } catch (InvalidArgumentException) {
            // Expected.
        }

        $this->assertSame(['a' => 1], $envelope->all());
    }
}
