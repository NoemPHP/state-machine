<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Abilities\Message;

use Noem\State\Feature\Abilities\AbilityMessage;
use Noem\State\Feature\Abilities\AbilityDefinition;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that AbilityMessage implements jsonSerialize with type, correlationId, abilityName, parameters
 *
 * Spec: ability-message / Message subclass for ability invocations
 * Intent: Enables serialization for persistence and transmission with all invocation data
 * Criticality: contract
 */

#[Group('feature')]
#[Group('abilities')]
class JsonSerializableTest extends TestCase
{
    public function testImplementsJsonSerializable(): void
    {
        // This test will fail because AbilityMessage doesn't exist yet
        $this->assertTrue(
            is_subclass_of(AbilityMessage::class, \JsonSerializable::class),
            'AbilityMessage must implement JsonSerializable (inherited from Message)'
        );
    }

    public function testJsonSerializeIncludesType(): void
    {
        // This test will fail because AbilityMessage doesn't exist yet
        $abilityName = 'test-ability';
        $parameters = ['key' => 'value'];

        $message = AbilityMessage::create($abilityName, $parameters);
        $serialized = $message->jsonSerialize();

        // Should include 'type' field
        $this->assertIsArray($serialized);
        $this->assertArrayHasKey('type', $serialized);
        $this->assertSame(AbilityMessage::class, $serialized['type']);
    }

    public function testJsonSerializeIncludesCorrelationId(): void
    {
        // This test will fail because AbilityMessage doesn't exist yet
        $abilityName = 'test-ability';
        $parameters = ['key' => 'value'];
        $correlationId = 'custom-correlation-id';

        $message = AbilityMessage::create($abilityName, $parameters, null, $correlationId);
        $serialized = $message->jsonSerialize();

        // Should include 'correlationId' field
        $this->assertArrayHasKey('correlationId', $serialized);
        $this->assertSame($correlationId, $serialized['correlationId']);
    }

    public function testJsonSerializeIncludesAbilityName(): void
    {
        // This test will fail because AbilityMessage doesn't exist yet
        $abilityName = 'test-ability';
        $parameters = ['key' => 'value'];

        $message = AbilityMessage::create($abilityName, $parameters);
        $serialized = $message->jsonSerialize();

        // Should include 'abilityName' field
        $this->assertArrayHasKey('abilityName', $serialized);
        $this->assertSame($abilityName, $serialized['abilityName']);
    }

    public function testJsonSerializeIncludesParameters(): void
    {
        // This test will fail because AbilityMessage doesn't exist yet
        $abilityName = 'test-ability';
        $parameters = ['key' => 'value', 'nested' => ['data' => 123]];

        $message = AbilityMessage::create($abilityName, $parameters);
        $serialized = $message->jsonSerialize();

        // Should include 'parameters' field
        $this->assertArrayHasKey('parameters', $serialized);
        $this->assertSame($parameters, $serialized['parameters']);
    }

    public function testJsonSerializeHandlesNullParameters(): void
    {
        // This test will fail because AbilityMessage doesn't exist yet
        $abilityName = 'test-ability';

        $message = AbilityMessage::create($abilityName, null);
        $serialized = $message->jsonSerialize();

        // Should include 'parameters' field even when null
        $this->assertArrayHasKey('parameters', $serialized);
        $this->assertNull($serialized['parameters']);
    }

    public function testJsonSerializeCompleteStructure(): void
    {
        // This test will fail because AbilityMessage doesn't exist yet
        $abilityName = 'test-ability';
        $parameters = ['key' => 'value'];
        $correlationId = 'test-correlation-id';

        $message = AbilityMessage::create($abilityName, $parameters, null, $correlationId);
        $serialized = $message->jsonSerialize();

        // Should have complete structure with all required fields
        $this->assertIsArray($serialized);
        $this->assertArrayHasKey('type', $serialized);
        $this->assertArrayHasKey('correlationId', $serialized);
        $this->assertArrayHasKey('abilityName', $serialized);
        $this->assertArrayHasKey('parameters', $serialized);

        // Verify values
        $this->assertSame(AbilityMessage::class, $serialized['type']);
        $this->assertSame($correlationId, $serialized['correlationId']);
        $this->assertSame($abilityName, $serialized['abilityName']);
        $this->assertSame($parameters, $serialized['parameters']);
    }
}
