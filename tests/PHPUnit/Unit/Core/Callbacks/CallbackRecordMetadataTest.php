<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Core\Callbacks;

use Noem\State\Callbacks\CallbackRecord;
use Noem\State\Callbacks\CallbackType;
use Noem\State\Region;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

// Test double for CallbackType
class TestCallbackTypeForMetadata extends CallbackType
{
}

/**
 * Test: CallbackRecord accepts optional metadata for feature-specific configuration
 *
 * Intent: Enables features to attach custom configuration (e.g., AsyncConfig) without
 * coupling core to specific feature implementations
 */
#[CoversClass(CallbackRecord::class)]
final class CallbackRecordMetadataTest extends TestCase
{
    public function testCallbackRecordAcceptsOptionalMetadata(): void
    {
        $region = $this->createMock(Region::class);
        $type = TestCallbackTypeForMetadata::get();
        $callback = fn() => null;
        $metadata = ['priority' => 10, 'timeout' => 5000];

        $record = new CallbackRecord(
            region: $region,
            type: $type,
            event: 'action',
            state: 'idle',
            callback: $callback,
            metadata: $metadata
        );

        $this->assertSame($metadata, $record->metadata);
    }

    public function testCallbackRecordMetadataDefaultsToNull(): void
    {
        $region = $this->createMock(Region::class);
        $type = TestCallbackTypeForMetadata::get();
        $callback = fn() => null;

        $record = new CallbackRecord(
            region: $region,
            type: $type,
            event: 'action',
            state: 'idle',
            callback: $callback
        );

        $this->assertNull($record->metadata);
    }

    public function testCallbackRecordSupportsVariousMetadataTypes(): void
    {
        $region = $this->createMock(Region::class);
        $type = TestCallbackTypeForMetadata::get();
        $callback = fn() => null;

        // String metadata
        $record1 = new CallbackRecord(
            region: $region,
            type: $type,
            event: 'action',
            state: 'idle',
            callback: $callback,
            metadata: 'custom-config'
        );
        $this->assertSame('custom-config', $record1->metadata);

        // Object metadata
        $configObject = new \stdClass();
        $configObject->timeout = 1000;
        $record2 = new CallbackRecord(
            region: $region,
            type: $type,
            event: 'enter',
            state: 'active',
            callback: $callback,
            metadata: $configObject
        );
        $this->assertSame($configObject, $record2->metadata);

        // Array metadata
        $arrayMetadata = ['key' => 'value'];
        $record3 = new CallbackRecord(
            region: $region,
            type: $type,
            event: 'exit',
            state: 'done',
            callback: $callback,
            metadata: $arrayMetadata
        );
        $this->assertSame($arrayMetadata, $record3->metadata);
    }
}
