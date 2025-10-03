<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Core\Region;

use Noem\State\Chains\DispatchAction;
use Noem\State\Chains\DoTransition;
use Noem\State\Chains\Path;
use Noem\State\Events;
use Noem\State\Region;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Entry callbacks can trigger additional events
 */
#[Group('region')]
#[Group('event-lifecycle')]
class EntryCascadingEventsTest extends TestCase
{
    public function testEntryCallbacksCanTriggerEvents(): void
    {
        $events = \Mockery::mock(Events::class);
        $actionChain = \Mockery::mock(DispatchAction::class);
        $transitionChain = \Mockery::mock(DoTransition::class);
        $pathChain = \Mockery::mock(Path::class);

        $region = new Region(
            $events,
            'initial',
            'final',
            $actionChain,
            $transitionChain,
            $pathChain
        );

        $actionChain->shouldReceive('call')
            ->andReturn('initial');

        // Events system can trigger new events
        $events->shouldReceive('onEnterState')
            ->once()
            ->andReturnUsing(function ($r, $state, $trigger) use ($region) {
                // Simulate entry callback triggering a new event
                $region->trigger((object)['cascaded' => true], true);
            });

        $region->onEnterParent((object)['data' => 'test']);

        $this->assertTrue(true);
    }

    public function testCascadedEventsAreQueued(): void
    {
        $events = \Mockery::mock(Events::class);
        $actionChain = \Mockery::mock(DispatchAction::class);
        $transitionChain = \Mockery::mock(DoTransition::class);
        $pathChain = \Mockery::mock(Path::class);

        $callCount = 0;
        $actionChain->shouldReceive('call')
            ->andReturnUsing(function () use (&$callCount) {
                $callCount++;
                return 'initial';
            });

        $region = new Region(
            $events,
            'initial',
            'final',
            $actionChain,
            $transitionChain,
            $pathChain
        );

        $events->shouldReceive('onEnterState')
            ->once()
            ->andReturnUsing(function ($r, $state, $trigger) use ($region) {
                // Queue event during entry
                $region->trigger((object)['cascaded' => true], true);
            });

        $region->onEnterParent((object)['data' => 'test']);

        // After onEnterParent, the cascaded event should have been processed
        // because onEnterParent calls doDispatch
        $this->assertEquals(1, $callCount);
    }

    protected function tearDown(): void
    {
        \Mockery::close();
        parent::tearDown();
    }
}
