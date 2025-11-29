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
 * Acceptance Criterion: SpawnRegion evaluates guard predicate with trigger payload
 */
#[Group('loader')]
#[Group('spawn-execution')]
class SpawnGuardEvaluationTest extends TestCase
{
    public function testEvaluatesGuardPredicateWithTriggerPayload(): void
    {
        $builder = new RegionBuilder();
        $builder->enableFeatures(new RegionLoader());
        
        $receivedPayload = null;
        
        $guard = function(object $t) use (&$receivedPayload): bool {
            $receivedPayload = $t;
            return false; // Don't spawn
        };
        
        $regionFactory = fn(): Region => $builder->newInstance()
            ->setStates('child')
            ->build();
        
        $builder->setStates('parent');
        $builder->addBuildStep(
            RegionLoader::regionSpawnStep('parent', $regionFactory, $guard)
        );
        
        $region = $builder->build();
        
        // Trigger with a specific payload
        $payload = new stdClass();
        $payload->testData = 'hello';
        $region->trigger($payload);
        
        // Guard should have received the exact payload
        $this->assertSame($payload, $receivedPayload, 'Guard should receive the trigger payload');
        $this->assertSame('hello', $receivedPayload->testData);
    }

    public function testGuardIsEvaluatedWithEachTrigger(): void
    {
        $builder = new RegionBuilder();
        $builder->enableFeatures(new RegionLoader());
        
        $receivedPayloads = [];
        
        $guard = function(object $t) use (&$receivedPayloads): bool {
            $receivedPayloads[] = $t;
            return false;
        };
        
        $regionFactory = fn(): Region => $builder->newInstance()
            ->setStates('child')
            ->build();
        
        $builder->setStates('parent');
        $builder->addBuildStep(
            RegionLoader::regionSpawnStep('parent', $regionFactory, $guard)
        );
        
        $region = $builder->build();
        
        // Trigger with different payloads
        $payload1 = new stdClass();
        $payload1->id = 1;
        $payload2 = new stdClass();
        $payload2->id = 2;
        $payload3 = new stdClass();
        $payload3->id = 3;
        
        $region->trigger($payload1);
        $region->trigger($payload2);
        $region->trigger($payload3);
        
        // Guard should have been called with each payload
        $this->assertCount(3, $receivedPayloads);
        $this->assertSame($payload1, $receivedPayloads[0]);
        $this->assertSame($payload2, $receivedPayloads[1]);
        $this->assertSame($payload3, $receivedPayloads[2]);
    }

    public function testGuardReturnValueDeterminesSpawning(): void
    {
        $builder = new RegionBuilder();
        $builder->enableFeatures(new RegionLoader());
        
        $shouldSpawn = false;
        $spawnCount = 0;
        
        $guard = function(object $t) use (&$shouldSpawn): bool {
            return $shouldSpawn;
        };
        
        $regionFactory = function() use (&$spawnCount, $builder): Region {
            $spawnCount++;
            return $builder->newInstance()->setStates('child')->build();
        };
        
        $builder->setStates('parent');
        $builder->addBuildStep(
            RegionLoader::regionSpawnStep('parent', $regionFactory, $guard)
        );
        
        $region = $builder->build();
        
        // Guard returns false - should not spawn
        $shouldSpawn = false;
        $region->trigger(new stdClass());
        $this->assertSame(0, $spawnCount, 'Should not spawn when guard returns false');
        
        // Guard returns true - should spawn
        $shouldSpawn = true;
        $region->trigger(new stdClass());
        $this->assertSame(1, $spawnCount, 'Should spawn when guard returns true');
    }
}
