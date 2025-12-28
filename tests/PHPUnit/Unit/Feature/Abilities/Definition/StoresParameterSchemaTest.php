<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Abilities\Definition;

use Noem\State\Feature\Abilities\AbilityDefinition;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: AbilityDefinition stores parameterSchema as readonly array
 *
 * Intent: Provides JSON Schema for parameter validation,
 * enabling schema-validated invocations and documentation generation
 *
 * Criticality: contract
 */
#[Group('abilities')]
#[Group('ability-definition')]
class StoresParameterSchemaTest extends TestCase
{
    public function testStoresParameterSchemaAsReadonlyArray(): void
    {
        $name = 'test-ability';
        $description = 'Test ability description';
        $parameterSchema = [
            'type' => 'object',
            'properties' => [
                'name' => ['type' => 'string'],
                'age' => ['type' => 'integer'],
            ],
            'required' => ['name'],
        ];
        $responseSchema = ['type' => 'object'];
        $handler = fn(mixed $params): mixed => $params;

        $definition = new AbilityDefinition(
            name: $name,
            description: $description,
            parameterSchema: $parameterSchema,
            responseSchema: $responseSchema,
            handler: $handler
        );

        // Verify parameterSchema is stored and accessible
        $this->assertSame($parameterSchema, $definition->parameterSchema);

        // Verify it's an array
        $this->assertIsArray($definition->parameterSchema);

        // Verify readonly behavior - should return same value on multiple accesses
        $firstAccess = $definition->parameterSchema;
        $secondAccess = $definition->parameterSchema;
        $this->assertSame($firstAccess, $secondAccess, 'ParameterSchema should return same value on multiple accesses');

        // Verify schema structure is preserved
        $this->assertArrayHasKey('type', $definition->parameterSchema);
        $this->assertArrayHasKey('properties', $definition->parameterSchema);
        $this->assertArrayHasKey('required', $definition->parameterSchema);
    }
}
