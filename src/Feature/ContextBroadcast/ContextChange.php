<?php

declare(strict_types=1);

namespace Noem\State\Feature\ContextBroadcast;

/**
 * Event representing a context change in a Region
 *
 * Emitted whenever $this->set() is called within a state callback,
 * providing complete change context for external observers.
 */
readonly class ContextChange implements \JsonSerializable
{
    public function __construct(
        public string $path,
        public string $key,
        public mixed $value,
        public mixed $previousValue,
        public float $timestamp,
    ) {
    }

    /**
     * Serialize to JSON with type field for polymorphic deserialization
     *
     * @return array<string, mixed>
     */
    #[\Override]
    public function jsonSerialize(): array
    {
        return [
            'type' => self::class,
            'path' => $this->path,
            'key' => $this->key,
            'value' => $this->value,
            'previousValue' => $this->previousValue,
            'timestamp' => $this->timestamp,
        ];
    }
}
