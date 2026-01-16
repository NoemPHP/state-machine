<?php

declare(strict_types=1);

namespace Tests\Unit\Feature\Presentation\Registry;

use Noem\State\Feature\Presentation\PresentationRegistry;
use Noem\State\Feature\Presentation\RegionPresentation;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criteria: PresentationRegistry.register() accepts RegionPresentation parameter
 * Intent: Provides type-safe registration API for presentation contracts
 * Criticality: contract
 */
final class RegisterAcceptsPresentationTest extends TestCase
{
    public function testRegisterAcceptsRegionPresentation(): void
    {
        $registry = new PresentationRegistry();

        // Set up schema context (required for registration)
        $registry->setSchemas(['testKey' => ['type' => 'string']]);

        $presentation = new RegionPresentation(
            key: 'testKey',
            label: 'Test Label',
            intent: 'Test Intent'
        );

        // Should accept RegionPresentation without error
        $registry->register($presentation);

        // Verify registration succeeded
        $this->assertSame($presentation, $registry->get('testKey'));
    }
}
