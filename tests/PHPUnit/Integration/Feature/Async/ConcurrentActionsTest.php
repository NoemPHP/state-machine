<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Integration\Feature\Async;

use Noem\State\Feature\Async\AsyncFeature;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Multiple concurrent async actions execute cooperatively
 */
#[Group('async'), Group('integration')]
class ConcurrentActionsTest extends TestCase
{
    public function testMultipleConcurrentAsyncActionsExecuteCooperatively(): void
    {
        $builder = new RegionBuilder();
        $builder->enableFeatures(new AsyncFeature());

        $log = [];

        $region = $builder
            ->setStates('active')
            ->onAction('active', function (object $trigger) use (&$log) {
                $log[] = 'task1-start';
                yield;
                $log[] = 'task1-middle';
                yield;
                $log[] = 'task1-end';
            })
            ->onAction('active', function (object $trigger) use (&$log) {
                $log[] = 'task2-start';
                yield;
                $log[] = 'task2-middle';
                yield;
                $log[] = 'task2-end';
            })
            ->build();

        // First trigger - both tasks complete (Priority::NORMAL = 5 steps, each task has 3 steps)
        $region->trigger(new \stdClass());
        $this->assertContains('task1-start', $log);
        $this->assertContains('task1-middle', $log);
        $this->assertContains('task1-end', $log);
        $this->assertContains('task2-start', $log);
        $this->assertContains('task2-middle', $log);
        $this->assertContains('task2-end', $log);
        $this->assertCount(6, $log, 'Both 3-step tasks complete in first tick with NORMAL priority');
    }

    public function testTasksInterleaveDuringExecution(): void
    {
        $builder = new RegionBuilder();
        $builder->enableFeatures(new AsyncFeature());

        $interleaveLog = [];

        $region = $builder
            ->setStates('work')
            ->onAction('work', function (object $trigger) use (&$interleaveLog) {
                $interleaveLog[] = 'A1';
                yield;
                $interleaveLog[] = 'A2';
                yield;
            })
            ->onAction('work', function (object $trigger) use (&$interleaveLog) {
                $interleaveLog[] = 'B1';
                yield;
                $interleaveLog[] = 'B2';
                yield;
            })
            ->onAction('work', function (object $trigger) use (&$interleaveLog) {
                $interleaveLog[] = 'C1';
                yield;
                $interleaveLog[] = 'C2';
                yield;
            })
            ->build();

        // Execute trigger - all tasks complete in first tick (Priority::NORMAL = 5 steps, each task has 2 steps)
        $region->trigger(new \stdClass());

        // All three tasks should complete in first tick
        $this->assertContains('A1', $interleaveLog);
        $this->assertContains('A2', $interleaveLog);
        $this->assertContains('B1', $interleaveLog);
        $this->assertContains('B2', $interleaveLog);
        $this->assertContains('C1', $interleaveLog);
        $this->assertContains('C2', $interleaveLog);
        $this->assertCount(6, $interleaveLog, 'All three 2-step tasks complete in first tick');
    }
}
