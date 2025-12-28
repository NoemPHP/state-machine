<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Core\Region;

use Noem\State\Chains\DispatchAction;
use Noem\State\Chains\DoTransition;
use Noem\State\Chains\Notification;
use Noem\State\Chains\Path;
use Noem\State\Chains\Params\Transition;
use Noem\State\Events;
use Noem\State\Region;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Action chain can return same or different state
 */
#[Group('region')]
#[Group('action-chain-integration')]
class ActionChainStateReturnTest extends TestCase
{
    public function testActionChainCanReturnSameState(): void
    {
        $events = \Mockery::mock(Events::class);
        $actionChain = \Mockery::mock(DispatchAction::class);
        $actionChain->shouldReceive('link');

        $transitionChain = \Mockery::mock(DoTransition::class);
        $pathChain = \Mockery::mock(Path::class);
        $notificationChain = \Mockery::mock(Notification::class);

        $notificationChain->shouldReceive('call')
            ->andReturn([]);

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

        $this->assertEquals('initial', $region->currentState());
    }

    public function testActionChainCanReturnDifferentState(): void
    {
        $events = \Mockery::mock(Events::class);
        $actionChain = \Mockery::mock(DispatchAction::class);
        $actionChain->shouldReceive('link');

        $transitionChain = \Mockery::mock(DoTransition::class);
        $pathChain = \Mockery::mock(Path::class);
        $notificationChain = \Mockery::mock(Notification::class);

        $notificationChain->shouldReceive('call')
            ->andReturn([]);

        $actionChain->shouldReceive('call')
            ->andReturn('newState');

        $transitionChain->shouldReceive('call')
            ->once()
            ->andReturn(true);

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

        $this->assertEquals('newState', $region->currentState());
    }

    public function testStateChangesWhenActionChainReturnsNewState(): void
    {
        $events = \Mockery::mock(Events::class);
        $actionChain = \Mockery::mock(DispatchAction::class);
        $actionChain->shouldReceive('link');

        $transitionChain = \Mockery::mock(DoTransition::class);
        $pathChain = \Mockery::mock(Path::class);
        $notificationChain = \Mockery::mock(Notification::class);

        $notificationChain->shouldReceive('call')
            ->andReturn([]);

        // First event keeps same state, second changes it
        $actionChain->shouldReceive('call')
            ->andReturn('initial', 'final');

        $transitionChain->shouldReceive('call')
            ->once()
            ->andReturn(true);

        $region = new Region(
            $events,
            'initial',
            'final',
            $actionChain,
            $transitionChain,
            $pathChain,
            $notificationChain
        );

        $region->trigger((object)['id' => 1], false);
        $this->assertEquals('initial', $region->currentState());

        $region->trigger((object)['id' => 2], false);
        $this->assertEquals('final', $region->currentState());
    }

    protected function tearDown(): void
    {
        \Mockery::close();
        parent::tearDown();
    }
}
