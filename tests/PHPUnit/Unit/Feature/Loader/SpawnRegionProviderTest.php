<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Loader;

use Noem\State\Chains\ConnectedRegions;
use Noem\State\Feature\Loader\LoaderChains\SpawnRegion;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Acceptance Criterion: SpawnRegion chain uses provider method for spawn logic
 */
#[Group('loader')]
#[Group('loader-chains')]
class SpawnRegionProviderTest extends TestCase
{
    public function testSpawnRegionUsesProviderMethod(): void
    {
        $connectedRegions = new ConnectedRegions();
        $spawnRegion = new SpawnRegion($connectedRegions);

        $reflection = new ReflectionClass($spawnRegion);

        // Verify the provider method exists
        $this->assertTrue(
            $reflection->hasMethod('provider'),
            'SpawnRegion should have a provider method'
        );

        $providerMethod = $reflection->getMethod('provider');

        // Verify it's private (implementation detail)
        $this->assertTrue(
            $providerMethod->isPrivate(),
            'Provider method should be private'
        );

        // Verify the provider method signature accepts SpawnRegionParams
        $parameters = $providerMethod->getParameters();
        $this->assertCount(1, $parameters, 'Provider method should accept one parameter');
        $this->assertEquals(
            'params',
            $parameters[0]->getName(),
            'Provider method parameter should be named params'
        );
    }
}
