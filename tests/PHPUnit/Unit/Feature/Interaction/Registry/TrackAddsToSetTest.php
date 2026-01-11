<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Interaction\Registry;

use Noem\State\Feature\Interaction\InteractionDefinition;
use Noem\State\Feature\Interaction\InteractionRegistry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(InteractionRegistry::class)]
class TrackAddsToSetTest extends TestCase
{
    public function testTrackTriggeredAddsInteractionIdToTriggeredSet(): void
    {
        $registry = new InteractionRegistry();

        $definition = new InteractionDefinition(
            id: 'confirm_deploy',
            type: 'confirm',
            state: 'ready',
            question: 'Deploy?'
        );

        $registry->register($definition);
        $registry->trackTriggered('confirm_deploy');

        $triggered = $registry->getTriggered();

        $this->assertContains('confirm_deploy', $triggered);
    }

    public function testMultipleTracksRecorded(): void
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

        $triggered = $registry->getTriggered();

        $this->assertCount(2, $triggered);
        $this->assertContains('interaction_1', $triggered);
        $this->assertContains('interaction_2', $triggered);
    }
}
