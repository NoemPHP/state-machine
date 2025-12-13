<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Async;

use Noem\State\Feature\Async\AsyncConfig;
use PHPUnit\Framework\TestCase;

/**
 * Validates AsyncConfig throttle validation
 *
 * Acceptance Criteria: AsyncConfig validates throttle is non-negative
 * Intent: Prevents invalid configuration, ensuring throttle values make semantic sense
 */
final class ThrottleValidationTest extends TestCase
{
    public function testThrottleRejectsNegativeValue(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Throttle must be >= 0');

        new AsyncConfig(throttle: -1.0);
    }

    public function testThrottleAcceptsZero(): void
    {
        $config = new AsyncConfig(throttle: 0.0);

        $this->assertSame(0.0, $config->throttle);
    }

    public function testThrottleAcceptsPositiveValue(): void
    {
        $config = new AsyncConfig(throttle: 1.0);

        $this->assertSame(1.0, $config->throttle);
    }
}
