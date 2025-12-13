<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Async;

use Noem\State\Feature\Async\AsyncConfig;
use PHPUnit\Framework\TestCase;

/**
 * Validates AsyncConfig timeout validation
 *
 * Acceptance Criteria: AsyncConfig validates timeout is positive if specified
 * Intent: Prevents invalid configuration, ensuring timeout values are meaningful
 */
final class TimeoutValidationTest extends TestCase
{
    public function testTimeoutRejectsZero(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Timeout must be > 0');

        new AsyncConfig(timeout: 0.0);
    }

    public function testTimeoutRejectsNegativeValue(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Timeout must be > 0');

        new AsyncConfig(timeout: -5.0);
    }

    public function testTimeoutAcceptsPositiveValue(): void
    {
        $config = new AsyncConfig(timeout: 5.0);

        $this->assertSame(5.0, $config->timeout);
    }

    public function testTimeoutAcceptsNull(): void
    {
        $config = new AsyncConfig(timeout: null);

        $this->assertNull($config->timeout);
    }
}
