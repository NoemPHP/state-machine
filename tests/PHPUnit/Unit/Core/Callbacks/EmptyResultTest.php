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
class TestCallbackTypeForEmpty extends CallbackType
{
}

/**
 * Test: CallbackRegistry returns empty array when no callbacks match query
 *
 * Intent: Provides consistent return type for non-matching queries, simplifying
 * consumer code with null-safe iteration
 */
#[CoversClass(CallbackRegistry::class)]
final class EmptyResultTest extends TestCase
{
    public function testQueryReturnsEmptyArrayWhenNoMatches(): void
    {
        $registry = new CallbackRegistry();
        $region = $this->createMock(Region::class);
        $type = TestCallbackTypeForEmpty::get();

        $record = new CallbackRecord(
            region: $region,
            type: $type,
            event: 'action',
            state: 'idle',
            callback: fn() => null
        );

        $registry->register($record);

        // Query with non-matching criteria
        $results = $registry->query(state: 'nonexistent');
        $this->assertIsArray($results);
        $this->assertEmpty($results);
    }

    public function testEmptyRegistryReturnsEmptyArray(): void
    {
        $registry = new CallbackRegistry();

        $results = $registry->query();
        $this->assertIsArray($results);
        $this->assertEmpty($results);
    }

    public function testQueryWithNoMatchingRegionReturnsEmpty(): void
    {
        $registry = new CallbackRegistry();
        $region1 = $this->createMock(Region::class);
        $region2 = $this->createMock(Region::class);
        $type = TestCallbackTypeForEmpty::get();

        $record = new CallbackRecord(
            region: $region1,
            type: $type,
            event: 'action',
            state: 'idle',
            callback: fn() => null
        );

        $registry->register($record);

        $results = $registry->query(region: $region2);
        $this->assertIsArray($results);
        $this->assertEmpty($results);
    }
}
