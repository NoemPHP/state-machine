<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Abilities\Definition;

use Noem\State\Feature\Abilities\AbilityDefinition;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: AbilityDefinition stores responseSchema as readonly array
 *
 * Intent: Provides JSON Schema for response validation,
 * enabling contract verification and type-safe response handling
 *
 * Criticality: contract
 */
#[Group('abilities')]
#[Group('ability-definition')]
class StoresResponseSchemaTest extends TestCase
{
    public function testStoresResponseSchemaAsReadonlyArray(): void
    {
        $name = 'test-ability';
        $description = 'Test ability description';
        $parameterSchema = ['type' => 'object'];
        $responseSchema = [
            'type' => 'object',
            'properties' => [
                'success' => ['type' => 'boolean'],
                'data' => ['type' => 'array'],
            ],
            'required' => ['success'],
        ];
        $handler = fn(mixed $params): mixed => $params;

        $definition = new AbilityDefinition(
            name: $name,
            description: $description,
            parameterSchema: $parameterSchema,
            responseSchema: $responseSchema,
            handler: $handler
        );

        // Verify responseSchema is stored and accessible
        $this->assertSame($responseSchema, $definition->responseSchema);

        // Verify it's an array
        $this->assertIsArray($definition->responseSchema);

        // Verify readonly behavior - should return same value on multiple accesses
        $firstAccess = $definition->responseSchema;
        $secondAccess = $definition->responseSchema;
        $this->assertSame($firstAccess, $secondAccess, 'ResponseSchema should return same value on multiple accesses');

        // Verify schema structure is preserved
        $this->assertArrayHasKey('type', $definition->responseSchema);
        $this->assertArrayHasKey('properties', $definition->responseSchema);
        $this->assertArrayHasKey('required', $definition->responseSchema);
    }
}
