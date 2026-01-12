<?php

declare(strict_types=1);

namespace Tests\Unit\Feature\Presentation\Registry;

use Noem\State\Feature\Presentation\PresentationRegistry;
use Noem\State\Feature\Presentation\RegionPresentation;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criteria: PresentationRegistry.register() overwrites existing presentation with same key
 * Intent: Allows presentation redefinition with explicit overwrite semantics, supporting runtime customization
 * Criticality: constraint
 */
final class RegisterOverwritesTest extends TestCase
{
    public function testRegisterOverwritesExistingPresentation(): void
    {
        $registry = new PresentationRegistry();
        $registry->setSchemas(['key' => ['type' => 'string']]);

        $first = new RegionPresentation('key', 'First Label', 'First Intent');
        $second = new RegionPresentation('key', 'Second Label', 'Second Intent');

        $registry->register($first);
        $registry->register($second);

        $retrieved = $registry->get('key');

        $this->assertSame($second, $retrieved);
        $this->assertNotSame($first, $retrieved);
    }
}
