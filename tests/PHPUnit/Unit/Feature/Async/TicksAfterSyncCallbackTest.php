<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Async;

use Noem\State\Feature\Async\AsyncFeature;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: AsyncFeature ticks scheduler after synchronous callbacks
 */
#[Group('async'), Group('async-callback-handling')]
class TicksAfterSyncCallbackTest extends TestCase
{
    public function testTicksAfterSyncCallback(): void
    {
        $builder = new RegionBuilder();
        $builder->enableFeatures(new AsyncFeature());
        
        $asyncProgress = [];
        $syncExecuted = false;
        
        $region = $builder
            ->setStates('idle')
            // Async callback
            ->onAction('idle', function (object $trigger) use (&$asyncProgress) {
                $asyncProgress[] = 'async1';
                yield;
                $asyncProgress[] = 'async2';
                yield;
            })
            // Sync callback
            ->onAction('idle', function (object $trigger) use (&$syncExecuted) {
                $syncExecuted = true;
            })
            ->build();
        
        // First trigger
        $region->trigger(new \stdClass());
        
        $this->assertTrue($syncExecuted, 'Sync callback should have executed');
        $this->assertSame(['async1'], $asyncProgress, 'Async should have progressed once');
        
        // Second trigger - sync callback runs again, and scheduler ticks
        $syncExecuted = false;
        $region->trigger(new \stdClass());
        
        $this->assertTrue($syncExecuted);
        $this->assertSame(['async1', 'async2'], $asyncProgress, 'Async should have progressed again');
    }
    
    public function testTicksProgressesAllTasks(): void
    {
        $builder = new RegionBuilder();
        $builder->enableFeatures(new AsyncFeature());
        
        $task1Progress = 0;
        $task2Progress = 0;
        
        $region = $builder
            ->setStates('idle')
            ->onAction('idle', function (object $trigger) use (&$task1Progress) {
                $task1Progress++;
                yield;
                $task1Progress++;
                yield;
            })
            ->onAction('idle', function (object $trigger) use (&$task2Progress) {
                $task2Progress++;
                yield;
                $task2Progress++;
                yield;
            })
            ->build();
        
        // First trigger - both tasks progress once
        $region->trigger(new \stdClass());
        $this->assertSame(1, $task1Progress);
        $this->assertSame(1, $task2Progress);
        
        // Second trigger - both tasks progress again
        $region->trigger(new \stdClass());
        $this->assertSame(2, $task1Progress);
        $this->assertSame(2, $task2Progress);
    }
}
