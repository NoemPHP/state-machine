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
 * Acceptance Criterion: Singleton callback prevents concurrent task execution
 * Intent: Validates singleton mutex behavior with overlapping triggers
 */
#[Group('async'), Group('integration')]
class SingletonCallbackTest extends TestCase
{
    public function testSingletonCallbackPreventsConcurrentTaskExecution(): void
    {
        $builder = new RegionBuilder();
        $builder->enableFeatures(new ExtendedState(), new AsyncFeature());

        $starts = 0;
        $steps = 0;

        $singletonCallback = function (object $trigger) use (&$starts, &$steps) {
            $starts++;
            for ($i = 0; $i < 20; $i++) {
                $steps++;
                yield;
            }
        };

        $config = new AsyncConfig(singleton: true);

        $builder
            ->setStates('active')
            ->onAction('active', $singletonCallback, $config);

        $region = $builder->build();

        // First trigger - starts task
        $region->trigger(new \stdClass());
        $this->assertEquals(1, $starts, 'Task should start once');
        $this->assertGreaterThan(0, $steps, 'Task should advance some steps');

        $stepsAfterFirst = $steps;

        // Second trigger while task still running - singleton prevents new task
        $region->trigger(new \stdClass());
        $this->assertEquals(1, $starts, 'Singleton should prevent second task from starting');
        $this->assertGreaterThan($stepsAfterFirst, $steps, 'Existing task should continue advancing');
    }
}
