<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Async;

use Noem\State\Feature\Async\AsyncConfig;
use PHPUnit\Framework\TestCase;

/**
 * Validates AsyncConfig throttle duration
 *
 * Acceptance Criteria: AsyncConfig accepts throttle duration in seconds
 * Intent: Limits execution frequency, preventing excessive API calls or resource usage
 */
final class AsyncConfigThrottleTest extends TestCase
{
    public function testAsyncConfigAcceptsThrottleFloat(): void
    {
        $config = new AsyncConfig(throttle: 1.0);

        $this->assertSame(1.0, $config->throttle);
    }

    public function testAsyncConfigThrottleCanBeNull(): void
    {
        $config = new AsyncConfig(throttle: null);

        $this->assertNull($config->throttle);
    }

    public function testAsyncConfigThrottleCanBeZero(): void
    {
        $config = new AsyncConfig(throttle: 0.0);

        $this->assertSame(0.0, $config->throttle);
    }
}
