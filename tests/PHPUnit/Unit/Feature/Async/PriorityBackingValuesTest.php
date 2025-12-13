<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Async;

use Noem\State\Feature\Async\Priority;
use PHPUnit\Framework\TestCase;

/**
 * Validates Priority enum backing values
 *
 * Acceptance Criteria: Priority enum has integer backing values (LOW=1, NORMAL=5, HIGH=10)
 * Intent: Enables arithmetic operations for scheduler tick budget calculations
 */
final class PriorityBackingValuesTest extends TestCase
{
    public function testLowPriorityHasBackingValue1(): void
    {
        $this->assertSame(1, Priority::LOW->value);
    }

    public function testNormalPriorityHasBackingValue5(): void
    {
        $this->assertSame(5, Priority::NORMAL->value);
    }

    public function testHighPriorityHasBackingValue10(): void
    {
        $this->assertSame(10, Priority::HIGH->value);
    }
}
