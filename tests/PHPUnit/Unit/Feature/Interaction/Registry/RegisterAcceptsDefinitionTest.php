<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Interaction\Registry;

use Noem\State\Feature\Interaction\InteractionDefinition;
use Noem\State\Feature\Interaction\InteractionRegistry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(InteractionRegistry::class)]
class RegisterAcceptsDefinitionTest extends TestCase
{
    public function testRegisterAcceptsInteractionDefinitionParameter(): void
    {
        $registry = new InteractionRegistry();
        $definition = new InteractionDefinition(
            id: 'confirm_deploy',
            type: 'confirm',
            state: 'ready',
            question: 'Deploy?'
        );

        // Should not throw
        $registry->register($definition);
        $this->assertTrue(true);
    }

    public function testRegisterMethodExists(): void
    {
        $this->assertTrue(method_exists(InteractionRegistry::class, 'register'));
    }
}
