<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Interaction\Registry\Definition;

use Noem\State\Feature\Interaction\InteractionDefinition;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Noem\State\Feature\Interaction\InteractionDefinition
 */
class StoresIdTest extends TestCase
{
    public function testStoresIdAsReadonlyStringProperty(): void
    {
        $definition = new InteractionDefinition(
            id: 'confirm_deployment',
            type: 'confirm',
            state: 'ready',
            question: 'Deploy to production?'
        );

        $this->assertSame('confirm_deployment', $definition->id);
    }

    public function testIdIsReadonly(): void
    {
        $definition = new InteractionDefinition(
            id: 'confirm_deployment',
            type: 'confirm',
            state: 'ready',
            question: 'Deploy?'
        );

        // Verify id is readonly by checking it can't be reassigned
        $reflection = new \ReflectionProperty($definition, 'id');
        $this->assertTrue($reflection->isReadOnly());
    }
}
