<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Async\Task;

use Noem\State\Feature\Async\Task;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Task retrieves generator return value after completion
 */
#[Group('async'), Group('task-management')]
class RetrievesReturnValueTest extends TestCase
{
    public function testRetrievesReturnValue(): void
    {
        $generator = (function () {
            yield 'step1';
            yield 'step2';
            return 'final-result';
        })();
        
        $task = new Task($generator);
        
        // Run through all yields
        $task->run(); // step1
        $task->run(); // step2
        $task->run(); // finishes and returns
        
        $this->assertTrue($task->isFinished());
        
        $returnValue = $task->getReturn();
        $this->assertSame('final-result', $returnValue,
            'Task should return the generator\'s return value');
    }
    
    public function testRunReturnsGeneratorReturnValueWhenFinished(): void
    {
        $generator = (function () {
            yield 'only-step';
            return 'completion-value';
        })();
        
        $task = new Task($generator);
        
        $task->run(); // Execute first yield
        
        // When run() advances past final yield, it returns the generator's return value
        $result = $task->run();
        
        $this->assertSame('completion-value', $result,
            'run() should return generator return value when task finishes');
        $this->assertTrue($task->isFinished());
    }
    
    public function testHandlesNullReturnValue(): void
    {
        $generator = (function () {
            yield 'value';
            return null;
        })();
        
        $task = new Task($generator);
        
        $task->run();
        $task->run();
        
        $returnValue = $task->getReturn();
        $this->assertNull($returnValue, 'Task should handle null return value');
    }
    
    public function testHandlesComplexReturnValue(): void
    {
        $generator = (function () {
            yield;
            return ['status' => 'success', 'data' => [1, 2, 3]];
        })();
        
        $task = new Task($generator);
        
        $task->run();
        $task->run();
        
        $returnValue = $task->getReturn();
        $this->assertSame(
            ['status' => 'success', 'data' => [1, 2, 3]],
            $returnValue,
            'Task should handle complex return values'
        );
    }
}
