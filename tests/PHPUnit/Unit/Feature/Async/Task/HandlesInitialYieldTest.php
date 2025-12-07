<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Async\Task;

use Noem\State\Feature\Async\Task;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Task handles initial yield without send
 */
#[Group('async'), Group('task-management')]
class HandlesInitialYieldTest extends TestCase
{
    public function testHandlesInitialYield(): void
    {
        $generator = (function () {
            yield 'first-value';
            yield 'second-value';
        })();

        $task = new Task($generator);

        // First run should use current() not send(), because you cannot send to a generator
        // before it has started
        $result = $task->run();

        $this->assertSame(
            'first-value',
            $result,
            'First run should return initial yielded value using current()'
        );
    }

    public function testSubsequentRunsUseSend(): void
    {
        $receivedValue = null;

        $generator = (function () use (&$receivedValue) {
            yield 'initial';
            $receivedValue = yield 'second';
            yield 'third';
        })();

        $task = new Task($generator);

        // First run uses current() - at first yield
        $result1 = $task->run();
        $this->assertSame('initial', $result1);
        $this->assertNull($receivedValue, 'No value should be received yet');

        // Second run uses send() - advances to second yield
        $result2 = $task->run();
        $this->assertSame('second', $result2);
        $this->assertNull($receivedValue, 'Still no value received - we are at the second yield');

        // Third run uses send() with a value - the value is assigned to $receivedValue
        $task->setSendValue('sent-value');
        $result3 = $task->run();

        $this->assertSame('third', $result3);
        $this->assertSame(
            'sent-value',
            $receivedValue,
            'Third run should use send() to inject value into previous yield'
        );
    }

    public function testFirstRunDoesNotAdvanceGenerator(): void
    {
        $steps = [];

        $generator = (function () use (&$steps) {
            $steps[] = 'before-first-yield';
            yield 'value1';
            $steps[] = 'after-first-yield';
            yield 'value2';
        })();

        $task = new Task($generator);

        // First run executes up to first yield and returns current value
        $task->run();

        $this->assertSame(
            ['before-first-yield'],
            $steps,
            'First run should execute up to first yield using current()'
        );

        // Second run advances from first yield to second
        $task->run();

        $this->assertSame(
            ['before-first-yield', 'after-first-yield'],
            $steps,
            'Second run should use send() to advance generator'
        );
    }

    public function testFirstRunUsesCurrentNotSend(): void
    {
        $sendUsedOnFirstRun = false;

        $generator = (function () use (&$sendUsedOnFirstRun) {
            // If send() is used on first run, it would send a value
            // But current() is used, so no value is sent
            $received = yield 'first';
            if ($received !== null) {
                $sendUsedOnFirstRun = true;
            }
            yield 'second';
        })();

        $task = new Task($generator);

        // First run should use current(), not send()
        $task->run();

        // Second run uses send(null) by default
        $task->run();

        $this->assertFalse(
            $sendUsedOnFirstRun,
            'First run should use current() which does not send a value'
        );
    }
}
