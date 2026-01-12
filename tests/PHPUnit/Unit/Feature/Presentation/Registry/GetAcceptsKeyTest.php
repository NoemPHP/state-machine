<?php

declare(strict_types=1);

namespace Tests\Unit\Feature\Presentation\Registry;

use Noem\State\Feature\Presentation\PresentationRegistry;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criteria: PresentationRegistry.get() accepts string key parameter
 * Intent: Provides type-safe lookup API for retrieving specific presentation definitions
 * Criticality: contract
 */
final class GetAcceptsKeyTest extends TestCase
{
    public function testGetAcceptsStringKey(): void
    {
        $registry = new PresentationRegistry();

        // Should not throw type error
        $result = $registry->get('someKey');

        $this->assertNull($result); // Key doesn't exist, so returns null
    }
}
