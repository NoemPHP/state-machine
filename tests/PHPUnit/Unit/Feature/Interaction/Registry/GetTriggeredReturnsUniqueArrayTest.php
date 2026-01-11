<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Interaction\Registry;

use Noem\State\Feature\Interaction\InteractionDefinition;
use Noem\State\Feature\Interaction\InteractionRegistry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(InteractionRegistry::class)]
class GetTriggeredReturnsUniqueArrayTest extends TestCase
{
    public function testGetTriggeredReturnsArrayOfUniqueInteractionIds(): void
    {
        $registry = new InteractionRegistry();

        $definition = new InteractionDefinition(
            id: 'confirm_deploy',
            type: 'confirm',
            state: 'ready',
            question: 'Deploy?'
        );

        $registry->register($definition);

        // Track same interaction multiple times
        $registry->trackTriggered('confirm_deploy');
        $registry->trackTriggered('confirm_deploy');
        $registry->trackTriggered('confirm_deploy');

        $triggered = $registry->getTriggered();

        // Should only appear once
        $this->assertCount(1, $triggered);
        $this->assertSame(['confirm_deploy'], $triggered);
    }

    public function testDeduplicatesMultipleTracksOfSameInteraction(): void
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

        $registry->trackTriggered('interaction_1');
        $registry->trackTriggered('interaction_2');
        $registry->trackTriggered('interaction_1'); // Duplicate
        $registry->trackTriggered('interaction_2'); // Duplicate

        $triggered = $registry->getTriggered();

        $this->assertCount(2, $triggered);
    }
}
