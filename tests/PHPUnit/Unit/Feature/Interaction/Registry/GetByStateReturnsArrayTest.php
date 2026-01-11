<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Interaction\Registry;

use Noem\State\Feature\Interaction\InteractionDefinition;
use Noem\State\Feature\Interaction\InteractionRegistry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(InteractionRegistry::class)]
class GetByStateReturnsArrayTest extends TestCase
{
    public function testReturnsArrayOfInteractionDefinitionsForMatchingState(): void
    {
        $registry = new InteractionRegistry();

        $definition1 = new InteractionDefinition(
            id: 'confirm_deploy',
            type: 'confirm',
            state: 'ready_to_deploy',
            question: 'Deploy?'
        );
        $definition2 = new InteractionDefinition(
            id: 'select_env',
            type: 'select',
            state: 'ready_to_deploy',
            question: 'Choose environment'
        );
        $definition3 = new InteractionDefinition(
            id: 'other_interaction',
            type: 'prompt',
            state: 'other_state',
            question: 'Other question'
        );

        $registry->register($definition1);
        $registry->register($definition2);
        $registry->register($definition3);

        $interactions = $registry->getByState('ready_to_deploy');

        $this->assertIsArray($interactions);
        $this->assertCount(2, $interactions);
        $this->assertContains($definition1, $interactions);
        $this->assertContains($definition2, $interactions);
        $this->assertNotContains($definition3, $interactions);
    }

    public function testReturnedArrayContainsOnlyInteractionDefinitions(): void
    {
        $registry = new InteractionRegistry();

        $definition = new InteractionDefinition(
            id: 'confirm_deploy',
            type: 'confirm',
            state: 'ready',
            question: 'Deploy?'
        );

        $registry->register($definition);
        $interactions = $registry->getByState('ready');

        foreach ($interactions as $interaction) {
            $this->assertInstanceOf(InteractionDefinition::class, $interaction);
        }
    }
}
