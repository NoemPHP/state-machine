<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Async;

use Noem\State\Feature\Async\AsyncConfig;
use PHPUnit\Framework\TestCase;

/**
 * Validates AsyncConfig singleton flag
 *
 * Acceptance Criteria: AsyncConfig accepts singleton boolean flag
 * Intent: Prevents concurrent task execution, ensuring only one instance runs at a time
 */
final class AsyncConfigSingletonTest extends TestCase
{
    public function testAsyncConfigAcceptsSingletonTrue(): void
    {
        $config = new AsyncConfig(singleton: true);

        $this->assertTrue($config->singleton);
    }

    public function testAsyncConfigAcceptsSingletonFalse(): void
    {
        $config = new AsyncConfig(singleton: false);

        $this->assertFalse($config->singleton);
    }

    public function testAsyncConfigSingletonDefaultsToFalse(): void
    {
        $config = new AsyncConfig();

        $this->assertFalse($config->singleton);
    }
}
