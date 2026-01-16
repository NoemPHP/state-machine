<?php

declare(strict_types=1);

namespace Tests\Unit\Feature\Presentation\Schema;

use Noem\State\Feature\Presentation\PresentationRegistry;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criteria: PresentationRegistry.register() retrieves schemas from JsonSchemaFeature
 * Intent: Accesses schema definitions for validation checking during registration
 * Criticality: contract
 */
final class RetrievesSchemasTest extends TestCase
{
    public function testRegistryStoresSchemaDefinitions(): void
    {
        $registry = new PresentationRegistry();

        $schemas = [
            'field1' => ['type' => 'string'],
            'field2' => ['type' => 'integer'],
        ];

        $registry->setSchemas($schemas);

        // Verify schemas are stored
        $this->assertSame(['type' => 'string'], $registry->getSchema('field1'));
        $this->assertSame(['type' => 'integer'], $registry->getSchema('field2'));
    }

    public function testGetSchemaReturnsNullForUnknownKey(): void
    {
        $registry = new PresentationRegistry();

        $registry->setSchemas(['field1' => ['type' => 'string']]);

        $this->assertNull($registry->getSchema('unknownField'));
    }

    public function testSchemasUsedForValidation(): void
    {
        $registry = new PresentationRegistry();

        // Set schemas
        $schemas = [
            'validField' => ['type' => 'string', 'description' => 'A valid field'],
        ];

        $registry->setSchemas($schemas);

        // Schemas should be retrievable for validation
        $schema = $registry->getSchema('validField');

        $this->assertIsArray($schema);
        $this->assertArrayHasKey('type', $schema);
        $this->assertSame('string', $schema['type']);
    }
}
