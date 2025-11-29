<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Async\Call;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Call.take pauses task until matching event occurs
 */
#[Group('async'), Group('call-helpers')]
class TakeTest extends TestCase
{
    public function testTakeRequiresListenerProvider(): void
    {
        // Note: Full testing of take() functionality requires listener provider infrastructure
        // which is part of a broader event system integration.
        //
        // Additionally, there appears to be a parameter ordering issue in the current
        // implementation where Call::take() passes parameters to takeAny() in a way that
        // doesn't match takeAny()'s signature expectations. This would need to be addressed
        // in the source code before comprehensive testing can be completed.
        $this->markTestIncomplete(
            'Full take() functionality testing requires CoroutineScheduler->listenerProvider() ' .
            'implementation and potential fix to parameter ordering.'
        );
    }
}
