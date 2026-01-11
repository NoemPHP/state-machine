<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Interaction\Registry\Definition;

use Noem\State\Feature\Interaction\InteractionDefinition;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Noem\State\Feature\Interaction\InteractionDefinition
 */
class SerializesAllPropertiesTest extends TestCase
{
    public function testSerializesAllPropertiesInReturnedArray(): void
    {
        $options = [
            ['label' => 'Prod', 'value' => 'prod'],
            ['label' => 'Staging', 'value' => 'staging'],
        ];
        $constraints = ['minSelections' => 1];
        $metadata = ['helpText' => 'Choose wisely'];

        $definition = new InteractionDefinition(
            id: 'select_env',
            type: 'select',
            state: 'configuring',
            question: 'Choose environment',
            options: $options,
            constraints: $constraints,
            metadata: $metadata
        );

        $serialized = $definition->jsonSerialize();

        $this->assertIsArray($serialized);
        $this->assertArrayHasKey('id', $serialized);
        $this->assertArrayHasKey('type', $serialized);
        $this->assertArrayHasKey('state', $serialized);
        $this->assertArrayHasKey('question', $serialized);
        $this->assertArrayHasKey('options', $serialized);
        $this->assertArrayHasKey('constraints', $serialized);
        $this->assertArrayHasKey('metadata', $serialized);
    }

    public function testSerializedValuesMatchProperties(): void
    {
        $options = [['label' => 'Yes', 'value' => 'yes']];
        $constraints = ['required' => true];
        $metadata = ['help' => 'Please confirm'];

        $definition = new InteractionDefinition(
            id: 'confirm_deploy',
            type: 'confirm',
            state: 'ready',
            question: 'Deploy to production?',
            options: $options,
            constraints: $constraints,
            metadata: $metadata
        );

        $serialized = $definition->jsonSerialize();

        $this->assertSame('confirm_deploy', $serialized['id']);
        $this->assertSame('confirm', $serialized['type']);
        $this->assertSame('ready', $serialized['state']);
        $this->assertSame('Deploy to production?', $serialized['question']);
        $this->assertSame($options, $serialized['options']);
        $this->assertSame($constraints, $serialized['constraints']);
        $this->assertSame($metadata, $serialized['metadata']);
    }

    public function testSerializesNullOptionalProperties(): void
    {
        $definition = new InteractionDefinition(
            id: 'confirm_deploy',
            type: 'confirm',
            state: 'ready',
            question: 'Deploy?'
        );

        $serialized = $definition->jsonSerialize();

        $this->assertNull($serialized['options']);
        $this->assertNull($serialized['constraints']);
        $this->assertNull($serialized['metadata']);
    }
}
