<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Abilities\Conditional;

use Noem\State\Feature\Abilities\AbilityDefinition;
use Noem\State\Region;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Noem\State\Feature\Abilities\AbilityDefinition
 */
final class StoresPredicateTest extends TestCase
{
    public function testAbilityDefinitionStoresOptionalPredicate(): void
    {
        $handler = fn(array $params) => ['result' => 'test'];
        $predicate = fn(Region $region) => true;

        $definition = new AbilityDefinition(
            name: 'test-ability',
            description: 'Test',
            parameterSchema: [],
            responseSchema: [],
            handler: $handler,
            predicate: $predicate
        );

        $this->assertSame(
            $predicate,
            $definition->predicate,
            'AbilityDefinition must store optional predicate callable'
        );
    }

    public function testPredicateDefaultsToNull(): void
    {
        $handler = fn(array $params) => ['result' => 'test'];

        $definition = new AbilityDefinition(
            name: 'test-ability',
            description: 'Test',
            parameterSchema: [],
            responseSchema: [],
            handler: $handler
        );

        $this->assertNull(
            $definition->predicate,
            'Predicate must default to null when not provided'
        );
    }
}
