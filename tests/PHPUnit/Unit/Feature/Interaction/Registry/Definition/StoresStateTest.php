<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Interaction\Registry\Definition;

use Noem\State\Feature\Interaction\InteractionDefinition;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Noem\State\Feature\Interaction\InteractionDefinition
 */
class StoresStateTest extends TestCase
{
    public function testStoresStateAsReadonlyStringProperty(): void
    {
        $definition = new InteractionDefinition(
            id: 'confirm_deploy',
            type: 'confirm',
            state: 'ready_to_deploy',
            question: 'Deploy?'
        );

        $this->assertSame('ready_to_deploy', $definition->state);
    }

    public function testStateIsReadonly(): void
    {
        $definition = new InteractionDefinition(
            id: 'confirm_deploy',
            type: 'confirm',
            state: 'ready_to_deploy',
            question: 'Deploy?'
        );

        $reflection = new \ReflectionProperty($definition, 'state');
        $this->assertTrue($reflection->isReadOnly());
    }
}
