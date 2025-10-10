<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Core\Region;

use Noem\State\Chains\DispatchAction;
use Noem\State\Chains\DoTransition;
use Noem\State\Chains\Path;
use Noem\State\Chains\Params\Action;
use Noem\State\Events;
use Noem\State\Region;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Action chain is invoked for each dispatched event
 */
#[Group('region')]
#[Group('action-chain-integration')]
class ActionChainInvocationTest extends TestCase
{
    public function testActionChainIsCalledForEachEvent(): void
    {
        $events = \Mockery::mock(Events::class);
        $actionChain = \Mockery::mock(DispatchAction::class);
        $actionChain->shouldReceive('link');

        $transitionChain = \Mockery::mock(DoTransition::class);
        $pathChain = \Mockery::mock(Path::class);

        $actionChain->shouldReceive('call')
            ->times(3)
            ->andReturn('initial');

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

        // Mockery will verify the call count
        $this->assertTrue(true);
    }

    public function testActionChainIsCalledOncePerDispatchedEvent(): void
    {
        $events = \Mockery::mock(Events::class);
        $actionChain = \Mockery::mock(DispatchAction::class);
        $actionChain->shouldReceive('link');

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

        $region->trigger((object)['id' => 1], false);
        
        $this->assertEquals(1, $callCount);
    }

    public function testActionChainNotCalledForEnqueuedEvents(): void
    {
        $events = \Mockery::mock(Events::class);
        $actionChain = \Mockery::mock(DispatchAction::class);
        $actionChain->shouldReceive('link');

        $transitionChain = \Mockery::mock(DoTransition::class);
        $pathChain = \Mockery::mock(Path::class);

        $actionChain->shouldReceive('call')
            ->never();

        $region = new Region(
            $events,
            'initial',
            'final',
            $actionChain,
            $transitionChain,
            $pathChain
        );

        $region->trigger((object)['id' => 1], true);
        $region->trigger((object)['id' => 2], true);

        $this->assertTrue(true);
    }

    protected function tearDown(): void
    {
        \Mockery::close();
        parent::tearDown();
    }
}
