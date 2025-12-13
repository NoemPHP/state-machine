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
class TestCallbackTypeForOrder extends CallbackType
{
}

/**
 * Test: CallbackRegistry preserves insertion order within query results
 *
 * Intent: Ensures callbacks execute in registration order, providing predictable
 * execution sequence for deterministic behavior
 */
#[CoversClass(CallbackRegistry::class)]
final class InsertionOrderPreservationTest extends TestCase
{
    public function testInsertionOrderIsPreserved(): void
    {
        $registry = new CallbackRegistry();
        $region = $this->createMock(Region::class);
        $type = TestCallbackTypeForOrder::get();

        $record1 = new CallbackRecord(
            region: $region,
            type: $type,
            event: 'action',
            state: 'idle',
            callback: fn() => 'first'
        );

        $record2 = new CallbackRecord(
            region: $region,
            type: $type,
            event: 'action',
            state: 'idle',
            callback: fn() => 'second'
        );

        $record3 = new CallbackRecord(
            region: $region,
            type: $type,
            event: 'action',
            state: 'idle',
            callback: fn() => 'third'
        );

        $registry->register($record1);
        $registry->register($record2);
        $registry->register($record3);

        $results = $registry->query();
        $this->assertCount(3, $results);
        $this->assertSame($record1, $results[0]);
        $this->assertSame($record2, $results[1]);
        $this->assertSame($record3, $results[2]);
    }

    public function testInsertionOrderPreservedWithFiltering(): void
    {
        $registry = new CallbackRegistry();
        $region = $this->createMock(Region::class);
        $type = TestCallbackTypeForOrder::get();

        $idleRecord1 = new CallbackRecord(
            region: $region,
            type: $type,
            event: 'action',
            state: 'idle',
            callback: fn() => 'idle-1'
        );

        $activeRecord = new CallbackRecord(
            region: $region,
            type: $type,
            event: 'action',
            state: 'active',
            callback: fn() => 'active'
        );

        $idleRecord2 = new CallbackRecord(
            region: $region,
            type: $type,
            event: 'action',
            state: 'idle',
            callback: fn() => 'idle-2'
        );

        $idleRecord3 = new CallbackRecord(
            region: $region,
            type: $type,
            event: 'action',
            state: 'idle',
            callback: fn() => 'idle-3'
        );

        $registry->register($idleRecord1);
        $registry->register($activeRecord);
        $registry->register($idleRecord2);
        $registry->register($idleRecord3);

        // Query for 'idle' state should preserve insertion order
        $results = $registry->query(state: 'idle');
        $this->assertCount(3, $results);
        $this->assertSame($idleRecord1, $results[0]);
        $this->assertSame($idleRecord2, $results[1]);
        $this->assertSame($idleRecord3, $results[2]);
    }
}
