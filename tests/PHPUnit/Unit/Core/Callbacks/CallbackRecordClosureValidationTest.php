<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Core\Callbacks;

use Noem\State\Callbacks\CallbackRecord;
use Noem\State\Callbacks\CallbackType;
use Noem\State\Region;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

// Test double for CallbackType
class TestCallbackTypeForValidation extends CallbackType
{
}

/**
 * Test: CallbackRecord validates that callback is a Closure
 *
 * Intent: Ensures type safety by requiring closures, which are serializable and inspectable
 */
#[CoversClass(CallbackRecord::class)]
final class CallbackRecordClosureValidationTest extends TestCase
{
    public function testCallbackRecordValidatesCallbackIsClosure(): void
    {
        $region = $this->createMock(Region::class);
        $type = TestCallbackTypeForValidation::get();

        // Valid closure
        $validCallback = fn() => null;
        $record = new CallbackRecord(
            region: $region,
            type: $type,
            event: 'action',
            state: 'idle',
            callback: $validCallback
        );

        $this->assertInstanceOf(\Closure::class, $record->callback);
    }

    public function testCallbackRecordAcceptsClosuresWithParameters(): void
    {
        $region = $this->createMock(Region::class);
        $type = TestCallbackTypeForValidation::get();

        $callbackWithParams = fn($event, $context) => $context['data'] ?? null;
        $record = new CallbackRecord(
            region: $region,
            type: $type,
            event: 'action',
            state: 'processing',
            callback: $callbackWithParams
        );

        $this->assertInstanceOf(\Closure::class, $record->callback);
        $this->assertSame($callbackWithParams, $record->callback);
    }

    public function testCallbackRecordAcceptsClosuresWithReturnTypes(): void
    {
        $region = $this->createMock(Region::class);
        $type = TestCallbackTypeForValidation::get();

        $callbackWithReturnType = fn(): string => 'result';
        $record = new CallbackRecord(
            region: $region,
            type: $type,
            event: 'enter',
            state: 'ready',
            callback: $callbackWithReturnType
        );

        $this->assertInstanceOf(\Closure::class, $record->callback);
        $this->assertSame('result', ($record->callback)());
    }
}
