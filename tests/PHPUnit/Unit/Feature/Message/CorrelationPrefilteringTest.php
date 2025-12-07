<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Message;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that correlation middleware pre-filters Message events by correlation ID before type filtering
 *
 * Spec: correlation-filtering-optimization / Correlation middleware pre-filters Message events by correlation ID before type filtering
 * Intent: Optimizes message delivery by checking correlation IDs early, skipping type inspection for mismatched messages
 * Criticality: detail
 */

#[Group('feature')]
#[Group('message')]
class CorrelationPrefilteringTest extends TestCase
{
    public function testCorrelationMiddlewarePrefiltersMessageEventsByCorrelationIdBeforeTypeFiltering(): void
    {
        $this->markTestSkipped(
            'Correlation filtering optimization is deferred to v2. V1 uses repliesTo() check in subscription setup which is sufficient. See MessageFeature::installCorrelationFiltering() implementation notes.'
        );
    }
}
