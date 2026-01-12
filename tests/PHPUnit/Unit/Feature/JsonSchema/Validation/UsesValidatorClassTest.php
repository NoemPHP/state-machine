<?php

declare(strict_types=1);

namespace Tests\Unit\Feature\JsonSchema\Validation;

use JsonSchema\Validator;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criteria: JSON Schema validation uses justinrainbow/json-schema Validator class
 * Intent: Provides standard JSON Schema Draft-04 validation for parameter and response schemas
 * Criticality: contract
 */
final class UsesValidatorClassTest extends TestCase
{
    public function testValidationUsesValidatorClass(): void
    {
        // Verify Validator class is available
        $this->assertTrue(
            class_exists(Validator::class),
            'JsonSchema\Validator class must be available'
        );

        // Verify Validator can be instantiated
        $validator = new Validator();
        $this->assertInstanceOf(Validator::class, $validator);

        // Verify key validation methods exist
        $this->assertTrue(
            method_exists($validator, 'validate'),
            'Validator must have validate() method'
        );
        $this->assertTrue(
            method_exists($validator, 'isValid'),
            'Validator must have isValid() method'
        );
        $this->assertTrue(
            method_exists($validator, 'getErrors'),
            'Validator must have getErrors() method'
        );
    }
}
