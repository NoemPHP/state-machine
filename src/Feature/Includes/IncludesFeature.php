<?php

declare(strict_types=1);

namespace Noem\State\Feature\Includes;

use Noem\State\Feature\Feature;
use Noem\State\Feature\Includes\Chains\LoadFile;
use Noem\State\Middleware\ChainMail;

/**
 * Provides synchronous file loading infrastructure through the LoadFile chain.
 *
 * Enables features to load application files during build and bootstrap,
 * with support for:
 * - File loading from filesystem
 * - Middleware-based customization (search paths, caching, transforms)
 * - Depth limiting to prevent infinite recursion
 * - Error handling for unreadable files
 *
 * The LoadFile chain executes middleware in LIFO order, allowing features
 * to customize file loading behavior through search paths, caching,
 * and path transformations.
 */
class IncludesFeature implements Feature
{
    /**
     * Register the LoadFile chain in ChainMail container.
     *
     * @param ChainMail $chainMail The dependency injection container
     */
    public function __invoke(ChainMail $chainMail): void
    {
        $chainMail->supply(
            fn(): LoadFile => new LoadFile(),
        );
    }
}
