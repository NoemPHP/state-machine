<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Interaction\Registry\Definition;

use Noem\State\Feature\Interaction\InteractionDefinition;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Noem\State\Feature\Interaction\InteractionDefinition
 */
class StoresConstraintsTest extends TestCase
{
    public function testStoresConstraintsAsReadonlyArrayProperty(): void
    {
        $constraints = [
            'minSelections' => 1,
            'maxSelections' => 3,
            'pattern' => '/^[a-z]+$/',
        ];

        $definition = new InteractionDefinition(
            id: 'choice_features',
            type: 'choice',
            state: 'configuring',
            question: 'Select features',
            constraints: $constraints
        );

        $this->assertSame($constraints, $definition->constraints);
    }

    public function testConstraintsCanBeNull(): void
    {
        $definition = new InteractionDefinition(
            id: 'confirm_deploy',
            type: 'confirm',
            state: 'ready',
            question: 'Deploy?'
        );

        $this->assertNull($definition->constraints);
    }

    public function testConstraintsIsReadonly(): void
    {
        $definition = new InteractionDefinition(
            id: 'choice_features',
            type: 'choice',
            state: 'configuring',
            question: 'Select features',
            constraints: []
        );

        $reflection = new \ReflectionProperty($definition, 'constraints');
        $this->assertTrue($reflection->isReadOnly());
    }
}
