<?php

declare(strict_types=1);

namespace Tests\Unit\Feature\Presentation\Registry;

use Noem\State\Feature\Presentation\PresentationRegistry;
use Noem\State\Feature\Presentation\RegionPresentation;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criteria: PresentationRegistry.get() returns null after unregister
 * Intent: Validates removal by returning null for previously registered keys
 * Criticality: contract
 */
final class GetReturnsNullAfterUnregisterTest extends TestCase
{
    public function testGetReturnsNullAfterUnregister(): void
    {
        $registry = new PresentationRegistry();
        $registry->setSchemas(['key' => ['type' => 'string']]);

        $presentation = new RegionPresentation('key', 'Label', 'Intent');
        $registry->register($presentation);

        $this->assertNotNull($registry->get('key'));

        $registry->unregister('key');

        $this->assertNull($registry->get('key'));
    }
}
