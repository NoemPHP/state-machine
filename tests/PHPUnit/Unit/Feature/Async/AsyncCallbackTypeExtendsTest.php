<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Async;

use Noem\State\Callbacks\CallbackType;
use Noem\State\Feature\Async\AsyncCallbackType;
use PHPUnit\Framework\TestCase;

/**
 * Validates AsyncCallbackType extends CallbackType
 *
 * Acceptance Criteria: AsyncCallbackType extends CallbackType
 * Intent: Defines dedicated channel for async callbacks, enabling separate code paths for async execution
 */
final class AsyncCallbackTypeExtendsTest extends TestCase
{
    public function testAsyncCallbackTypeExtendsCallbackType(): void
    {
        $instance = AsyncCallbackType::get();

        $this->assertInstanceOf(CallbackType::class, $instance);
    }
}
