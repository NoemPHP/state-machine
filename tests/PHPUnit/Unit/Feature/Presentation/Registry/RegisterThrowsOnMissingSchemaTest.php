<?php

declare(strict_types=1);

namespace Tests\Unit\Feature\Presentation\Registry;

use Noem\State\Feature\Presentation\PresentationRegistry;
use Noem\State\Feature\Presentation\RegionPresentation;
use Noem\State\Feature\Presentation\SchemaNotFoundException;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criteria: PresentationRegistry.register() throws SchemaNotFoundException when key missing from schema
 * Intent: Provides clear error messaging when attempting to expose unvalidated context fields
 * Criticality: contract
 */
final class RegisterThrowsOnMissingSchemaTest extends TestCase
{
    public function testRegisterThrowsWhenKeyNotInSchema(): void
    {
        $registry = new PresentationRegistry();

        // Set up schema WITHOUT the key we'll try to register
        $registry->setSchemas(['someOtherKey' => ['type' => 'string']]);

        $presentation = new RegionPresentation(
            key: 'missingKey',
            label: 'Label',
            intent: 'Intent'
        );

        $this->expectException(SchemaNotFoundException::class);
        $this->expectExceptionMessage('missingKey');

        $registry->register($presentation);
    }
}
