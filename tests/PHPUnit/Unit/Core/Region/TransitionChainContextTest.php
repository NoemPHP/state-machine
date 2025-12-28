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
 * Acceptance Criterion: Transition chain receives previous and current state
 */
#[Group('region')]
#[Group('transition-chain-integration')]
class TransitionChainContextTest extends TestCase
{
    public function testTransitionChainReceivesTransitionContext(): void
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
            ->with(\Mockery::type(Transition::class))
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

        $this->assertTrue(true);
    }

    public function testTransitionContextContainsPreviousState(): void
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

        $capturedPreviousState = null;
        $transitionChain->shouldReceive('call')
            ->andReturnUsing(function (Transition $context) use (&$capturedPreviousState) {
                $capturedPreviousState = $context->previousState;
                return true;
            });

        $region = new Region(
            $events,
            'oldState',
            'final',
            $actionChain,
            $transitionChain,
            $pathChain,
            $notificationChain
        );

        $region->trigger((object)['data' => 'test'], false);

        $this->assertEquals('oldState', $capturedPreviousState);
    }

    public function testTransitionContextContainsRegion(): void
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

        $capturedRegion = null;
        $transitionChain->shouldReceive('call')
            ->andReturnUsing(function (Transition $context) use (&$capturedRegion) {
                $capturedRegion = $context->region;
                return true;
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

        $region->trigger((object)['data' => 'test'], false);

        $this->assertSame($region, $capturedRegion);
    }

    public function testTransitionContextContainsTriggerPayload(): void
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

        $capturedPayload = null;
        $transitionChain->shouldReceive('call')
            ->andReturnUsing(function (Transition $context) use (&$capturedPayload) {
                $capturedPayload = $context->payload;
                return true;
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

        $payload = (object)['id' => 456, 'type' => 'transition'];
        $region->trigger($payload, false);

        $this->assertSame($payload, $capturedPayload);
    }

    protected function tearDown(): void
    {
        \Mockery::close();
        parent::tearDown();
    }
}
