<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Interaction\Registry\Definition;

use Noem\State\Feature\Interaction\InteractionDefinition;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Noem\State\Feature\Interaction\InteractionDefinition
 */
class StoresTypeTest extends TestCase
{
    public function testStoresTypeAsReadonlyStringProperty(): void
    {
        $definition = new InteractionDefinition(
            id: 'select_env',
            type: 'select',
            state: 'configuring',
            question: 'Choose environment'
        );

        $this->assertSame('select', $definition->type);
    }

    public function testTypeIsReadonly(): void
    {
        $definition = new InteractionDefinition(
            id: 'select_env',
            type: 'select',
            state: 'configuring',
            question: 'Choose environment'
        );

        $reflection = new \ReflectionProperty($definition, 'type');
        $this->assertTrue($reflection->isReadOnly());
    }
}
