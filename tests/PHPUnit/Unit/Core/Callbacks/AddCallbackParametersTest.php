<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Core\Callbacks;

use Noem\State\Callbacks\AddCallback;
use Noem\State\Callbacks\CallbackType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

// Test double for CallbackType
class TestCallbackTypeForParams extends CallbackType
{
}

/**
 * Test: AddCallback accepts event, state, callback, and optional type parameters
 *
 * Intent: Provides flexible registration API supporting both default and custom
 * callback types
 */
#[CoversClass(AddCallback::class)]
final class AddCallbackParametersTest extends TestCase
{
    public function testAddCallbackAcceptsRequiredParameters(): void
    {
        $callback = fn() => null;

        $addCallback = new AddCallback(
            event: 'action',
            state: 'idle',
            callback: $callback
        );

        $this->assertInstanceOf(AddCallback::class, $addCallback);
    }

    public function testAddCallbackAcceptsOptionalTypeParameter(): void
    {
        $type = TestCallbackTypeForParams::get();
        $callback = fn() => null;

        $addCallback = new AddCallback(
            event: 'action',
            state: 'idle',
            callback: $callback,
            type: $type
        );

        $this->assertInstanceOf(AddCallback::class, $addCallback);
    }

    public function testAddCallbackAcceptsAllEventTypes(): void
    {
        $callback = fn() => null;

        $actionCallback = new AddCallback(
            event: 'action',
            state: 'idle',
            callback: $callback
        );
        $this->assertInstanceOf(AddCallback::class, $actionCallback);

        $enterCallback = new AddCallback(
            event: 'enter',
            state: 'idle',
            callback: $callback
        );
        $this->assertInstanceOf(AddCallback::class, $enterCallback);

        $exitCallback = new AddCallback(
            event: 'exit',
            state: 'idle',
            callback: $callback
        );
        $this->assertInstanceOf(AddCallback::class, $exitCallback);
    }
}
