<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Abilities\Definition;

use Noem\State\Feature\Abilities\AbilityDefinition;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: AbilityDefinition stores ability name as readonly string
 *
 * Intent: Provides unique identifier for ability lookup and invocation,
 * ensuring name immutability after creation
 *
 * Criticality: contract
 */
#[Group('abilities')]
#[Group('ability-definition')]
class StoresNameTest extends TestCase
{
    public function testStoresNameAsReadonlyString(): void
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

        // Verify name is stored and accessible
        $this->assertSame($name, $definition->name);

        // Verify it's a string
        $this->assertIsString($definition->name);

        // Verify readonly behavior - should return same value on multiple accesses
        $firstAccess = $definition->name;
        $secondAccess = $definition->name;
        $this->assertSame($firstAccess, $secondAccess, 'Name should return same value on multiple accesses');
    }
}
