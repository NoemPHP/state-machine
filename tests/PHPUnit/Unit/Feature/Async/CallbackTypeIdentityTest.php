<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Async;

use Noem\State\Feature\Async\AsyncCallbackType;
use Noem\State\Feature\Async\SyncCallbackType;
use PHPUnit\Framework\TestCase;

/**
 * Validates CallbackType distinct identities
 *
 * Acceptance Criteria: AsyncCallbackType and SyncCallbackType have distinct identities
 * Intent: Prevents type confusion, ensuring async and sync callbacks remain separate
 */
final class CallbackTypeIdentityTest extends TestCase
{
    public function testAsyncAndSyncCallbackTypesHaveDistinctIdentities(): void
    {
        $asyncType = AsyncCallbackType::get();
        $syncType = SyncCallbackType::get();

        $this->assertNotSame($asyncType, $syncType);
    }

    public function testAsyncCallbackTypeIsNotSyncCallbackType(): void
    {
        $asyncType = AsyncCallbackType::get();

        $this->assertFalse($asyncType->is(SyncCallbackType::class));
    }

    public function testSyncCallbackTypeIsNotAsyncCallbackType(): void
    {
        $syncType = SyncCallbackType::get();

        $this->assertFalse($syncType->is(AsyncCallbackType::class));
    }
}
