<?php

declare(strict_types=1);

namespace Tests\Unit\Feature\Presentation\Registry;

use Noem\State\Feature\Presentation\PresentationRegistry;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criteria: PresentationRegistry.get() returns null when key does not exist
 * Intent: Provides safe lookup without exceptions, enabling existence checks before access
 * Criticality: contract
 */
final class GetReturnsNullTest extends TestCase
{
    public function testGetReturnsNullWhenKeyNotFound(): void
    {
        $registry = new PresentationRegistry();

        $result = $registry->get('nonexistentKey');

        $this->assertNull($result);
    }
}
