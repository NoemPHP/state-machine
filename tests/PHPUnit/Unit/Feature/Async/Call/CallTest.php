<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Async\Call;

use Noem\State\Feature\Async\Call;
use Noem\State\Feature\Async\CoroutineScheduler;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Call.call spawns nested coroutine and waits for completion
 */
#[Group('async'), Group('call-helpers')]
class CallTest extends TestCase
{
    public function testCallSpawnsNestedCoroutine(): void
    {
        $scheduler = new CoroutineScheduler();
        
        $childSteps = [];
        $parentSteps = [];
        
        $childGenerator = function () use (&$childSteps) {
            $childSteps[] = 'child-1';
            yield;
            $childSteps[] = 'child-2';
            return 'child-result';
        };
        
        $callOp = Call::call($childGenerator);
        
        $parentGen = (function () use ($callOp, &$parentSteps) {
            $parentSteps[] = 'parent-before';
            $result = yield $callOp;
            $parentSteps[] = 'parent-after:' . $result;
            yield; // Need another yield to receive the value
        })();
        
        $scheduler->enqueue($parentGen);
        
        // Tick 1: Parent executes before, yields call
        // Call is invoked, parent is paused, child starts and executes first step
        $scheduler->tick();
        $this->assertSame(['parent-before'], $parentSteps);
        $this->assertSame(['child-1'], $childSteps, 'Child executes in same tick as Call invocation');
        
        // Tick 2: Child continues and completes, parent still paused
        $scheduler->tick();
        $this->assertSame(['child-1', 'child-2'], $childSteps);
        $this->assertSame(['parent-before'], $parentSteps, 'Parent still paused');
        
        // Tick 3: Child detected as finished, callback resumes parent
        $scheduler->tick();
        
        // Tick 4: Parent receives value and executes
        $scheduler->tick();
        $this->assertSame(['parent-before', 'parent-after:child-result'], $parentSteps, 'Parent receives child result');
    }
    
    public function testCallWaitsForCompletion(): void
    {
        $scheduler = new CoroutineScheduler();
        
        $parentExecuted = false;
        
        $childGenerator = function () {
            yield;
            yield;
            return 'done';
        };
        
        $callOp = Call::call($childGenerator);
        
        $parentGen = (function () use ($callOp, &$parentExecuted) {
            yield $callOp;
            $parentExecuted = true;
        })();
        
        $scheduler->enqueue($parentGen);
        
        // Multiple ticks: child should complete before parent continues
        $scheduler->tick();
        $this->assertFalse($parentExecuted);
        $scheduler->tick();
        $this->assertFalse($parentExecuted);
        $scheduler->tick();
        $this->assertFalse($parentExecuted);
        $scheduler->tick();
        $this->assertFalse($parentExecuted);
        
        // Eventually parent executes
        $scheduler->tick();
        $this->assertTrue($parentExecuted, 'Parent should eventually execute after child completes');
    }
    
    public function testCallReturnsChildResult(): void
    {
        $scheduler = new CoroutineScheduler();
        
        $receivedResult = null;
        
        $childGenerator = function () {
            yield 'intermediate';
            return 'final-result';
        };
        
        $callOp = Call::call($childGenerator);
        
        $parentGen = (function () use ($callOp, &$receivedResult) {
            $receivedResult = yield $callOp;
            yield; // Need to resume to receive the value
        })();
        
        $scheduler->enqueue($parentGen);
        
        // Execute until child completes and parent receives value
        for ($i = 0; $i < 10; $i++) {
            $scheduler->tick();
            if ($receivedResult !== null) {
                break;
            }
        }
        
        $this->assertSame('final-result', $receivedResult, 'Parent should receive child return value');
    }
}
