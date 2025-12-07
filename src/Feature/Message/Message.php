<?php

declare(strict_types=1);

namespace Noem\State\Feature\Message;

/**
 * Abstract message with UUID correlation and promise-like API
 *
 * Message objects must be fully JSON-serializable for persistence and transmission.
 * Subclasses implement their own serialization logic for full control.
 */
abstract class Message implements \JsonSerializable
{
    protected readonly string $correlationId;

    /**
     * @var callable[] Registered response handlers
     */
    private array $replyHandlers = [];

    protected function __construct(?string $correlationId = null)
    {
        $this->correlationId = $correlationId ?? $this->generateId();
    }

    private function generateId(): string
    {
        return sprintf(
            '%08x-%04x-%04x-%04x-%012x',
            random_int(0, 0xFFFFFFFF),
            random_int(0, 0xFFFF),
            random_int(0, 0xFFFF),
            random_int(0, 0xFFFF),
            random_int(0, 0xFFFFFFFFFFFF)
        );
    }

    /**
     * Get correlation ID for matching request/response
     */
    final public function correlationId(): string
    {
        return $this->correlationId;
    }

    /**
     * Register response handler (promise-like API)
     */
    final public function then(callable $handler): self
    {
        $this->replyHandlers[] = $handler;
        return $this;
    }

    /**
     * Deliver response to registered handlers
     */
    final public function deliverResponse(Message $response): void
    {
        foreach ($this->replyHandlers as $handler) {
            $handler($response);  // Call handler with response Message object
        }
    }

    /**
     * Check if this message replies to given request
     */
    final public function repliesTo(Message $request): bool
    {
        return $this->correlationId === $request->correlationId();
    }

    /**
     * Create a correlated response message
     *
     * @template T of Message
     * @param class-string<T> $responseClass
     * @param mixed $responsePayload
     * @return T
     */
    final public function createResponse(string $responseClass, mixed $responsePayload): Message
    {
        // Type check that response class exists and extends Message
        if (!class_exists($responseClass) || !is_subclass_of($responseClass, self::class)) {
            throw new \InvalidArgumentException(
                "Response class must exist and extend " . self::class
            );
        }

        return $responseClass::fromData($responsePayload, $this->correlationId);
    }

    // Abstract methods - subclasses MUST implement
    abstract public function jsonSerialize(): mixed;

    abstract protected static function fromData(mixed $data, ?string $correlationId): static;

    /**
     * Reconstruct Message from JSON-decoded array
     *
     * Expected format:
     * [
     *   'type' => 'Fully\\Qualified\\ClassName',  // Optional if $fqcn provided
     *   'correlationId' => 'uuid-string',
     *   'data' => [...] // Message-specific payload
     * ]
     *
     * @param array $data JSON-decoded message data
     * @param string|null $fqcn Optional fully-qualified class name (overrides $data['type'])
     * @return static Reconstructed message instance
     */
    public static function fromJson(array $data, ?string $fqcn = null): static
    {
        $fqcn = $fqcn ?? ($data['type'] ?? null);

        if ($fqcn && is_subclass_of($fqcn, self::class)) {
            return $fqcn::fromData($data['data'] ?? null, $data['correlationId'] ?? null);
        }

        // Fallback to anonymous class
        return self::createAnonymousMessage($data);
    }

    private static function createAnonymousMessage(array $data): static
    {
        return new class ($data['data'] ?? new \stdClass(), $data['correlationId'] ?? null) extends Message {
            public function __construct(
                public readonly object $data,
                ?string $correlationId = null
            ) {
                parent::__construct($correlationId);
            }

            public function jsonSerialize(): mixed
            {
                return [
                    'correlationId' => $this->correlationId,
                    'type' => 'AnonymousMessage',
                    'data' => $this->data
                ];
            }

            protected static function fromData(mixed $data, ?string $correlationId): static
            {
                return new self($data, $correlationId);
            }
        };
    }
}
