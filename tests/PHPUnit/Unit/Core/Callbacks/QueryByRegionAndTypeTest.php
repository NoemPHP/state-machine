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
class TestCallbackTypeA extends CallbackType
{
}

class TestCallbackTypeB extends CallbackType
{
}

/**
 * Test: CallbackRegistry can query callbacks by region and type
 *
 * Intent: Enables features to retrieve only their channel's callbacks,
 * preventing interference between callback types
 */
#[CoversClass(CallbackRegistry::class)]
final class QueryByRegionAndTypeTest extends TestCase
{
    public function testQueryByRegion(): void
    {
        $registry = new CallbackRegistry();
        $region1 = $this->createMock(Region::class);
        $region2 = $this->createMock(Region::class);
        $type = TestCallbackTypeA::get();

        $record1 = new CallbackRecord(
            region: $region1,
            type: $type,
            event: 'action',
            state: 'idle',
            callback: fn() => 'region1'
        );

        $record2 = new CallbackRecord(
            region: $region2,
            type: $type,
            event: 'action',
            state: 'idle',
            callback: fn() => 'region2'
        );

        $registry->register($record1);
        $registry->register($record2);

        $results = $registry->query(region: $region1);
        $this->assertCount(1, $results);
        $this->assertSame($record1, $results[0]);
    }

    public function testQueryByType(): void
    {
        $registry = new CallbackRegistry();
        $region = $this->createMock(Region::class);
        $typeA = TestCallbackTypeA::get();
        $typeB = TestCallbackTypeB::get();

        $recordA = new CallbackRecord(
            region: $region,
            type: $typeA,
            event: 'action',
            state: 'idle',
            callback: fn() => 'typeA'
        );

        $recordB = new CallbackRecord(
            region: $region,
            type: $typeB,
            event: 'action',
            state: 'idle',
            callback: fn() => 'typeB'
        );

        $registry->register($recordA);
        $registry->register($recordB);

        $results = $registry->query(type: $typeA);
        $this->assertCount(1, $results);
        $this->assertSame($recordA, $results[0]);
    }

    public function testQueryByRegionAndType(): void
    {
        $registry = new CallbackRegistry();
        $region1 = $this->createMock(Region::class);
        $region2 = $this->createMock(Region::class);
        $typeA = TestCallbackTypeA::get();
        $typeB = TestCallbackTypeB::get();

        $record1A = new CallbackRecord(
            region: $region1,
            type: $typeA,
            event: 'action',
            state: 'idle',
            callback: fn() => 'r1-typeA'
        );

        $record1B = new CallbackRecord(
            region: $region1,
            type: $typeB,
            event: 'action',
            state: 'idle',
            callback: fn() => 'r1-typeB'
        );

        $record2A = new CallbackRecord(
            region: $region2,
            type: $typeA,
            event: 'action',
            state: 'idle',
            callback: fn() => 'r2-typeA'
        );

        $registry->register($record1A);
        $registry->register($record1B);
        $registry->register($record2A);

        $results = $registry->query(region: $region1, type: $typeA);
        $this->assertCount(1, $results);
        $this->assertSame($record1A, $results[0]);
    }
}
