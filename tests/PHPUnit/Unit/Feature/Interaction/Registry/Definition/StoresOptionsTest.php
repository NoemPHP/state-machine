<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Interaction\Registry\Definition;

use Noem\State\Feature\Interaction\InteractionDefinition;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Noem\State\Feature\Interaction\InteractionDefinition
 */
class StoresOptionsTest extends TestCase
{
    public function testStoresOptionsAsReadonlyArrayProperty(): void
    {
        $options = [
            ['label' => 'Production', 'value' => 'prod'],
            ['label' => 'Staging', 'value' => 'staging'],
        ];

        $definition = new InteractionDefinition(
            id: 'select_env',
            type: 'select',
            state: 'configuring',
            question: 'Choose environment',
            options: $options
        );

        $this->assertSame($options, $definition->options);
    }

    public function testOptionsCanBeNull(): void
    {
        $definition = new InteractionDefinition(
            id: 'confirm_deploy',
            type: 'confirm',
            state: 'ready',
            question: 'Deploy?'
        );

        $this->assertNull($definition->options);
    }

    public function testOptionsIsReadonly(): void
    {
        $definition = new InteractionDefinition(
            id: 'select_env',
            type: 'select',
            state: 'configuring',
            question: 'Choose environment',
            options: []
        );

        $reflection = new \ReflectionProperty($definition, 'options');
        $this->assertTrue($reflection->isReadOnly());
    }
}
