<?php

declare(strict_types=1);

namespace Noem\State;

/**
 * Global registry for Region-to-Runtime mappings
 *
 * Uses WeakMap for automatic garbage collection when regions are destroyed.
 */
final class RuntimeRegistry
{
    /**
     * @var \WeakMap<Region, Runtime>
     */
    private static ?\WeakMap $runtimes = null;

    /**
     * Register a Region-to-Runtime mapping
     */
    public static function register(Region $region, Runtime $runtime): void
    {
        self::getRegistry()[$region] = $runtime;
    }

    /**
     * Get the Runtime for a Region
     *
     * @return Runtime|null Runtime if registered, null otherwise
     */
    public static function get(Region $region): ?Runtime
    {
        return self::getRegistry()[$region] ?? null;
    }

    /**
     * Unregister a Region
     */
    public static function unregister(Region $region): void
    {
        unset(self::getRegistry()[$region]);
    }

    /**
     * Get or create the registry
     *
     * @return \WeakMap<Region, Runtime>
     */
    private static function getRegistry(): \WeakMap
    {
        if (self::$runtimes === null) {
            self::$runtimes = new \WeakMap();
        }

        return self::$runtimes;
    }
}
