<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Chains\Params\BuildParams;

use Noem\State\Chains\Params\BuildParams;
use Noem\State\Chains\Params\Config\ConfigAccessor;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: BuildParams caches accessor instances per class to avoid redundant instantiation
 */
#[Group('config-accessor'), Group('build-params-infrastructure')]
class CachesAccessorInstancesTest extends TestCase
{
    public function testCachesAccessorInstances(): void
    {
        $builder = new RegionBuilder();
        $config = [];
        $params = new BuildParams($builder, $config);

        $accessor1 = $params->config(CachedTestAccessor::class);
        $accessor2 = $params->config(CachedTestAccessor::class);

        $this->assertSame($accessor1, $accessor2, 'Should return same instance on repeated calls');
    }
}

class CachedTestAccessor extends ConfigAccessor
{
}
