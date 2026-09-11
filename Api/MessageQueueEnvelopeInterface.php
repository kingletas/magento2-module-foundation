<?php
/**
 * @package   Kingletas_Foundation
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\Foundation\Api;

use Magento\Framework\MessageQueue\EnvelopeInterface;

/**
 * A mutable, serialisable message-queue envelope.
 */
interface MessageQueueEnvelopeInterface extends EnvelopeInterface
{
    /**
     * Metadata key under which AMQP-style message properties are carried.
     */
    public const string PROPERTY_KEY = 'properties';

    /**
     * @param array<string, mixed> $data
     */
    public function setData(array $data): static;

    public function set(string $key, mixed $value): static;

    public function get(string $key, mixed $default = null): mixed;

    /**
     * @return array<string, mixed>
     */
    public function all(): array;

    public function has(string $key): bool;

    public function reset(): static;

    /**
     * Rehydrate this envelope from a serialised body.
     *
     * @throws \InvalidArgumentException When the body is not a serialised array.
     */
    public function populate(string $body): static;
}
