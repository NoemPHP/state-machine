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
class TestCallbackTypeForRegistryStorage extends CallbackType
{
}

/**
 * Test: CallbackRegistry stores CallbackRecord instances
 *
 * Intent: Provides centralized callback storage accessible to all features,
 * enabling cross-cutting callback management
 */
#[CoversClass(CallbackRegistry::class)]
final class RegistryStoresRecordsTest extends TestCase
{
    public function testRegistryStoresCallbackRecords(): void
    {
        $registry = new CallbackRegistry();
        $region = $this->createMock(Region::class);
        $type = TestCallbackTypeForRegistryStorage::get();
        $callback = fn() => null;

        $record = new CallbackRecord(
            region: $region,
            type: $type,
            event: 'action',
            state: 'idle',
            callback: $callback
        );

        $registry->register($record);

        $results = $registry->query();
        $this->assertCount(1, $results);
        $this->assertSame($record, $results[0]);
    }

    public function testRegistryStoresMultipleRecords(): void
    {
        $registry = new CallbackRegistry();
        $region = $this->createMock(Region::class);
        $type = TestCallbackTypeForRegistryStorage::get();

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
            event: 'enter',
            state: 'active',
            callback: fn() => 'second'
        );

        $registry->register($record1);
        $registry->register($record2);

        $results = $registry->query();
        $this->assertCount(2, $results);
        $this->assertSame($record1, $results[0]);
        $this->assertSame($record2, $results[1]);
    }
}
