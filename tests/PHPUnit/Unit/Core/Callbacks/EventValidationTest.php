<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Core\Callbacks;

use Noem\State\Callbacks\AddCallback;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Test: AddCallback validates event is one of action, enter, or exit
 *
 * Intent: Prevents invalid event registration, ensuring callbacks only attach
 * to supported lifecycle events
 */
#[CoversClass(AddCallback::class)]
final class EventValidationTest extends TestCase
{
    public function testAddCallbackAcceptsValidEvents(): void
    {
        $callback = fn() => null;

        // action event
        $actionCallback = new AddCallback(
            event: 'action',
            state: 'idle',
            callback: $callback
        );
        $this->assertInstanceOf(AddCallback::class, $actionCallback);

        // enter event
        $enterCallback = new AddCallback(
            event: 'enter',
            state: 'idle',
            callback: $callback
        );
        $this->assertInstanceOf(AddCallback::class, $enterCallback);

        // exit event
        $exitCallback = new AddCallback(
            event: 'exit',
            state: 'idle',
            callback: $callback
        );
        $this->assertInstanceOf(AddCallback::class, $exitCallback);
    }

    public function testAddCallbackRejectsInvalidEvent(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Event must be one of action, enter, exit, got 'invalid'");

        new AddCallback(
            event: 'invalid',
            state: 'idle',
            callback: fn() => null
        );
    }

    public function testAddCallbackRejectsEmptyEvent(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new AddCallback(
            event: '',
            state: 'idle',
            callback: fn() => null
        );
    }

    public function testAddCallbackRejectsCaseSensitiveEvent(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Event must be one of action, enter, exit, got 'ACTION'");

        new AddCallback(
            event: 'ACTION',
            state: 'idle',
            callback: fn() => null
        );
    }
}
