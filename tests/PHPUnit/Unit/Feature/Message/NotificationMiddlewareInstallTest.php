<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Message;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that MessageFeature installs middleware on NotificationChain for Message event optimization
 *
 * Spec: correlation-filtering-optimization / MessageFeature installs middleware on NotificationChain for Message event optimization
 * Intent: Adds correlation-aware filtering layer to avoid invoking listeners for unmatched Message events
 * Criticality: detail
 */

#[Group('feature')]
#[Group('message')]
class NotificationMiddlewareInstallTest extends TestCase
{
    public function testMessageFeatureInstallsMiddlewareOnNotificationChainForMessageEventOptimization(): void
    {
        $this->markTestSkipped(
            'Correlation filtering optimization is deferred to v2. V1 uses repliesTo() check in subscription setup which is sufficient. See MessageFeature::installCorrelationFiltering() implementation notes.'
        );
    }
}
