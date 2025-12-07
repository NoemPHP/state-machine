<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Async\Task;

use Noem\State\Feature\Async\Task;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Task sends values into generator via setSendValue
 */
#[Group('async'), Group('task-management')]
class SendsValuesTest extends TestCase
{
    public function testSendsValues(): void
    {
        $receivedValue = null;

        $generator = (function () use (&$receivedValue) {
            yield 'first';
            $receivedValue = yield 'second';
            yield 'third';
        })();

        $task = new Task($generator);

        // First run - gets to first yield
        $task->run();

        // Second run - gets to second yield
        $task->run();

        // Set value to send
        $task->setSendValue('injected-value');

        // Third run - sends value into generator at yield point
        $task->run();

        $this->assertSame(
            'injected-value',
            $receivedValue,
            'Generator should receive the value sent via setSendValue'
        );
    }

    public function testBidirectionalCommunication(): void
    {
        $generator = (function () {
            $received1 = yield 'request-1';
            $received2 = yield 'got:' . $received1;
            yield 'got:' . $received2;
        })();

        $task = new Task($generator);

        // First run
        $result1 = $task->run();
        $this->assertSame('request-1', $result1);

        // Send value and run
        $task->setSendValue('data-A');
        $result2 = $task->run();
        $this->assertSame('got:data-A', $result2);

        // Send another value and run
        $task->setSendValue('data-B');
        $result3 = $task->run();
        $this->assertSame('got:data-B', $result3);
    }

    public function testSendValueClearedAfterUse(): void
    {
        $receivedValues = [];

        $generator = (function () use (&$receivedValues) {
            $receivedValues[] = yield 'a';
            $receivedValues[] = yield 'b';
            $receivedValues[] = yield 'c';
        })();

        $task = new Task($generator);

        $task->run(); // First yield

        $task->setSendValue('value-1');
        $task->run(); // Sends value-1

        // setSendValue is cleared after use, so next run sends null
        $task->run();

        $this->assertSame(
            ['value-1', null],
            $receivedValues,
            'Send value should be cleared after use'
        );
    }
}
