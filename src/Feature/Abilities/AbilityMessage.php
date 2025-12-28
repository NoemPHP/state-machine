<?php

declare(strict_types=1);

namespace Noem\State\Feature\Abilities;

use Noem\State\Feature\Message\Message;

/**
 * Message subclass for ability invocations
 *
 * Inherits correlation infrastructure from Message, enabling request-response
 * pattern through MessageFeature. Carries ability invocation data including
 * name, parameters, and optional definition metadata.
 *
 * @property-read string $correlationId Inherited from Message, exposed for testing
 */
class AbilityMessage extends Message
{
    /**
     * @param string $abilityName Name of ability being invoked
     * @param mixed $parameters Invocation parameters (arbitrary structure)
     * @param AbilityDefinition|null $definition Optional ability definition metadata
     * @param string|null $correlationId Optional explicit correlation ID for responses
     */
    private function __construct(
        public readonly string $abilityName,
        public readonly mixed $parameters,
        public readonly ?AbilityDefinition $definition = null,
        ?string $correlationId = null,
    ) {
        parent::__construct($correlationId);
    }

    /**
     * Expose correlationId from parent for public access
     */
    public function __get(string $name): mixed
    {
        if ($name === 'correlationId') {
            return $this->correlationId;
        }
        throw new \Error("Undefined property: " . static::class . "::\$$name");
    }

    /**
     * Create AbilityMessage from serialized data
     *
     * @param mixed $data Serialized message data (can be array or nested with 'data' key)
     * @param string|null $correlationId Optional correlation ID
     * @return static
     */
    protected static function fromData(mixed $data, ?string $correlationId): static
    {
        if (!is_array($data)) {
            throw new \InvalidArgumentException('AbilityMessage data must be an array');
        }

        // Support both flat and nested data structures for backwards compatibility
        $abilityName = $data['abilityName'] ?? $data['data']['abilityName'] ?? null;
        $parameters = $data['parameters'] ?? $data['data']['parameters'] ?? null;
        $definition = $data['definition'] ?? $data['data']['definition'] ?? null;

        if ($abilityName === null) {
            throw new \InvalidArgumentException('Missing abilityName');
        }

        return new self(
            abilityName: $abilityName,
            parameters: $parameters,
            definition: $definition,
            correlationId: $correlationId,
        );
    }

    /**
     * Serialize to JSON
     *
     * @return array{type: string, correlationId: string, abilityName: string, parameters: mixed}
     */
    public function jsonSerialize(): mixed
    {
        return [
            'type' => self::class,
            'correlationId' => $this->correlationId,
            'abilityName' => $this->abilityName,
            'parameters' => $this->parameters,
            // Exclude definition - not serializable (contains callables)
        ];
    }

    /**
     * Create new AbilityMessage instance
     *
     * @param string $abilityName Name of ability to invoke
     * @param mixed $parameters Invocation parameters
     * @param AbilityDefinition|null $definition Optional ability definition
     * @param string|null $correlationId Optional correlation ID
     * @return self
     */
    public static function create(
        string $abilityName,
        mixed $parameters = null,
        ?AbilityDefinition $definition = null,
        ?string $correlationId = null
    ): self {
        return new self($abilityName, $parameters, $definition, $correlationId);
    }
}
