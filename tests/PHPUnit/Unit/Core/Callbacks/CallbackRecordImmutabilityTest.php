<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Core\Callbacks;

use Noem\State\Callbacks\CallbackRecord;
use Noem\State\Callbacks\CallbackType;
use Noem\State\Region;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

// Test double for CallbackType
class TestCallbackTypeForImmutability extends CallbackType
{
}

/**
 * Test: CallbackRecord properties are readonly and immutable
 *
 * Intent: Prevents accidental modification after registration, ensuring registry
 * integrity and predictable behavior
 */
#[CoversClass(CallbackRecord::class)]
final class CallbackRecordImmutabilityTest extends TestCase
{
    public function testCallbackRecordPropertiesAreReadonly(): void
    {
        $region = $this->createMock(Region::class);
        $type = TestCallbackTypeForImmutability::get();
        $callback = fn() => null;

        $record = new CallbackRecord(
            region: $region,
            type: $type,
            event: 'action',
            state: 'idle',
            callback: $callback
        );

        // Verify that attempting to modify readonly properties fails
        // This is a compile-time check in PHP 8.4+, but we can verify the property declarations
        $reflectionClass = new \ReflectionClass($record);

        $regionProp = $reflectionClass->getProperty('region');
        $this->assertTrue($regionProp->isReadOnly(), 'region property should be readonly');

        $typeProp = $reflectionClass->getProperty('type');
        $this->assertTrue($typeProp->isReadOnly(), 'type property should be readonly');

        $eventProp = $reflectionClass->getProperty('event');
        $this->assertTrue($eventProp->isReadOnly(), 'event property should be readonly');

        $stateProp = $reflectionClass->getProperty('state');
        $this->assertTrue($stateProp->isReadOnly(), 'state property should be readonly');

        $callbackProp = $reflectionClass->getProperty('callback');
        $this->assertTrue($callbackProp->isReadOnly(), 'callback property should be readonly');

        $metadataProp = $reflectionClass->getProperty('metadata');
        $this->assertTrue($metadataProp->isReadOnly(), 'metadata property should be readonly');
    }
}
