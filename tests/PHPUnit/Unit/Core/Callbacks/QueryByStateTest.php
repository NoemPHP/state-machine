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
class TestCallbackTypeForState extends CallbackType
{
}

/**
 * Test: CallbackRegistry can query callbacks by state name
 *
 * Intent: Retrieves callbacks registered for specific states, supporting
 * state-scoped callback execution
 */
#[CoversClass(CallbackRegistry::class)]
final class QueryByStateTest extends TestCase
{
    public function testQueryByState(): void
    {
        $registry = new CallbackRegistry();
        $region = $this->createMock(Region::class);
        $type = TestCallbackTypeForState::get();

        $idleRecord = new CallbackRecord(
            region: $region,
            type: $type,
            event: 'action',
            state: 'idle',
            callback: fn() => 'idle-callback'
        );

        $activeRecord = new CallbackRecord(
            region: $region,
            type: $type,
            event: 'action',
            state: 'active',
            callback: fn() => 'active-callback'
        );

        $registry->register($idleRecord);
        $registry->register($activeRecord);

        $results = $registry->query(state: 'idle');
        $this->assertCount(1, $results);
        $this->assertSame($idleRecord, $results[0]);
    }

    public function testQueryByStateReturnsMultipleCallbacksForSameState(): void
    {
        $registry = new CallbackRegistry();
        $region = $this->createMock(Region::class);
        $type = TestCallbackTypeForState::get();

        $idleAction = new CallbackRecord(
            region: $region,
            type: $type,
            event: 'action',
            state: 'idle',
            callback: fn() => 'action'
        );

        $idleEnter = new CallbackRecord(
            region: $region,
            type: $type,
            event: 'enter',
            state: 'idle',
            callback: fn() => 'enter'
        );

        $activeAction = new CallbackRecord(
            region: $region,
            type: $type,
            event: 'action',
            state: 'active',
            callback: fn() => 'active'
        );

        $registry->register($idleAction);
        $registry->register($idleEnter);
        $registry->register($activeAction);

        $results = $registry->query(state: 'idle');
        $this->assertCount(2, $results);
        $this->assertContains($idleAction, $results);
        $this->assertContains($idleEnter, $results);
    }
}
