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
        
        // First trigger - both tasks start
        $region->trigger(new \stdClass());
        $this->assertContains('task1-start', $log);
        $this->assertContains('task2-start', $log);
        $this->assertCount(2, $log);
        
        // Second trigger - both tasks progress
        $region->trigger(new \stdClass());
        $this->assertContains('task1-middle', $log);
        $this->assertContains('task2-middle', $log);
        $this->assertCount(4, $log);
        
        // Third trigger - both tasks end
        $region->trigger(new \stdClass());
        $this->assertContains('task1-end', $log);
        $this->assertContains('task2-end', $log);
        $this->assertCount(6, $log);
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
        
        // Execute triggers to see interleaving
        $region->trigger(new \stdClass());
        $firstTick = $interleaveLog;
        
        $region->trigger(new \stdClass());
        $secondTick = array_slice($interleaveLog, count($firstTick));
        
        // All three tasks should have started in first tick
        $this->assertContains('A1', $firstTick);
        $this->assertContains('B1', $firstTick);
        $this->assertContains('C1', $firstTick);
        
        // All three tasks should progress in second tick
        $this->assertContains('A2', $secondTick);
        $this->assertContains('B2', $secondTick);
        $this->assertContains('C2', $secondTick);
    }
}
