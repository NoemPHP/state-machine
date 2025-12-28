<?php

declare(strict_types=1);

namespace Noem\State\Feature\Abilities;

/**
 * Wrapper for ability invocations that supports both generator and message patterns
 *
 * Allows callers to either:
 * - yield from $this->abilities(...) - wait for completion (generator pattern)
 * - $this->abilities(...)->then(...) - register callback (message pattern)
 *
 * Implements IteratorAggregate so it can be used with yield from.
 */
final class AbilityInvocation implements \IteratorAggregate
{
    public function __construct(
        private readonly \Generator $generator,
        private readonly AbilityMessage $message,
    ) {
    }

    /**
     * Register a callback to be called when the response arrives
     *
     * Supports message-based async pattern: $this->abilities(...)->then(...)
     */
    public function then(callable $handler): self
    {
        $this->message->then($handler);
        return $this;
    }

    /**
     * Get the iterator for yield from support
     *
     * Supports generator-based async pattern: yield from $this->abilities(...)
     */
    public function getIterator(): \Traversable
    {
        return $this->generator;
    }

    /**
     * Get the underlying message
     *
     * Allows access to message properties if needed
     */
    public function getMessage(): AbilityMessage
    {
        return $this->message;
    }

    /**
     * Get correlation ID from the underlying message
     *
     * Allows accessing correlation ID directly on invocation
     */
    public function __get(string $name): mixed
    {
        // Proxy correlationId access to the underlying message
        if ($name === 'correlationId') {
            return $this->message->correlationId();
        }

        throw new \Error("Undefined property: " . self::class . "::\${$name}");
    }
}
