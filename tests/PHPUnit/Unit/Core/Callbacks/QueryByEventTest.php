<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Core\Callbacks;

use Noem\State\Callbacks\CallbackRecord;
use Noem\State\Callbacks\CallbackRegistry;
use Noem\State\Callbacks\CallbackType;
use Noem\State\Region;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

// Test double for CallbackType
class TestCallbackTypeForEvent extends CallbackType
{
}

/**
 * Test: CallbackRegistry can query callbacks by event name
 *
 * Intent: Filters callbacks to specific lifecycle events (action, enter, exit),
 * enabling efficient event-specific processing
 */
#[CoversClass(CallbackRegistry::class)]
final class QueryByEventTest extends TestCase
{
    public function testQueryByEventAction(): void
    {
        $registry = new CallbackRegistry();
        $region = $this->createMock(Region::class);
        $type = TestCallbackTypeForEvent::get();

        $actionRecord = new CallbackRecord(
            region: $region,
            type: $type,
            event: 'action',
            state: 'idle',
            callback: fn() => 'action'
        );

        $enterRecord = new CallbackRecord(
            region: $region,
            type: $type,
            event: 'enter',
            state: 'idle',
            callback: fn() => 'enter'
        );

        $registry->register($actionRecord);
        $registry->register($enterRecord);

        $results = $registry->query(event: 'action');
        $this->assertCount(1, $results);
        $this->assertSame($actionRecord, $results[0]);
    }

    public function testQueryByEventEnter(): void
    {
        $registry = new CallbackRegistry();
        $region = $this->createMock(Region::class);
        $type = TestCallbackTypeForEvent::get();

        $actionRecord = new CallbackRecord(
            region: $region,
            type: $type,
            event: 'action',
            state: 'active',
            callback: fn() => 'action'
        );

        $enterRecord = new CallbackRecord(
            region: $region,
            type: $type,
            event: 'enter',
            state: 'active',
            callback: fn() => 'enter'
        );

        $exitRecord = new CallbackRecord(
            region: $region,
            type: $type,
            event: 'exit',
            state: 'active',
            callback: fn() => 'exit'
        );

        $registry->register($actionRecord);
        $registry->register($enterRecord);
        $registry->register($exitRecord);

        $results = $registry->query(event: 'enter');
        $this->assertCount(1, $results);
        $this->assertSame($enterRecord, $results[0]);
    }

    public function testQueryByEventExit(): void
    {
        $registry = new CallbackRegistry();
        $region = $this->createMock(Region::class);
        $type = TestCallbackTypeForEvent::get();

        $enterRecord = new CallbackRecord(
            region: $region,
            type: $type,
            event: 'enter',
            state: 'done',
            callback: fn() => 'enter'
        );

        $exitRecord = new CallbackRecord(
            region: $region,
            type: $type,
            event: 'exit',
            state: 'done',
            callback: fn() => 'exit'
        );

        $registry->register($enterRecord);
        $registry->register($exitRecord);

        $results = $registry->query(event: 'exit');
        $this->assertCount(1, $results);
        $this->assertSame($exitRecord, $results[0]);
    }
}
