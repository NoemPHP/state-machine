<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Async;

use Noem\State\Feature\Async\AsyncFeature;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: AsyncFeature clears callback mapping on task completion
 */
#[Group('async'), Group('async-callback-handling')]
class ClearsCompletedTaskMappingTest extends TestCase
{
    public function testClearsCompletedTaskMapping(): void
    {
        $builder = new RegionBuilder();
        $builder->enableFeatures(new AsyncFeature());

        $executionCount = 0;

        $region = $builder
            ->setStates('idle')
            ->onAction('idle', function (object $trigger) use (&$executionCount) {
                $executionCount++;
                yield 'value';
                return 'done';
            })
            ->build();

        // First trigger - creates and completes task (Priority::NORMAL = 5 steps, task has 2 steps)
        $region->trigger(new \stdClass());
        $this->assertSame(1, $executionCount);

        // Second trigger - should create a NEW task since previous task completed and complete it
        $region->trigger(new \stdClass());
        $this->assertSame(2, $executionCount, 'Should create new task after previous completed');
    }

    public function testAllowsTaskRecreationAfterCompletion(): void
    {
        $builder = new RegionBuilder();
        $builder->enableFeatures(new AsyncFeature());

        $runs = [];
        $runCount = 0;
        $region = $builder
            ->setStates('idle')
            ->onAction('idle', function (object $trigger) use (&$runs, &$runCount) {
                $runCount++;
                $runs[] = "run{$runCount}-start";
                yield;
                $runs[] = "run{$runCount}-end";
            })
            ->build();

        // First execution cycle - completes in one tick (Priority::NORMAL = 5 steps, task has 2 steps)
        $region->trigger(new \stdClass());
        $this->assertSame(['run1-start', 'run1-end'], $runs);

        // Second execution cycle - new task created and completes
        $region->trigger(new \stdClass());
        $this->assertSame(['run1-start', 'run1-end', 'run2-start', 'run2-end'], $runs);
    }
}
