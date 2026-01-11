<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Interaction\Registry\Definition;

use Noem\State\Feature\Interaction\InteractionDefinition;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Noem\State\Feature\Interaction\InteractionDefinition
 */
class ImplementsJsonSerializableTest extends TestCase
{
    public function testImplementsJsonSerializableInterface(): void
    {
        $definition = new InteractionDefinition(
            id: 'confirm_deploy',
            type: 'confirm',
            state: 'ready',
            question: 'Deploy?'
        );

        $this->assertInstanceOf(\JsonSerializable::class, $definition);
    }

    public function testJsonSerializeMethodExists(): void
    {
        $this->assertTrue(
            method_exists(InteractionDefinition::class, 'jsonSerialize')
        );
    }

    public function testCanBeJsonEncoded(): void
    {
        $definition = new InteractionDefinition(
            id: 'confirm_deploy',
            type: 'confirm',
            state: 'ready',
            question: 'Deploy?'
        );

        $json = json_encode($definition);
        $this->assertIsString($json);
        $this->assertNotFalse($json);
    }
}
