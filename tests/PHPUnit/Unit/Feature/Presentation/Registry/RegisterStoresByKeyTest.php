<?php

declare(strict_types=1);

namespace Tests\Unit\Feature\Presentation\Registry;

use Noem\State\Feature\Presentation\PresentationRegistry;
use Noem\State\Feature\Presentation\RegionPresentation;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criteria: PresentationRegistry.register() stores RegionPresentation indexed by key
 * Intent: Enables direct lookup by field key during enumeration and value retrieval
 * Criticality: contract
 */
final class RegisterStoresByKeyTest extends TestCase
{
    public function testRegisterStoresPresentationByKey(): void
    {
        $registry = new PresentationRegistry();

        // Set up schema for validation
        $registry->setSchemas(['userCount' => ['type' => 'integer']]);

        $presentation = new RegionPresentation(
            key: 'userCount',
            label: 'Active Users',
            intent: 'Number of logged-in users'
        );

        $registry->register($presentation);

        $retrieved = $registry->get('userCount');
        $this->assertSame($presentation, $retrieved);
    }
}
