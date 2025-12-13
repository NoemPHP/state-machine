<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Core\Callbacks;

use Noem\State\Callbacks\CallbackRecord;
use Noem\State\Callbacks\CallbackRegistry;
use Noem\State\Callbacks\CallbackType;
use Noem\State\Region;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

// Test doubles for CallbackType
class TestCallbackTypeForCombined extends CallbackType
{
}

class TestCallbackTypeForCombined2 extends CallbackType
{
}

/**
 * Test: CallbackRegistry supports combined filtering by region, type, event, and state
 *
 * Intent: Enables precise callback queries with multiple criteria, supporting
 * complex filtering scenarios
 */
#[CoversClass(CallbackRegistry::class)]
final class CombinedFilteringTest extends TestCase
{
    public function testCombinedFilteringAllCriteria(): void
    {
        $registry = new CallbackRegistry();
        $region1 = $this->createMock(Region::class);
        $region2 = $this->createMock(Region::class);
        $typeA = TestCallbackTypeForCombined::get();
        $typeB = TestCallbackTypeForCombined2::get();

        // Target record: region1, typeA, action, idle
        $targetRecord = new CallbackRecord(
            region: $region1,
            type: $typeA,
            event: 'action',
            state: 'idle',
            callback: fn() => 'target'
        );

        // Different region
        $differentRegion = new CallbackRecord(
            region: $region2,
            type: $typeA,
            event: 'action',
            state: 'idle',
            callback: fn() => 'diff-region'
        );

        // Different type
        $differentType = new CallbackRecord(
            region: $region1,
            type: $typeB,
            event: 'action',
            state: 'idle',
            callback: fn() => 'diff-type'
        );

        // Different event
        $differentEvent = new CallbackRecord(
            region: $region1,
            type: $typeA,
            event: 'enter',
            state: 'idle',
            callback: fn() => 'diff-event'
        );

        // Different state
        $differentState = new CallbackRecord(
            region: $region1,
            type: $typeA,
            event: 'action',
            state: 'active',
            callback: fn() => 'diff-state'
        );

        $registry->register($targetRecord);
        $registry->register($differentRegion);
        $registry->register($differentType);
        $registry->register($differentEvent);
        $registry->register($differentState);

        $results = $registry->query(
            region: $region1,
            type: $typeA,
            event: 'action',
            state: 'idle'
        );

        $this->assertCount(1, $results);
        $this->assertSame($targetRecord, $results[0]);
    }

    public function testCombinedFilteringSubset(): void
    {
        $registry = new CallbackRegistry();
        $region = $this->createMock(Region::class);
        $type = TestCallbackTypeForCombined::get();

        $record1 = new CallbackRecord(
            region: $region,
            type: $type,
            event: 'action',
            state: 'idle',
            callback: fn() => '1'
        );

        $record2 = new CallbackRecord(
            region: $region,
            type: $type,
            event: 'action',
            state: 'active',
            callback: fn() => '2'
        );

        $record3 = new CallbackRecord(
            region: $region,
            type: $type,
            event: 'enter',
            state: 'idle',
            callback: fn() => '3'
        );

        $registry->register($record1);
        $registry->register($record2);
        $registry->register($record3);

        // Query by region and event only
        $results = $registry->query(region: $region, event: 'action');
        $this->assertCount(2, $results);
        $this->assertContains($record1, $results);
        $this->assertContains($record2, $results);
    }
}
