<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Core\RegionBuilder\Fixtures;

use Noem\State\Feature\Feature;
use Noem\State\Feature\RequiresFeature;
use Noem\State\Middleware\ChainMail;

/**
 * Test fixture for self-dependency: CircularFeatureSelf -> CircularFeatureSelf
 */
#[RequiresFeature(CircularFeatureSelf::class)]
class CircularFeatureSelf implements Feature
{
    public function __invoke(ChainMail $chainMail): void
    {
        // Minimal implementation for testing
    }
}
