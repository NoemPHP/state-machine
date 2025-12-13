<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Async;

use Noem\State\Feature\Async\AsyncCallbackType;
use PHPUnit\Framework\TestCase;

/**
 * Validates CallbackType singleton behavior
 *
 * Acceptance Criteria: AsyncCallbackType is singleton instance
 * Intent: Ensures type identity for efficient channel filtering and type comparisons
 */
final class CallbackTypeSingletonsTest extends TestCase
{
    public function testAsyncCallbackTypeReturnsSameSingletonInstance(): void
    {
        $instance1 = AsyncCallbackType::get();
        $instance2 = AsyncCallbackType::get();

        $this->assertSame($instance1, $instance2);
    }
}
