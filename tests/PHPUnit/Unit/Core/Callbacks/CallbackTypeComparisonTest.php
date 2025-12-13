<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Core\Callbacks;

use Noem\State\Callbacks\CallbackType;
use PHPUnit\Framework\TestCase;

// Test doubles for comparison
class ComparisonTypeA extends CallbackType
{
}

class ComparisonTypeB extends CallbackType
{
}

/**
 * Test: CallbackType instances can be compared using is() method
 *
 * Intent: Enables type-safe comparisons when filtering callbacks by channel type
 */
class CallbackTypeComparisonTest extends TestCase
{
    public function testIsMethodComparesWithSameType(): void
    {
        $instance = ComparisonTypeA::get();

        $this->assertTrue(
            $instance->is(ComparisonTypeA::class),
            'is() must return true when comparing with same type class'
        );

        $this->assertTrue(
            $instance->is(CallbackType::class),
            'is() must return true when comparing with parent type'
        );
    }

    public function testIsMethodComparesWithDifferentType(): void
    {
        $instance = ComparisonTypeA::get();

        $this->assertFalse(
            $instance->is(ComparisonTypeB::class),
            'is() must return false when comparing with different type'
        );
    }

    public function testIsMethodComparesWithInstance(): void
    {
        $instanceA = ComparisonTypeA::get();
        $instanceB = ComparisonTypeB::get();

        $this->assertTrue(
            $instanceA->is($instanceA),
            'is() must return true when comparing with itself'
        );

        $this->assertFalse(
            $instanceA->is($instanceB),
            'is() must return false when comparing with different instance'
        );
    }
}
