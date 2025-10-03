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
 * Acceptance Criterion: Transition chain is invoked when state changes
 */
#[Group('region')]
#[Group('transition-chain-integration')]
class TransitionChainInvocationTest extends TestCase
{
    public function testTransitionChainCalledWhenStateChanges(): void
    {
        $events = \Mockery::mock(Events::class);
        $actionChain = \Mockery::mock(DispatchAction::class);
        $transitionChain = \Mockery::mock(DoTransition::class);
        $pathChain = \Mockery::mock(Path::class);

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
            $pathChain
        );

        $region->trigger((object)['data' => 'test'], false);

        $this->assertTrue(true);
    }

    public function testTransitionChainCalledForEachStateChange(): void
    {
        $events = \Mockery::mock(Events::class);
        $actionChain = \Mockery::mock(DispatchAction::class);
        $transitionChain = \Mockery::mock(DoTransition::class);
        $pathChain = \Mockery::mock(Path::class);

        $actionChain->shouldReceive('call')
            ->andReturn('state1', 'state2', 'state3');

        $transitionChain->shouldReceive('call')
            ->times(3)
            ->andReturn(true);

        $region = new Region(
            $events,
            'initial',
            'final',
            $actionChain,
            $transitionChain,
            $pathChain
        );

        $region->trigger((object)['id' => 1], false);
        $region->trigger((object)['id' => 2], false);
        $region->trigger((object)['id' => 3], false);

        $this->assertTrue(true);
    }

    protected function tearDown(): void
    {
        \Mockery::close();
        parent::tearDown();
    }
}
