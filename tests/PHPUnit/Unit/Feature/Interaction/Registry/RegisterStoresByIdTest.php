<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Interaction\Registry;

use Noem\State\Feature\Interaction\InteractionDefinition;
use Noem\State\Feature\Interaction\InteractionRegistry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(InteractionRegistry::class)]
class RegisterStoresByIdTest extends TestCase
{
    public function testStoresInteractionDefinitionIndexedById(): void
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

        $this->assertSame($definition, $retrieved);
    }

    public function testUsesIdAsStorageKey(): void
    {
        $registry = new InteractionRegistry();
        $definition1 = new InteractionDefinition(
            id: 'interaction_1',
            type: 'confirm',
            state: 'state1',
            question: 'Question 1?'
        );
        $definition2 = new InteractionDefinition(
            id: 'interaction_2',
            type: 'select',
            state: 'state2',
            question: 'Question 2?'
        );

        $registry->register($definition1);
        $registry->register($definition2);

        $this->assertSame($definition1, $registry->get('interaction_1'));
        $this->assertSame($definition2, $registry->get('interaction_2'));
    }
}
