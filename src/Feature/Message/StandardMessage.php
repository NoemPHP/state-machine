<?php

declare(strict_types=1);

namespace Noem\State\Feature\Message;

/**
 * Generic message wrapper for JSON payloads without registered Message subclasses
 *
 * StandardMessage provides a fallback implementation when Message.fromJson() encounters
 * unknown or missing type fields, enabling graceful handling of unregistered message types.
 */
class StandardMessage extends Message
{
    /**
     * @param array<string, mixed> $data Arbitrary key-value data
     * @param string|null $correlationId Optional correlation ID for existing messages
     */
    public function __construct(
        private readonly array $data = [],
        ?string $correlationId = null
    ) {
        parent::__construct($correlationId);
    }

    /**
     * Get value from data array
     *
     * @param string $key Data key
     * @param mixed $default Default value if key not found
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    /**
     * Serialize message to JSON
     *
     * @return array{correlationId: string, type: string, data: array<string, mixed>}
     */
    #[\Override]
    public function jsonSerialize(): mixed
    {
        return [
            'correlationId' => $this->correlationId,
            'type' => 'StandardMessage',
            'data' => $this->data,
        ];
    }

    /**
     * Reconstruct StandardMessage from data array
     *
     * @param mixed $data Message data (array expected)
     * @param string|null $correlationId Correlation ID
     * @return static
     */
    #[\Override]
    protected static function fromData(mixed $data, ?string $correlationId): static
    {
        // Handle null or missing data
        if ($data === null) {
            return new static([], $correlationId);
        }

        // Ensure data is array
        if (!is_array($data)) {
            $data = ['data' => $data];
        }

        return new static($data, $correlationId);
    }
}
