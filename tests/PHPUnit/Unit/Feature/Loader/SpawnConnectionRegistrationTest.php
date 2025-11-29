<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Loader;

use Noem\State\Feature\Loader\RegionLoader;
use Noem\State\Region;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * Acceptance Criterion: SpawnRegion adds connection to ConnectedRegions
 */
#[Group('loader')]
#[Group('spawn-execution')]
class SpawnConnectionRegistrationTest extends TestCase
{
    public function testSpawnedRegionConnectionIsRegistered(): void
    {
        $builder = new RegionBuilder();
        $builder->enableFeatures(new RegionLoader());
        
        $childReceivedActions = 0;
        $spawnCount = 0;
        
        $guard = function(object $t) use (&$spawnCount): bool {
            $spawnCount++;
            return $spawnCount === 1; // Only spawn once
        };
        
        $regionFactory = function() use (&$childReceivedActions, $builder): Region {
            $childBuilder = $builder->newInstance();
            $childBuilder->setStates('child');
            $childBuilder->onAction('child', function(object $trigger) use (&$childReceivedActions) {
                $childReceivedActions++;
                return 'child';
            });
            return $childBuilder->build();
        };
        
        $builder->setStates('parent');
        $builder->addBuildStep(
            RegionLoader::regionSpawnStep('parent', $regionFactory, $guard)
        );
        
        $parentRegion = $builder->build();
        
        // Before spawn, no connections exist
        $this->assertSame(0, $childReceivedActions);
        
        // Trigger to spawn - this should register the connection
        $parentRegion->trigger(new stdClass());
        $this->assertSame(1, $childReceivedActions, 'Child should receive spawn action, proving connection registered');
        
        // Subsequent trigger should reach child through registered connection
        $parentRegion->trigger(new stdClass());
        $this->assertSame(2, $childReceivedActions, 'Child should receive action through registered connection');
    }

    public function testMultipleSpawnsRegisterMultipleConnections(): void
    {
        $builder = new RegionBuilder();
        $builder->enableFeatures(new RegionLoader());
        
        $childActions = [];
        
        $guard = fn(object $t): bool => true; // Always spawn
        
        $regionFactory = function() use (&$childActions, $builder): Region {
            $index = count($childActions);
            $childActions[$index] = 0;
            
            $childBuilder = $builder->newInstance();
            $childBuilder->setStates('child');
            $childBuilder->onAction('child', function(object $trigger) use ($index, &$childActions) {
                $childActions[$index]++;
                return 'child';
            });
            return $childBuilder->build();
        };
        
        $builder->setStates('parent');
        $builder->addBuildStep(
            RegionLoader::regionSpawnStep('parent', $regionFactory, $guard)
        );
        
        $parentRegion = $builder->build();
        
        // Spawn three regions - each receives all subsequent actions
        $parentRegion->trigger(new stdClass()); // Spawns child[0], child[0]=1
        $parentRegion->trigger(new stdClass()); // Spawns child[1], child[0]=2, child[1]=1
        $parentRegion->trigger(new stdClass()); // Spawns child[2], child[0]=3, child[1]=2, child[2]=1
        
        // Each child has received actions based on when it was spawned
        $this->assertSame(3, $childActions[0], 'First child received 3 actions (spawn + 2 subsequent)');
        $this->assertSame(2, $childActions[1], 'Second child received 2 actions (spawn + 1 subsequent)');
        $this->assertSame(1, $childActions[2], 'Third child received 1 action (spawn)');
        
        // Trigger parent again - all three connections should forward the action
        $parentRegion->trigger(new stdClass());
        
        $this->assertSame(4, $childActions[0], 'First connection still active');
        $this->assertSame(3, $childActions[1], 'Second connection still active');
        $this->assertSame(2, $childActions[2], 'Third connection still active');
    }

    public function testConnectionRegistrationPersistsAcrossActions(): void
    {
        $builder = new RegionBuilder();
        $builder->enableFeatures(new RegionLoader());
        
        $childReceivedActions = 0;
        $spawnCount = 0;
        
        $guard = function(object $t) use (&$spawnCount): bool {
            $spawnCount++;
            return $spawnCount === 1;
        };
        
        $regionFactory = function() use (&$childReceivedActions, $builder): Region {
            $childBuilder = $builder->newInstance();
            $childBuilder->setStates('child');
            $childBuilder->onAction('child', function(object $trigger) use (&$childReceivedActions) {
                $childReceivedActions++;
                return 'child';
            });
            return $childBuilder->build();
        };
        
        $builder->setStates('parent');
        $builder->addBuildStep(
            RegionLoader::regionSpawnStep('parent', $regionFactory, $guard)
        );
        
        $parentRegion = $builder->build();
        
        // Spawn
        $parentRegion->trigger(new stdClass());
        $this->assertSame(1, $childReceivedActions);
        
        // Multiple subsequent actions should all reach the child
        for ($i = 0; $i < 5; $i++) {
            $parentRegion->trigger(new stdClass());
        }
        
        $this->assertSame(6, $childReceivedActions, 'Connection persists across multiple actions');
    }
}
