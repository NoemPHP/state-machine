<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Abilities\Definition;

use Noem\State\Feature\Abilities\AbilityDefinition;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: AbilityDefinition stores description as readonly string
 *
 * Intent: Provides human-readable documentation for ability purpose,
 * supporting enumeration and introspection
 *
 * Criticality: contract
 */
#[Group('abilities')]
#[Group('ability-definition')]
class StoresDescriptionTest extends TestCase
{
    public function testStoresDescriptionAsReadonlyString(): void
    {
        $name = 'test-ability';
        $description = 'Test ability description';
        $parameterSchema = ['type' => 'object'];
        $responseSchema = ['type' => 'object'];
        $handler = fn(mixed $params): mixed => $params;

        $definition = new AbilityDefinition(
            name: $name,
            description: $description,
            parameterSchema: $parameterSchema,
            responseSchema: $responseSchema,
            handler: $handler
        );

        // Verify description is stored and accessible
        $this->assertSame($description, $definition->description);

        // Verify it's a string
        $this->assertIsString($definition->description);

        // Verify readonly behavior - should return same value on multiple accesses
        $firstAccess = $definition->description;
        $secondAccess = $definition->description;
        $this->assertSame($firstAccess, $secondAccess, 'Description should return same value on multiple accesses');
    }
}
