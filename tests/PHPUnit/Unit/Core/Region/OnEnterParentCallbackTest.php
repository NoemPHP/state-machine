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
 * Acceptance Criterion: onEnterParent triggers state entry callbacks
 */
#[Group('region')]
#[Group('event-lifecycle')]
class OnEnterParentCallbackTest extends TestCase
{
    public function testOnEnterParentCallsEventsSystem(): void
    {
        $events = \Mockery::mock(Events::class);
        $actionChain = \Mockery::mock(DispatchAction::class);
        $actionChain->shouldReceive('link');

        $transitionChain = \Mockery::mock(DoTransition::class);
        $pathChain = \Mockery::mock(Path::class);
        $notificationChain = \Mockery::mock(Notification::class);
        $notificationChain = \Mockery::mock(Notification::class);

        $events->shouldReceive('onEnterState')
            ->once()
            ->with(
                \Mockery::type(Region::class),
                'myState',
                \Mockery::type('object')
            );

        $actionChain->shouldReceive('call')
            ->andReturn('myState')
            ->byDefault();

        $region = new Region(
            $events,
            'myState',
            'final',
            $actionChain,
            $transitionChain,
            $pathChain,
            $notificationChain
        );

        $trigger = (object)['data' => 'test'];
        $region->onEnterParent($trigger);

        $this->assertTrue(true);
    }

    public function testOnEnterParentPassesCorrectState(): void
    {
        $events = \Mockery::mock(Events::class);
        $actionChain = \Mockery::mock(DispatchAction::class);
        $actionChain->shouldReceive('link');

        $transitionChain = \Mockery::mock(DoTransition::class);
        $pathChain = \Mockery::mock(Path::class);
        $notificationChain = \Mockery::mock(Notification::class);
        $notificationChain = \Mockery::mock(Notification::class);

        $capturedState = null;
        $events->shouldReceive('onEnterState')
            ->andReturnUsing(function ($region, $state, $trigger) use (&$capturedState) {
                $capturedState = $state;
            });

        $actionChain->shouldReceive('call')
            ->andReturn('specificState')
            ->byDefault();

        $region = new Region(
            $events,
            'specificState',
            'final',
            $actionChain,
            $transitionChain,
            $pathChain,
            $notificationChain
        );

        $region->onEnterParent((object)['data' => 'test']);

        $this->assertEquals('specificState', $capturedState);
    }

    public function testOnEnterParentPassesTriggerPayload(): void
    {
        $events = \Mockery::mock(Events::class);
        $actionChain = \Mockery::mock(DispatchAction::class);
        $actionChain->shouldReceive('link');

        $transitionChain = \Mockery::mock(DoTransition::class);
        $pathChain = \Mockery::mock(Path::class);
        $notificationChain = \Mockery::mock(Notification::class);
        $notificationChain = \Mockery::mock(Notification::class);

        $capturedTrigger = null;
        $events->shouldReceive('onEnterState')
            ->andReturnUsing(function ($region, $state, $trigger) use (&$capturedTrigger) {
                $capturedTrigger = $trigger;
            });

        $actionChain->shouldReceive('call')
            ->andReturn('initial')
            ->byDefault();

        $region = new Region(
            $events,
            'initial',
            'final',
            $actionChain,
            $transitionChain,
            $pathChain,
            $notificationChain
        );

        $payload = (object)['id' => 789, 'name' => 'trigger'];
        $region->onEnterParent($payload);

        $this->assertSame($payload, $capturedTrigger);
    }

    protected function tearDown(): void
    {
        \Mockery::close();
        parent::tearDown();
    }
}
