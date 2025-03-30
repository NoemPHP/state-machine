<?php

declare(strict_types=1);

namespace Noem\State;

abstract class MetaType
{
    private static ?self $instance = null;

    private function __construct()
    {
    }

    public static function get(): static
    {
        if (self::$instance === null) {
            self::$instance = new static();
        }

        return self::$instance;
    }

    public function is(string|MetaType $type): bool
    {
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
