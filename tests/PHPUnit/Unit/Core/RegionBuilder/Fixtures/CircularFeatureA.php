<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Core\RegionBuilder\Fixtures;

use Noem\State\Feature\Feature;
use Noem\State\Feature\RequiresFeature;
use Noem\State\Middleware\ChainMail;

/**
 * Test fixture for circular dependency detection: A -> B
 */
#[RequiresFeature(CircularFeatureB::class)]
class CircularFeatureA implements Feature
{
    public function __invoke(ChainMail $chainMail): void
    {
        // Minimal implementation for testing
    }
}
