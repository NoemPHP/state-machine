<?php

declare(strict_types=1);

namespace Tests\Integration\Feature\JsonSchema;

use JsonSchema\Validator;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criteria: Complete workflow from schema definition to validation enforcement
 * Intent: Validates schema definitions used for runtime validation in abilities
 * Criticality: integration
 */
final class SchemaToValidationWorkflowTest extends TestCase
{
    public function testCompleteSchemaToValidationWorkflow(): void
    {
        // Define schema
        $schema = (object)[
            'type' => 'object',
            'properties' => (object)[
                'username' => (object)['type' => 'string'],
                'age' => (object)['type' => 'integer', 'minimum' => 0],
            ],
            'required' => ['username'],
        ];

        // Valid data
        $validData = (object)['username' => 'john', 'age' => 30];

        // Invalid data
        $invalidData = (object)['age' => -5]; // missing username, invalid age

        // Validate valid data
        $validator = new Validator();
        $validator->validate($validData, $schema);
        $this->assertTrue($validator->isValid(), 'Valid data should pass validation');

        // Validate invalid data
        $validator2 = new Validator();
        $validator2->validate($invalidData, $schema);
        $this->assertFalse($validator2->isValid(), 'Invalid data should fail validation');
        $this->assertNotEmpty($validator2->getErrors(), 'Errors should be available');
    }
}
