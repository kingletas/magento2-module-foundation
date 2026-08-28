<?php
/**
 * Envelope.php
 *
 * @package     Commerce_Foundation
 * @copyright   Copyright (c) the Commerce modules authors
 * @license     OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Commerce\Foundation\Model\MessageQueue;

use Commerce\Foundation\Api\MessageQueueEnvelopeInterface;
use InvalidArgumentException;
use Magento\Framework\Serialize\SerializerInterface;

/**
 * Default array-backed envelope.
 */
class Envelope implements MessageQueueEnvelopeInterface
{
    /** @var array<string, mixed> */
    private array $data = [];

    public function __construct(
        private readonly SerializerInterface $serializer
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getBody(): string
    {
        return $this->serializer->serialize($this->data);
    }

    /**
     * @inheritDoc
     *
     * @return array<string, mixed>
     */
    public function getProperties(): array
    {
        $properties = $this->get(self::PROPERTY_KEY, []);

        return is_array($properties) ? $properties : [];
    }

    /**
     * @inheritDoc
     */
    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function set(string $key, mixed $value): static
    {
        $this->data[$key] = $value;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return array_key_exists($key, $this->data) ? $this->data[$key] : $default;
    }

    /**
     * @inheritDoc
     */
    public function all(): array
    {
        return $this->data;
    }

    /**
     * @inheritDoc
     */
    public function has(string $key): bool
    {
        return array_key_exists($key, $this->data);
    }

    /**
     * @inheritDoc
     */
    public function reset(): static
    {
        $this->data = [];

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function populate(string $body): static
    {
        $decoded = $this->serializer->unserialize($body);

        if (!is_array($decoded)) {
            throw new InvalidArgumentException(
                'Message body did not decode to an array; refusing to populate the envelope.'
            );
        }

        return $this->reset()->setData($decoded);
    }
}
