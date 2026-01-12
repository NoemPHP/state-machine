<?php

declare(strict_types=1);

namespace Tests\Unit\Feature\Presentation\Registry;

use Noem\State\Feature\Presentation\PresentationRegistry;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criteria: PresentationRegistry.unregister() accepts string key parameter
 * Intent: Provides type-safe removal API matching registration pattern
 * Criticality: contract
 */
final class UnregisterAcceptsKeyTest extends TestCase
{
    public function testUnregisterAcceptsStringKey(): void
    {
        $registry = new PresentationRegistry();

        // Should not throw type error
        $registry->unregister('someKey');

        $this->assertTrue(true); // Method accepted string parameter
    }
}
