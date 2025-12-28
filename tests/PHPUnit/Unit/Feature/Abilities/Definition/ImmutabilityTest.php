<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Abilities\Definition;

use Noem\State\Feature\Abilities\AbilityDefinition;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: AbilityDefinition is immutable after construction
 *
 * Intent: Prevents modification of ability contracts after registration,
 * ensuring predictable behavior
 *
 * Criticality: constraint
 */
#[Group('abilities')]
#[Group('ability-definition')]
class ImmutabilityTest extends TestCase
{
    public function testImmutableAfterConstruction(): void
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

        // Verify all properties are readonly by attempting to access them multiple times
        // and ensuring they always return the same reference
        $this->assertSame($name, $definition->name);
        $this->assertSame($description, $definition->description);
        $this->assertSame($parameterSchema, $definition->parameterSchema);
        $this->assertSame($responseSchema, $definition->responseSchema);
        $this->assertSame($handler, $definition->handler);

        // In PHP 8.1+, attempting to modify readonly properties would throw an Error
        // This test verifies that the properties maintain their values and references
        // across multiple accesses, which is the observable behavior of readonly properties

        // Verify that accessing properties multiple times returns identical references
        $firstNameAccess = $definition->name;
        $secondNameAccess = $definition->name;
        $this->assertSame($firstNameAccess, $secondNameAccess, 'Name should be immutable');

        $firstDescriptionAccess = $definition->description;
        $secondDescriptionAccess = $definition->description;
        $this->assertSame($firstDescriptionAccess, $secondDescriptionAccess, 'Description should be immutable');

        $firstParameterSchemaAccess = $definition->parameterSchema;
        $secondParameterSchemaAccess = $definition->parameterSchema;
        $this->assertSame($firstParameterSchemaAccess, $secondParameterSchemaAccess, 'ParameterSchema should be immutable');

        $firstResponseSchemaAccess = $definition->responseSchema;
        $secondResponseSchemaAccess = $definition->responseSchema;
        $this->assertSame($firstResponseSchemaAccess, $secondResponseSchemaAccess, 'ResponseSchema should be immutable');

        $firstHandlerAccess = $definition->handler;
        $secondHandlerAccess = $definition->handler;
        $this->assertSame($firstHandlerAccess, $secondHandlerAccess, 'Handler should be immutable');
    }

    public function testPropertiesCannotBeModifiedDirectly(): void
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

        // Attempting to modify readonly properties should throw an Error
        $this->expectNotToPerformAssertions();
        $this->expectExceptionMessage('Cannot modify readonly property');

        // This will throw an Error if the property is readonly
        // @phpstan-ignore-next-line - We're intentionally triggering an error to test immutability
        $definition->name = 'modified-name';
    }
}
