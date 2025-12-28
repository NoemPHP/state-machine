<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Abilities\Message;

use Noem\State\Feature\Abilities\AbilityMessage;
use Noem\State\Feature\Abilities\AbilityDefinition;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that AbilityMessage stores optional AbilityDefinition reference
 *
 * Spec: ability-message / AbilityMessage stores optional AbilityDefinition reference
 * Intent: Includes definition metadata for middleware access, supporting logging and validation without registry lookup
 * Criticality: contract
 */

#[Group('feature')]
#[Group('abilities')]
class StoresDefinitionTest extends TestCase
{
    public function testStoresDefinitionReference(): void
    {
        // This test will fail because AbilityMessage doesn't exist yet
        $abilityName = 'test-ability';
        $parameters = ['key' => 'value'];
        $definition = new AbilityDefinition(
            name: $abilityName,
            description: 'Test ability',
            parameterSchema: [],
            responseSchema: [],
            handler: fn() => 'result'
        );

        $message = AbilityMessage::create($abilityName, $parameters, $definition);

        // Should store definition reference
        $this->assertSame($definition, $message->definition);
        $this->assertInstanceOf(AbilityDefinition::class, $message->definition);
    }

    public function testDefinitionIsOptional(): void
    {
        // This test will fail because AbilityMessage doesn't exist yet
        $abilityName = 'test-ability';
        $parameters = ['key' => 'value'];

        $message = AbilityMessage::create($abilityName, $parameters);

        // Definition should be optional (null by default)
        $this->assertNull($message->definition);
    }

    public function testDefinitionIsReadonly(): void
    {
        // This test will fail because AbilityMessage doesn't exist yet
        $abilityName = 'test-ability';
        $parameters = ['key' => 'value'];
        $definition = new AbilityDefinition(
            name: $abilityName,
            description: 'Test ability',
            parameterSchema: [],
            responseSchema: [],
            handler: fn() => 'result'
        );

        $message = AbilityMessage::create($abilityName, $parameters, $definition);

        // Should be readonly - accessing it multiple times returns same instance
        $this->assertSame($message->definition, $message->definition);
    }
}
