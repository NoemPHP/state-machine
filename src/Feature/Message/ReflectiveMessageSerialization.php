<?php

declare(strict_types=1);

namespace Noem\State\Feature\Message;

/**
 * Optional trait for reflection-based serialization
 * Use for simple Message classes where automatic serialization is sufficient
 */
trait ReflectiveMessageSerialization
{
    public function jsonSerialize(): mixed
    {
        $reflection = new \ReflectionClass($this);
        $properties = [];

        foreach ($reflection->getProperties(\ReflectionProperty::IS_PUBLIC) as $property) {
            if ($property->getName() === 'correlationId') {
                continue;
            }
            $properties[$property->getName()] = $property->getValue($this);
        }

        return [
            'correlationId' => $this->correlationId,
            'type' => static::class,
            'data' => $properties
        ];
    }

    protected static function fromData(mixed $data, ?string $correlationId): static
    {
        if (!is_array($data)) {
            throw new \InvalidArgumentException(
                'ReflectiveMessageSerialization requires array payload. Got: ' . gettype($data)
            );
        }

        $reflection = new \ReflectionClass(static::class);
        $constructor = $reflection->getConstructor();

        if (!$constructor) {
            return new static($correlationId);
        }

        $args = [];
        foreach ($constructor->getParameters() as $param) {
            if ($param->getName() === 'correlationId') {
                $args[] = $correlationId;
            } else {
                $args[] = $data[$param->getName()] ?? null;
            }
        }

        return new static(...$args);
    }
}
