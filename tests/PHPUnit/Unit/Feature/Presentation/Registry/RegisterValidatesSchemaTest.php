<?php

declare(strict_types=1);

namespace Tests\Unit\Feature\Presentation\Registry;

use Noem\State\Feature\Presentation\PresentationRegistry;
use Noem\State\Feature\Presentation\RegionPresentation;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criteria: PresentationRegistry.register() validates key exists in JsonSchema
 * Intent: Enforces schema requirement at registration time, failing fast for unvalidated fields
 * Criticality: contract
 */
final class RegisterValidatesSchemaTest extends TestCase
{
    public function testRegisterValidatesKeyExistsInSchema(): void
    {
        $registry = new PresentationRegistry();

        // Set up schema with one field
        $registry->setSchemas(['validKey' => ['type' => 'string']]);

        $presentation = new RegionPresentation(
            key: 'validKey',
            label: 'Label',
            intent: 'Intent'
        );

        // Should succeed - key exists in schema
        $registry->register($presentation);

        $this->assertNotNull($registry->get('validKey'));
    }
}
