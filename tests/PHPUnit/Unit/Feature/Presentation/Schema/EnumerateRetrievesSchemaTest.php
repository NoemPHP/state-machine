<?php

declare(strict_types=1);

namespace Tests\Unit\Feature\Presentation\Schema;

use Noem\State\Feature\Presentation\PresentationRegistry;
use Noem\State\Feature\Presentation\RegionPresentation;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criteria: Schema inclusion in enumeration retrieves definitions from JsonSchemaFeature
 * Intent: Provides complete schema objects for external agent validation workflows
 * Criticality: contract
 */
final class EnumerateRetrievesSchemaTest extends TestCase
{
    public function testEnumerationRetrievesCompleteSchemaObject(): void
    {
        $registry = new PresentationRegistry();

        $completeSchema = [
            'type' => 'object',
            'properties' => [
                'name' => ['type' => 'string'],
                'age' => ['type' => 'integer'],
            ],
            'required' => ['name'],
        ];

        $registry->setSchemas([
            'userInfo' => $completeSchema,
        ]);

        $presentation = new RegionPresentation(
            key: 'userInfo',
            label: 'User Information',
            intent: 'Complete user profile'
        );

        $registry->register($presentation);

        $retrievedSchema = $registry->getSchema('userInfo');

        // Verify complete schema object is retrieved
        $this->assertSame($completeSchema, $retrievedSchema);
        $this->assertArrayHasKey('properties', $retrievedSchema);
        $this->assertArrayHasKey('required', $retrievedSchema);
    }

    public function testSchemaRetrievalForMultiplePresentations(): void
    {
        $registry = new PresentationRegistry();

        $schemas = [
            'stringField' => [
                'type' => 'string',
                'maxLength' => 100,
            ],
            'numberField' => [
                'type' => 'number',
                'minimum' => 0,
                'maximum' => 100,
            ],
            'arrayField' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
            ],
        ];

        $registry->setSchemas($schemas);

        $registry->register(new RegionPresentation('stringField', 'String', 'A string'));
        $registry->register(new RegionPresentation('numberField', 'Number', 'A number'));
        $registry->register(new RegionPresentation('arrayField', 'Array', 'An array'));

        // Each schema should be independently retrievable
        $this->assertSame($schemas['stringField'], $registry->getSchema('stringField'));
        $this->assertSame($schemas['numberField'], $registry->getSchema('numberField'));
        $this->assertSame($schemas['arrayField'], $registry->getSchema('arrayField'));
    }
}
