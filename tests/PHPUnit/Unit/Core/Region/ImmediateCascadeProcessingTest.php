<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Core\Region;

use Noem\State\Chains\DispatchAction;
use Noem\State\Chains\DoTransition;
use Noem\State\Chains\Notification;
use Noem\State\Chains\Path;
use Noem\State\Events;
use Noem\State\Region;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Cascading events are processed immediately after entry
 */
#[Group('region')]
#[Group('event-lifecycle')]
class ImmediateCascadeProcessingTest extends TestCase
{
    public function testOnEnterParentProcessesCascadedEventsImmediately(): void
    {
        $events = \Mockery::mock(Events::class);
        $actionChain = \Mockery::mock(DispatchAction::class);
        $actionChain->shouldReceive('link');

        $transitionChain = \Mockery::mock(DoTransition::class);
        $pathChain = \Mockery::mock(Path::class);
        $notificationChain = \Mockery::mock(Notification::class);
        $notificationChain = \Mockery::mock(Notification::class);

        $processedEvents = [];
        $actionChain->shouldReceive('call')
            ->andReturnUsing(function ($context) use (&$processedEvents) {
                $processedEvents[] = $context->payload;
                return 'initial';
            });

        $region = new Region(
            $events,
            'initial',
            'final',
            $actionChain,
            $transitionChain,
            $pathChain,
            $notificationChain
        );

        $events->shouldReceive('onEnterState')
            ->once()
            ->andReturnUsing(function ($r, $state, $trigger) use ($region) {
                // Queue event during entry
                $region->trigger((object)['id' => 'cascaded'], true);
            });

        $region->onEnterParent((object)['id' => 'original']);

        // The cascaded event should have been processed immediately
        $this->assertCount(1, $processedEvents);
        $this->assertEquals('cascaded', $processedEvents[0]->id);
    }

    public function testDoDispatchCalledAfterOnEnterState(): void
    {
        $events = \Mockery::mock(Events::class);
        $actionChain = \Mockery::mock(DispatchAction::class);
        $actionChain->shouldReceive('link');

        $transitionChain = \Mockery::mock(DoTransition::class);
        $pathChain = \Mockery::mock(Path::class);
        $notificationChain = \Mockery::mock(Notification::class);
        $notificationChain = \Mockery::mock(Notification::class);

        $sequence = [];

        $events->shouldReceive('onEnterState')
            ->once()
            ->andReturnUsing(function ($r, $state, $trigger) use (&$sequence) {
                $sequence[] = 'onEnter';
                // Queue an event
                $r->trigger((object)['step' => 'queued'], true);
            });

        $actionChain->shouldReceive('call')
            ->andReturnUsing(function ($context) use (&$sequence) {
                $sequence[] = 'dispatch';
                return 'initial';
            });

        $region = new Region(
            $events,
            'initial',
            'final',
            $actionChain,
            $transitionChain,
            $pathChain,
            $notificationChain
        );

        $region->onEnterParent((object)['step' => 'original']);

        // Sequence should be: onEnter, then dispatch of queued event
        $this->assertEquals(['onEnter', 'dispatch'], $sequence);
    }

    protected function tearDown(): void
    {
        \Mockery::close();
        parent::tearDown();
    }
}
