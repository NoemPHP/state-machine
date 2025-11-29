<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Core\RegionBuilder\Fixtures;

use Noem\State\Feature\Feature;
use Noem\State\Feature\RequiresFeature;
use Noem\State\Middleware\ChainMail;

/**
 * Test fixture: Feature with multiple dependencies
 */
#[RequiresFeature(BaseTestFeature::class)]
#[RequiresFeature(AnotherTestFeature::class)]
class MultiDependencyTestFeature implements Feature
{
    public function __invoke(ChainMail $chainMail): void
    {
    }
}
