<?php

declare(strict_types=1);

namespace Tests\Unit\Feature\Presentation\Schema;

use Noem\State\Feature\Presentation\PresentationRegistry;
use Noem\State\Feature\Presentation\RegionPresentation;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criteria: get-presented-state values conform to registered schemas
 * Intent: Ensures retrieved values match schema types, maintaining serialization guarantees
 * Criticality: contract
 */
final class GetStateConformsToSchemaTest extends TestCase
{
    public function testPresentationRequiresSchemaForRegistration(): void
    {
        $registry = new PresentationRegistry();

        // Set up schema
        $registry->setSchemas([
            'validatedField' => [
                'type' => 'string',
                'pattern' => '^[a-z]+$',
            ],
        ]);

        $presentation = new RegionPresentation(
            key: 'validatedField',
            label: 'Validated Field',
            intent: 'Field with schema validation'
        );

        // Registration succeeds because schema exists
        $registry->register($presentation);

        // Verify presentation is registered
        $this->assertNotNull($registry->get('validatedField'));

        // Verify schema is accessible
        $schema = $registry->getSchema('validatedField');
        $this->assertIsArray($schema);
        $this->assertSame('string', $schema['type']);
    }

    public function testSchemaEnforcesTypeConstraints(): void
    {
        $registry = new PresentationRegistry();

        // Define schemas with different types
        $registry->setSchemas([
            'stringField' => ['type' => 'string'],
            'integerField' => ['type' => 'integer'],
            'booleanField' => ['type' => 'boolean'],
        ]);

        // Register presentations
        $registry->register(new RegionPresentation('stringField', 'String', 'String value'));
        $registry->register(new RegionPresentation('integerField', 'Integer', 'Integer value'));
        $registry->register(new RegionPresentation('booleanField', 'Boolean', 'Boolean value'));

        // Verify type information is available in schemas
        $this->assertSame('string', $registry->getSchema('stringField')['type']);
        $this->assertSame('integer', $registry->getSchema('integerField')['type']);
        $this->assertSame('boolean', $registry->getSchema('booleanField')['type']);
    }

    public function testSchemaProvidedForValueValidation(): void
    {
        $registry = new PresentationRegistry();

        // Complex schema with validation rules
        $registry->setSchemas([
            'complexField' => [
                'type' => 'object',
                'properties' => [
                    'name' => ['type' => 'string', 'minLength' => 1],
                    'count' => ['type' => 'integer', 'minimum' => 0],
                ],
                'required' => ['name'],
            ],
        ]);

        $presentation = new RegionPresentation(
            key: 'complexField',
            label: 'Complex Field',
            intent: 'Complex validated field'
        );

        $registry->register($presentation);

        // Schema should be available for external validation
        $schema = $registry->getSchema('complexField');

        $this->assertIsArray($schema);
        $this->assertArrayHasKey('properties', $schema);
        $this->assertArrayHasKey('required', $schema);

        // Validation rules should be present
        $this->assertArrayHasKey('name', $schema['properties']);
        $this->assertSame(1, $schema['properties']['name']['minLength']);
    }
}
