<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Async;

use Noem\State\Feature\Async\AsyncConfig;
use Noem\State\Feature\Async\Priority;
use PHPUnit\Framework\TestCase;

/**
 * Validates AsyncConfig priority
 *
 * Acceptance Criteria: AsyncConfig accepts Priority enum value
 * Intent: Enables priority-based scheduling, allowing critical tasks to execute more frequently
 */
final class AsyncConfigPriorityTest extends TestCase
{
    public function testAsyncConfigAcceptsPriorityLow(): void
    {
        $config = new AsyncConfig(priority: Priority::LOW);

        $this->assertSame(Priority::LOW, $config->priority);
    }

    public function testAsyncConfigAcceptsPriorityNormal(): void
    {
        $config = new AsyncConfig(priority: Priority::NORMAL);

        $this->assertSame(Priority::NORMAL, $config->priority);
    }

    public function testAsyncConfigAcceptsPriorityHigh(): void
    {
        $config = new AsyncConfig(priority: Priority::HIGH);

        $this->assertSame(Priority::HIGH, $config->priority);
    }

    public function testAsyncConfigPriorityDefaultsToNormal(): void
    {
        $config = new AsyncConfig();

        $this->assertSame(Priority::NORMAL, $config->priority);
    }
}
