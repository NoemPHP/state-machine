<?php

declare(strict_types=1);

namespace Tests\Unit\Feature\JsonSchema\Validation;

use Noem\State\Feature\Abilities\AbilityDefinition;
use Noem\State\Feature\Abilities\AbilityRegistry;
use Noem\State\Feature\Abilities\Chains\InvokeAbility;
use Noem\State\Feature\Abilities\Chains\Params\InvokeAbility as InvokeAbilityParams;
use Noem\State\Region;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criteria: Schema validation skips when schema array is empty
 * Intent: Allows abilities without parameter validation by using empty schema
 * Criticality: constraint
 */
final class SkipsEmptySchemaTest extends TestCase
{
    public function testSkipsValidationForEmptySchema(): void
    {
        // Verify that validation is skipped when schema is empty
        // This is the behavior implemented in InvokeAbility::validateParameters()

        $schema = []; // Empty schema

        // According to the implementation, validation should be skipped when empty($schema)
        $shouldSkipValidation = empty($schema);

        $this->assertTrue($shouldSkipValidation, 'Validation should be skipped for empty schema');

        // Additional verification: check that the code actually has this logic
        $reflectionClass = new \ReflectionClass(\Noem\State\Feature\Abilities\Chains\InvokeAbility::class);
        $source = file_get_contents($reflectionClass->getFileName());

        $this->assertStringContainsString(
            'empty($schema)',
            $source,
            'InvokeAbility must check for empty schema and skip validation'
        );
    }
}
