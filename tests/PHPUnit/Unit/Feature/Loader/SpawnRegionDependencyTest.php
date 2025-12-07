<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Loader;

use Noem\State\Chains\ConnectedRegions;
use Noem\State\Feature\Loader\LoaderChains\SpawnRegion;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Acceptance Criterion: SpawnRegion chain requires ConnectedRegions dependency
 */
#[Group('loader')]
#[Group('loader-chains')]
class SpawnRegionDependencyTest extends TestCase
{
    public function testSpawnRegionRequiresConnectedRegions(): void
    {
        $connectedRegions = new ConnectedRegions();
        $spawnRegion = new SpawnRegion($connectedRegions);

        $reflection = new ReflectionClass($spawnRegion);
        $property = $reflection->getProperty('connectedRegions');

        $value = $property->getValue($spawnRegion);

        $this->assertInstanceOf(
            ConnectedRegions::class,
            $value,
            'SpawnRegion should store ConnectedRegions dependency'
        );

        $this->assertSame(
            $connectedRegions,
            $value,
            'SpawnRegion should store the exact ConnectedRegions instance passed to constructor'
        );
    }
}
