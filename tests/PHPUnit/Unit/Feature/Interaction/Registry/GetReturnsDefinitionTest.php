<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Interaction\Registry;

use Noem\State\Feature\Interaction\InteractionDefinition;
use Noem\State\Feature\Interaction\InteractionRegistry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(InteractionRegistry::class)]
class GetReturnsDefinitionTest extends TestCase
{
    public function testReturnsInteractionDefinitionWhenIdExists(): void
    {
        $registry = new InteractionRegistry();
        $definition = new InteractionDefinition(
            id: 'confirm_deploy',
            type: 'confirm',
            state: 'ready',
            question: 'Deploy?'
        );

        $registry->register($definition);
        $retrieved = $registry->get('confirm_deploy');

        $this->assertInstanceOf(InteractionDefinition::class, $retrieved);
        $this->assertSame($definition, $retrieved);
    }
}
