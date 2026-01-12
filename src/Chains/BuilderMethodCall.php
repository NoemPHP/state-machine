<?php

declare(strict_types=1);

namespace Noem\State\Chains;

use Noem\State\Middleware\Chain;

/**
 * Chain for intercepting RegionBuilder magic method calls
 *
 * Allows features to inject custom methods into RegionBuilder via __call().
 * Features hook into this chain to provide methods like presentation(), registerInteraction(), etc.
 */
final class BuilderMethodCall extends Chain
{
}
