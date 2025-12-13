<?php

declare(strict_types=1);

namespace Noem\State;

abstract class MetaType
{
    private static array $instances = [];

    private function __construct()
    {
    }

    public static function get(): static
    {
        $class = static::class;
        if (!isset(self::$instances[$class])) {
            self::$instances[$class] = new static();
        }

        return self::$instances[$class];
    }

    public function is(string|MetaType $type): bool
    {
        if ($type instanceof MetaType) {
            return $this === $type;
        }
        return is_a($this, $type);
    }

    // Prevent cloning of the instance
    private function __clone()
    {
        throw new \RuntimeException("Cannot clone an instance of " . static::class);
    }

    // Prevent unserialization of the instance
    public function __wakeup()
    {
        throw new \RuntimeException("Cannot unserialize an instance of " . static::class);
    }

    public function __toString(): string
    {
        return static::class;
    }

    public static function key(): string
    {
        return static::get()->__toString();
    }
}
