<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Loader;

use Noem\State\Feature\Loader\RegionLoader;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: RegionLoader passes through build when no loader args present
 */
#[Group('loader')]
#[Group('builder-integration')]
class BuilderPassthroughTest extends TestCase
{
    public function testPassesThroughBuildWhenNoLoaderArgs(): void
    {
        $builder = new RegionBuilder();
        $builder->enableFeatures(new RegionLoader());
        
        // Build without loader args - should use normal builder flow
        $region = $builder
            ->setStates('idle', 'active')
            ->markInitial('idle')
            ->build();
        
        $this->assertInstanceOf(\Noem\State\Region::class, $region);
        $this->assertTrue($region->isInState('idle'));
    }
}
