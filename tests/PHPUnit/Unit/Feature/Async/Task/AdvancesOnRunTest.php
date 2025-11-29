<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Async\Task;

use Noem\State\Feature\Async\Task;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Task advances generator on run call
 */
#[Group('async'), Group('task-management')]
class AdvancesOnRunTest extends TestCase
{
    public function testAdvancesOnRun(): void
    {
        $generator = (function () {
            yield 'first';
            yield 'second';
            yield 'third';
        })();
        
        $task = new Task($generator);
        
        // First run returns first yielded value
        $value1 = $task->run();
        $this->assertSame('first', $value1);
        
        // Second run advances to second yield
        $value2 = $task->run();
        $this->assertSame('second', $value2);
        
        // Third run advances to third yield
        $value3 = $task->run();
        $this->assertSame('third', $value3);
    }
    
    public function testReturnsCurrentYieldedValue(): void
    {
        $generator = (function () {
            yield 'A';
            yield 'B';
        })();
        
        $task = new Task($generator);
        
        $result = $task->run();
        $this->assertSame('A', $result, 'run() should return the current yielded value');
    }
    
    public function testProgressesThroughGeneratorSteps(): void
    {
        $executionOrder = [];
        
        $generator = (function () use (&$executionOrder) {
            $executionOrder[] = 'before-yield-1';
            yield 'v1';
            $executionOrder[] = 'before-yield-2';
            yield 'v2';
            $executionOrder[] = 'after-yield-2';
        })();
        
        $task = new Task($generator);
        
        // First run: executes up to first yield
        $task->run();
        $this->assertSame(['before-yield-1'], $executionOrder);
        
        // Second run: executes from first yield to second yield
        $task->run();
        $this->assertSame(['before-yield-1', 'before-yield-2'], $executionOrder);
        
        // Third run: executes from second yield to end
        $task->run();
        $this->assertSame(['before-yield-1', 'before-yield-2', 'after-yield-2'], $executionOrder);
    }
}
