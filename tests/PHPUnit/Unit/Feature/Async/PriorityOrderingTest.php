<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Async;

use Noem\State\Feature\Async\Priority;
use PHPUnit\Framework\TestCase;

/**
 * Validates Priority value ordering
 *
 * Acceptance Criteria: Priority values are ordered LOW < NORMAL < HIGH
 * Intent: Ensures priority semantics match intuitive expectations
 */
final class PriorityOrderingTest extends TestCase
{
    public function testLowIsLessThanNormal(): void
    {
        $this->assertLessThan(Priority::NORMAL->value, Priority::LOW->value);
    }

    public function testNormalIsLessThanHigh(): void
    {
        $this->assertLessThan(Priority::HIGH->value, Priority::NORMAL->value);
    }

    public function testLowIsLessThanHigh(): void
    {
        $this->assertLessThan(Priority::HIGH->value, Priority::LOW->value);
    }
}
