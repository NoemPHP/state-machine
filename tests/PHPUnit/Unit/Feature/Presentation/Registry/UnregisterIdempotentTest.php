<?php

declare(strict_types=1);

namespace Tests\Unit\Feature\Presentation\Registry;

use Noem\State\Feature\Presentation\PresentationRegistry;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criteria: PresentationRegistry.unregister() is idempotent
 * Intent: Multiple unregister calls for same key succeed without errors
 * Criticality: constraint
 */
final class UnregisterIdempotentTest extends TestCase
{
    public function testUnregisterIsIdempotent(): void
    {
        $registry = new PresentationRegistry();

        // Unregister nonexistent key - should not throw
        $registry->unregister('nonexistent');

        // Unregister same key again - should not throw
        $registry->unregister('nonexistent');

        $this->assertTrue(true); // No exception thrown
    }
}
