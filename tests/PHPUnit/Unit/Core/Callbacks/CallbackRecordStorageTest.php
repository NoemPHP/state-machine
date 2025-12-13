<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Core\Callbacks;

use Noem\State\Callbacks\CallbackRecord;
use Noem\State\Callbacks\CallbackType;
use Noem\State\Region;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

// Test double for CallbackType
class TestCallbackTypeForStorage extends CallbackType
{
}

/**
 * Test: CallbackRecord stores region, type, event, state, and callback
 *
 * Intent: Encapsulates all metadata needed to identify and invoke a callback,
 * providing complete context for registry queries
 */
#[CoversClass(CallbackRecord::class)]
final class CallbackRecordStorageTest extends TestCase
{
    public function testCallbackRecordStoresRegionTypeEventStateAndCallback(): void
    {
        $region = $this->createMock(Region::class);
        $type = TestCallbackTypeForStorage::get();
        $event = 'action';
        $state = 'idle';
        $callback = fn() => null;

        $record = new CallbackRecord(
            region: $region,
            type: $type,
            event: $event,
            state: $state,
            callback: $callback
        );

        $this->assertSame($region, $record->region);
        $this->assertTrue($type->is($record->type));
        $this->assertSame($event, $record->event);
        $this->assertSame($state, $record->state);
        $this->assertSame($callback, $record->callback);
        $this->assertNull($record->metadata);
    }
}
