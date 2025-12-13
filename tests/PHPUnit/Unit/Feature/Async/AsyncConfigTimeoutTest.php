<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Async;

use Noem\State\Feature\Async\AsyncConfig;
use PHPUnit\Framework\TestCase;

/**
 * Validates AsyncConfig timeout duration
 *
 * Acceptance Criteria: AsyncConfig accepts timeout duration in seconds
 * Intent: Prevents runaway tasks, ensuring async operations complete within time bounds
 */
final class AsyncConfigTimeoutTest extends TestCase
{
    public function testAsyncConfigAcceptsTimeoutFloat(): void
    {
        $config = new AsyncConfig(timeout: 5.0);

        $this->assertSame(5.0, $config->timeout);
    }

    public function testAsyncConfigTimeoutCanBeNull(): void
    {
        $config = new AsyncConfig(timeout: null);

        $this->assertNull($config->timeout);
    }
}
