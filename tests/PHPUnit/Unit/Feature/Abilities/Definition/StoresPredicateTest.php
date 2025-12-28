<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Abilities\Definition;

use Noem\State\Feature\Abilities\AbilityDefinition;
use Noem\State\Region;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: AbilityDefinition stores optional predicate callable
 *
 * Intent: Associates conditional logic with ability for runtime filtering,
 * enabling context-aware exposure
 *
 * Criticality: contract
 */
#[Group('abilities')]
#[Group('ability-definition')]
class StoresPredicateTest extends TestCase
{
    public function testStoresPredicateAsReadonlyCallable(): void
    {
        $name = 'test-ability';
        $description = 'Test ability description';
        $parameterSchema = ['type' => 'object'];
        $responseSchema = ['type' => 'object'];
        $handler = fn(mixed $params): mixed => $params;
        $predicate = fn(Region $region): bool => true;

        $definition = new AbilityDefinition(
            name: $name,
            description: $description,
            parameterSchema: $parameterSchema,
            responseSchema: $responseSchema,
            handler: $handler,
            predicate: $predicate
        );

        // Verify predicate is stored and accessible
        $this->assertSame($predicate, $definition->predicate);

        // Verify it's callable
        $this->assertIsCallable($definition->predicate);
    }

    public function testPredicateCanBeNull(): void
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
            handler: $handler,
            predicate: null
        );

        // Verify predicate can be null
        $this->assertNull($definition->predicate);
    }
}
