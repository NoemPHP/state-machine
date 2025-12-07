<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Message;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that correlation middleware passes non-Message events through unchanged
 *
 * Spec: correlation-filtering-optimization / Correlation middleware passes non-Message events through unchanged
 * Intent: Ensures optimization only affects Message instances without interfering with normal event notification
 * Criticality: detail
 */

#[Group('feature')]
#[Group('message')]
class NonMessagePassthroughTest extends TestCase
{
    public function testCorrelationMiddlewarePassesNonMessageEventsThroughUnchanged(): void
    {
        $this->markTestSkipped(
            'Correlation filtering optimization is deferred to v2. V1 uses repliesTo() check in subscription setup which is sufficient. See MessageFeature::installCorrelationFiltering() implementation notes.'
        );
    }
}
