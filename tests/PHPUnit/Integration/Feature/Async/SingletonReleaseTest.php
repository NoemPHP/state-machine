<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Integration\Feature\Async;

use Noem\State\Feature\Async\AsyncConfig;
use Noem\State\Feature\Async\AsyncFeature;
use Noem\State\Feature\ExtendedState\ExtendedState;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: New task can start after previous singleton task finishes
 * Intent: Releases lock when task completes, allowing subsequent triggers to create new tasks
 */
#[Group('async'), Group('integration'), Group('singleton')]
class SingletonReleaseTest extends TestCase
{
    public function testNewTaskCanStartAfterSingletonTaskFinishes(): void
    {
        $builder = new RegionBuilder();
        $builder->enableFeatures(new ExtendedState(), new AsyncFeature());

        $taskStarts = 0;

        $callback = function (object $trigger) use (&$taskStarts) {
            $taskStarts++;
            yield;
            yield;
            return 'done';
        };

        $config = new AsyncConfig(singleton: true);

        $builder
            ->setStates('active')
            ->onAction('active', $callback, $config);

        $region = $builder->build();

        // Start first task (completes in one tick with NORMAL priority = 5 steps)
        $region->trigger(new \stdClass());
        $this->assertEquals(1, $taskStarts, 'First task should start');

        // Task completed, singleton lock should be released
        // Next trigger should create new task
        $region->trigger(new \stdClass());
        $this->assertEquals(2, $taskStarts, 'Second task should start after first completes');

        // Third task after second completes
        $region->trigger(new \stdClass());
        $this->assertEquals(3, $taskStarts, 'Third task should start after second completes');
    }
}
