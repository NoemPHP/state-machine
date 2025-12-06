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
 * Acceptance Criterion: Transition chain is not invoked when state remains unchanged
 */
#[Group('region')]
#[Group('transition-chain-integration')]
class NoTransitionOnSameStateTest extends TestCase
{
    public function testTransitionChainNotCalledWhenStateUnchanged(): void
    {
        $events = \Mockery::mock(Events::class);
        $actionChain = \Mockery::mock(DispatchAction::class);
        $actionChain->shouldReceive('link');

        $transitionChain = \Mockery::mock(DoTransition::class);
        $pathChain = \Mockery::mock(Path::class);
        $notificationChain = \Mockery::mock(Notification::class);
        $notificationChain = \Mockery::mock(Notification::class);

        $actionChain->shouldReceive('call')
            ->andReturn('initial');

        $transitionChain->shouldReceive('call')
            ->never();

        $region = new Region(
            $events,
            'initial',
            'final',
            $actionChain,
            $transitionChain,
            $pathChain,
            $notificationChain
        );

        $region->trigger((object)['data' => 'test'], false);

        $this->assertTrue(true);
    }

    public function testMultipleEventsWithSameStateDoNotTriggerTransition(): void
    {
        $events = \Mockery::mock(Events::class);
        $actionChain = \Mockery::mock(DispatchAction::class);
        $actionChain->shouldReceive('link');

        $transitionChain = \Mockery::mock(DoTransition::class);
        $pathChain = \Mockery::mock(Path::class);
        $notificationChain = \Mockery::mock(Notification::class);
        $notificationChain = \Mockery::mock(Notification::class);

        $actionChain->shouldReceive('call')
            ->andReturn('myState', 'myState', 'myState');

        $transitionChain->shouldReceive('call')
            ->never();

        $region = new Region(
            $events,
            'myState',
            'final',
            $actionChain,
            $transitionChain,
            $pathChain,
            $notificationChain
        );

        $region->trigger((object)['id' => 1], false);
        $region->trigger((object)['id' => 2], false);
        $region->trigger((object)['id' => 3], false);

        $this->assertEquals('myState', $region->currentState());
    }

    public function testStateRemainsUnchangedWhenActionChainReturnsSameState(): void
    {
        $events = \Mockery::mock(Events::class);
        $actionChain = \Mockery::mock(DispatchAction::class);
        $actionChain->shouldReceive('link');

        $transitionChain = \Mockery::mock(DoTransition::class);
        $pathChain = \Mockery::mock(Path::class);
        $notificationChain = \Mockery::mock(Notification::class);
        $notificationChain = \Mockery::mock(Notification::class);

        $actionChain->shouldReceive('call')
            ->andReturn('stable');

        $transitionChain->shouldReceive('call')
            ->never();

        $region = new Region(
            $events,
            'stable',
            'final',
            $actionChain,
            $transitionChain,
            $pathChain,
            $notificationChain
        );

        $initialState = $region->currentState();
        $region->trigger((object)['data' => 'test'], false);
        $afterState = $region->currentState();

        $this->assertEquals($initialState, $afterState);
        $this->assertEquals('stable', $afterState);
    }

    protected function tearDown(): void
    {
        \Mockery::close();
        parent::tearDown();
    }
}
