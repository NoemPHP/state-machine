<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Async;

use Noem\State\Feature\Async\AsyncConfig;
use PHPUnit\Framework\TestCase;

/**
 * Validates AsyncConfig debounce duration
 *
 * Acceptance Criteria: AsyncConfig accepts debounce duration in seconds
 * Intent: Enables delayed execution until trigger activity stops, supporting debounced user interactions
 */
final class AsyncConfigDebounceTest extends TestCase
{
    public function testAsyncConfigAcceptsDebounceFloat(): void
    {
        $config = new AsyncConfig(debounce: 0.5);

        $this->assertSame(0.5, $config->debounce);
    }

    public function testAsyncConfigDebounceCanBeNull(): void
    {
        $config = new AsyncConfig(debounce: null);

        $this->assertNull($config->debounce);
    }

    public function testAsyncConfigDebounceCanBeZero(): void
    {
        $config = new AsyncConfig(debounce: 0.0);

        $this->assertSame(0.0, $config->debounce);
    }
}
