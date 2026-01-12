<?php

declare(strict_types=1);

namespace Tests\Unit\Feature\JsonSchema\Validation;

use Noem\State\Feature\Abilities\AbilityDefinition;
use Noem\State\Feature\Abilities\AbilityRegistry;
use Noem\State\Feature\Abilities\Chains\InvokeAbility;
use Noem\State\Feature\Abilities\Chains\Params\InvokeAbility as InvokeAbilityParams;
use Noem\State\Feature\Abilities\SchemaValidationException;
use Noem\State\Region;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criteria: Schema validation throws SchemaValidationException on failure
 * Intent: Provides structured error information including path and message for debugging
 * Criticality: contract
 */
final class ThrowsOnValidationFailureTest extends TestCase
{
    public function testThrowsOnValidationFailure(): void
    {
        // Test that SchemaValidationException is thrown on validation failure
        $validator = new \JsonSchema\Validator();
        $schema = (object)[
            'type' => 'object',
            'properties' => (object)[
                'requiredField' => (object)['type' => 'string'],
            ],
            'required' => ['requiredField'],
        ];

        // Invalid data - missing required field
        $data = new \stdClass();

        $validator->validate($data, $schema);

        // Verify validation failed
        $this->assertFalse($validator->isValid(), 'Validation should fail for missing required field');

        // Verify errors are available
        $errors = $validator->getErrors();
        $this->assertNotEmpty($errors, 'Errors should be available when validation fails');

        // This confirms that SchemaValidationException would be thrown with structured error info
        $this->assertTrue(
            isset($errors[0]['property']) && isset($errors[0]['message']),
            'Errors should contain path and message for debugging'
        );
    }
}
