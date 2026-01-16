<?php

declare(strict_types=1);

namespace Tests\Unit\Feature\Presentation\Schema;

use Noem\State\Feature\Presentation\PresentationRegistry;
use Noem\State\Feature\Presentation\RegionPresentation;
use Noem\State\Feature\Presentation\SchemaNotFoundException;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criteria: PresentationRegistry.register() validates key against context.schema definitions
 * Intent: Ensures exposed field has corresponding schema entry, enforcing validation requirement
 * Criticality: contract
 */
final class ValidatesKeyAgainstSchemaTest extends TestCase
{
    public function testRegisterSucceedsWhenKeyExistsInSchema(): void
    {
        $registry = new PresentationRegistry();

        $registry->setSchemas([
            'validKey' => ['type' => 'string', 'description' => 'A valid key'],
        ]);

        $presentation = new RegionPresentation(
            key: 'validKey',
            label: 'Valid Label',
            intent: 'Valid Intent'
        );

        // Should succeed without throwing
        $registry->register($presentation);

        $this->assertNotNull($registry->get('validKey'));
    }

    public function testRegisterThrowsWhenKeyMissingFromSchema(): void
    {
        $this->expectException(SchemaNotFoundException::class);
        $this->expectExceptionMessage('invalidKey');

        $registry = new PresentationRegistry();

        $registry->setSchemas([
            'validKey' => ['type' => 'string'],
        ]);

        $presentation = new RegionPresentation(
            key: 'invalidKey',
            label: 'Label',
            intent: 'Intent'
        );

        // Should throw SchemaNotFoundException
        $registry->register($presentation);
    }

    public function testRegisterValidatesEachKeyIndependently(): void
    {
        $registry = new PresentationRegistry();

        $registry->setSchemas([
            'field1' => ['type' => 'string'],
            'field2' => ['type' => 'integer'],
        ]);

        // Register field1 - should succeed
        $presentation1 = new RegionPresentation(
            key: 'field1',
            label: 'Field 1',
            intent: 'First field'
        );

        $registry->register($presentation1);
        $this->assertNotNull($registry->get('field1'));

        // Register field2 - should succeed
        $presentation2 = new RegionPresentation(
            key: 'field2',
            label: 'Field 2',
            intent: 'Second field'
        );

        $registry->register($presentation2);
        $this->assertNotNull($registry->get('field2'));

        // Register field3 - should fail
        $presentation3 = new RegionPresentation(
            key: 'field3',
            label: 'Field 3',
            intent: 'Third field'
        );

        $this->expectException(SchemaNotFoundException::class);
        $registry->register($presentation3);
    }
}
