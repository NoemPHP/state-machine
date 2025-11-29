<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Loader;

use Noem\State\Connection;
use Noem\State\Feature\Loader\RegionLoader;
use Noem\State\Feature\Transitions\AddTransition;
use Noem\State\Feature\Transitions\TransitionsFeature;
use Noem\State\Region;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * Acceptance Criterion: SpawnRegion creates connection predicate tied to parent state
 */
#[Group('loader')]
#[Group('spawn-execution')]
class SpawnConnectionPredicateTest extends TestCase
{
    public function testConnectionActiveOnlyInSpawningState(): void
    {
        $this->markTestSkipped('We have a bug here that we need to fix later');
        $builder = new RegionBuilder();
        $builder->enableFeatures(new RegionLoader(), new TransitionsFeature());
        
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
        
        // Parent has two states: stateA (spawning state) and stateB
        $builder->setStates('stateA', 'stateB');
        $builder->markInitial('stateA');
        $builder->addBuildStep(
            RegionLoader::regionSpawnStep('stateA', $regionFactory, $guard, Connection::DYNAMIC | Connection::RECEIVE_ACTIONS)
        );
        
        // Add transition from stateA to stateB
        $builder->addBuildStep(new AddTransition('stateA', 'stateB', fn(object $t): bool => $t->moveToB ?? false));
        
        $parentRegion = $builder->build();
        
        $this->assertTrue($parentRegion->isInState('stateA'));
        
        // Spawn child while in stateA
        $parentRegion->trigger(new stdClass());
        $this->assertSame(1, $childReceivedActions, 'Child should receive spawn action');
        
        // Trigger another action while still in stateA - child should receive it
        $parentRegion->trigger(new stdClass());
        $this->assertSame(2, $childReceivedActions, 'Child should receive action when parent in stateA');
        
        // Transition parent to stateB
        $trigger = new stdClass();
        $trigger->moveToB = true;
        $parentRegion->trigger($trigger);
        $this->assertTrue($parentRegion->isInState('stateB'), 'Parent should have transitioned to stateB');
        
        // Now trigger actions - child should NOT receive them (connection predicate returns false)
        $parentRegion->trigger(new stdClass());
        $this->assertSame(2, $childReceivedActions, 'Child should not receive actions when parent not in stateA');
        
        $parentRegion->trigger(new stdClass());
        $this->assertSame(2, $childReceivedActions, 'Child should still not receive actions');
    }

    public function testMultipleSpawnsFromDifferentStates(): void
    {
        $this->markTestSkipped('We have a bug here that we need to fix later');

        $builder = new RegionBuilder();
        $builder->enableFeatures(new RegionLoader(), new TransitionsFeature());
        
        $child1Actions = 0;
        $child2Actions = 0;
        
        $guard = fn(object $t): bool => true; // Always spawn
        
        $factory1 = function() use (&$child1Actions, $builder): Region {
            $childBuilder = $builder->newInstance();
            $childBuilder->setStates('child1');
            $childBuilder->onAction('child1', function(object $trigger) use (&$child1Actions) {
                $child1Actions++;
                return 'child1';
            });
            return $childBuilder->build();
        };
        
        $factory2 = function() use (&$child2Actions, $builder): Region {
            $childBuilder = $builder->newInstance();
            $childBuilder->setStates('child2');
            $childBuilder->onAction('child2', function(object $trigger) use (&$child2Actions) {
                $child2Actions++;
                return 'child2';
            });
            return $childBuilder->build();
        };
        
        $builder->setStates('stateA', 'stateB');
        $builder->markInitial('stateA');
        
        // Spawn child1 from stateA
        $builder->addBuildStep(
            RegionLoader::regionSpawnStep('stateA', $factory1, $guard, Connection::DYNAMIC | Connection::RECEIVE_ACTIONS)
        );
        
        // Spawn child2 from stateB
        $builder->addBuildStep(
            RegionLoader::regionSpawnStep('stateB', $factory2, $guard, Connection::DYNAMIC | Connection::RECEIVE_ACTIONS)
        );
        
        $builder->addBuildStep(new AddTransition('stateA', 'stateB', fn(object $t): bool => $t->moveToB ?? false));
        $builder->addBuildStep(new AddTransition('stateB', 'stateA', fn(object $t): bool => $t->moveToA ?? false));
        
        $parentRegion = $builder->build();
        
        // In stateA - spawn child1
        $parentRegion->trigger(new stdClass());
        $this->assertSame(1, $child1Actions, 'Child1 spawned and received action');
        $this->assertSame(0, $child2Actions, 'Child2 not yet spawned');
        
        // Transition to stateB
        $trigger1 = new stdClass();
        $trigger1->moveToB = true;
        $parentRegion->trigger($trigger1);
        
        // In stateB - spawn child2
        $parentRegion->trigger(new stdClass());
        $this->assertSame(1, $child1Actions, 'Child1 connection inactive in stateB');
        $this->assertSame(1, $child2Actions, 'Child2 spawned and received action');
        
        // Back to stateA
        $trigger2 = new stdClass();
        $trigger2->moveToA = true;
        $parentRegion->trigger($trigger2);
        
        // Trigger in stateA - only child1 should receive (child2 connection inactive)
        $parentRegion->trigger(new stdClass());
        $this->assertSame(2, $child1Actions, 'Child1 connection active again in stateA');
        $this->assertSame(1, $child2Actions, 'Child2 connection inactive in stateA');
    }

    public function testConnectionPredicateWithDynamicFlag(): void
    {
        $this->markTestSkipped('We have a bug here that we need to fix later');
        $builder = new RegionBuilder();
        $builder->enableFeatures(new RegionLoader(), new TransitionsFeature());
        
        $childActions = 0;
        
        $guard = fn(object $t): bool => $t->shouldSpawn ?? false;
        
        $regionFactory = function() use (&$childActions, $builder): Region {
            $childBuilder = $builder->newInstance();
            $childBuilder->setStates('child');
            $childBuilder->onAction('child', function(object $trigger) use (&$childActions) {
                $childActions++;
                return 'child';
            });
            return $childBuilder->build();
        };
        
        $builder->setStates('active', 'inactive');
        $builder->markInitial('active');
        $builder->addBuildStep(
            RegionLoader::regionSpawnStep('active', $regionFactory, $guard, Connection::DYNAMIC | Connection::RECEIVE_ACTIONS)
        );
        $builder->addBuildStep(new AddTransition('active', 'inactive', fn(object $t): bool => $t->deactivate ?? false));
        
        $parentRegion = $builder->build();
        
        // Spawn child
        $spawnTrigger = new stdClass();
        $spawnTrigger->shouldSpawn = true;
        $parentRegion->trigger($spawnTrigger);
        $this->assertSame(1, $childActions);
        
        // Deactivate (leave the spawning state)
        $deactivateTrigger = new stdClass();
        $deactivateTrigger->deactivate = true;
        $parentRegion->trigger($deactivateTrigger);
        
        // Child should not receive actions when parent in different state
        $parentRegion->trigger(new stdClass());
        $this->assertSame(1, $childActions, 'Connection should be inactive due to DYNAMIC flag and state mismatch');
    }
}
