<?php

declare(strict_types=1);

namespace Tests\Unit\Feature\Presentation\Registry;

use Noem\State\Feature\Presentation\PresentationRegistry;
use Noem\State\Feature\Presentation\RegionPresentation;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criteria: PresentationRegistry.unregister() removes presentation by key
 * Intent: Deletes presentation from internal storage enabling cleanup
 * Criticality: contract
 */
final class UnregisterRemovesByKeyTest extends TestCase
{
    public function testUnregisterRemovesPresentation(): void
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
