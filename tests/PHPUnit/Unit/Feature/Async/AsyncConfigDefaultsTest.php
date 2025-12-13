<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Async;

use Noem\State\Feature\Async\AsyncConfig;
use Noem\State\Feature\Async\Priority;
use PHPUnit\Framework\TestCase;

/**
 * Validates AsyncConfig default values
 *
 * Acceptance Criteria: AsyncConfig provides sensible defaults when options are null
 * Intent: Enables minimal configuration while supporting full customization when needed
 */
final class AsyncConfigDefaultsTest extends TestCase
{
    public function testAsyncConfigDefaultsDebounceToNull(): void
    {
        $config = new AsyncConfig();

        $this->assertNull($config->debounce);
    }

    public function testAsyncConfigDefaultsThrottleToNull(): void
    {
        $config = new AsyncConfig();

        $this->assertNull($config->throttle);
    }

    public function testAsyncConfigDefaultsSingletonToFalse(): void
    {
        $config = new AsyncConfig();

        $this->assertFalse($config->singleton);
    }

    public function testAsyncConfigDefaultsPriorityToNormal(): void
    {
        $config = new AsyncConfig();

        $this->assertSame(Priority::NORMAL, $config->priority);
    }

    public function testAsyncConfigDefaultsTimeoutToNull(): void
    {
        $config = new AsyncConfig();

        $this->assertNull($config->timeout);
    }
}
