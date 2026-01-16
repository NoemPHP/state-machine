<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Core\RegionBuilder\Fixtures;

use Noem\State\Feature\Feature;
use Noem\State\Feature\RequiresFeature;
use Noem\State\Middleware\ChainMail;

/**
 * Test fixture for indirect circular dependency: C -> A (completes A -> B -> C -> A cycle)
 */
#[RequiresFeature(CircularFeatureIndirectA::class)]
class CircularFeatureC implements Feature
{
    public function __invoke(ChainMail $chainMail): void
    {
        // Minimal implementation for testing
    }
}
