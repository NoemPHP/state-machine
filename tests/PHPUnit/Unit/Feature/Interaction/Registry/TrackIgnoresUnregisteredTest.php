<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Interaction\Registry;

use Noem\State\Feature\Interaction\InteractionRegistry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(InteractionRegistry::class)]
class TrackIgnoresUnregisteredTest extends TestCase
{
    public function testTrackTriggeredIgnoresUnregisteredInteractionIds(): void
    {
        $registry = new InteractionRegistry();

        // Try to track unregistered interaction
        $registry->trackTriggered('unregistered_interaction');

        $triggered = $registry->getTriggered();

        $this->assertEmpty($triggered);
    }

    public function testSilentlySkipsTrackingForDynamicInteractionsNotInRegistry(): void
    {
        $registry = new InteractionRegistry();

        // Should not throw exception
        $registry->trackTriggered('dynamic_interaction_1');
        $registry->trackTriggered('dynamic_interaction_2');

        $this->assertTrue(true); // No exception thrown
    }
}
