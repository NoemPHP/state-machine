<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Core\Region;

use Noem\State\Chains\DispatchAction;
use Noem\State\Chains\DoTransition;
use Noem\State\Chains\Notification;
use Noem\State\Chains\Path;
use Noem\State\Chains\Params\Action;
use Noem\State\Chains\Params\Notify;
use Noem\State\Events;
use Noem\State\Region;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Region.doDispatch() invokes notificationChain for each dispatched event
 */
#[Group('region')]
#[Group('event-dispatching')]
class DispatchInvokesNotificationChainTest extends TestCase
{
    public function testNotificationChainIsCalledForEachEvent(): void
    {
        $events = \Mockery::mock(Events::class);
        $actionChain = \Mockery::mock(DispatchAction::class);
        $actionChain->shouldReceive('link');
        $actionChain->shouldReceive('call')->andReturn('initial');

        $transitionChain = \Mockery::mock(DoTransition::class);
        $pathChain = \Mockery::mock(Path::class);
        $notificationChain = \Mockery::mock(Notification::class);

        $callCount = 0;
        $notificationChain->shouldReceive('call')
            ->times(2)
            ->andReturnUsing(function (Notify $params) use (&$callCount) {
                $callCount++;
                return [];
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

        $region->trigger((object)['id' => 1], false);
        $region->trigger((object)['id' => 2], false);

        $this->assertEquals(2, $callCount, 'notificationChain should be called once per event');
    }

    public function testNotificationChainReceivesCorrectParameters(): void
    {
        $events = \Mockery::mock(Events::class);
        $actionChain = \Mockery::mock(DispatchAction::class);
        $actionChain->shouldReceive('link');
        $actionChain->shouldReceive('call')->andReturn('initial');

        $transitionChain = \Mockery::mock(DoTransition::class);
        $pathChain = \Mockery::mock(Path::class);
        $notificationChain = \Mockery::mock(Notification::class);

        $capturedParams = null;
        $notificationChain->shouldReceive('call')
            ->once()
            ->andReturnUsing(function (Notify $params) use (&$capturedParams) {
                $capturedParams = $params;
                return [];
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

        $payload = (object)['data' => 'test'];
        $region->trigger($payload, false);

        $this->assertInstanceOf(Notify::class, $capturedParams);
        $this->assertSame($region, $capturedParams->region);
        $this->assertSame($payload, $capturedParams->event);
    }

    public function testNotificationChainCalledBeforeActionChain(): void
    {
        $events = \Mockery::mock(Events::class);
        $actionChain = \Mockery::mock(DispatchAction::class);
        $actionChain->shouldReceive('link');

        $transitionChain = \Mockery::mock(DoTransition::class);
        $pathChain = \Mockery::mock(Path::class);
        $notificationChain = \Mockery::mock(Notification::class);

        $callOrder = [];

        $notificationChain->shouldReceive('call')
            ->once()
            ->andReturnUsing(function () use (&$callOrder) {
                $callOrder[] = 'notification';
                return [];
            });

        $actionChain->shouldReceive('call')
            ->once()
            ->andReturnUsing(function () use (&$callOrder) {
                $callOrder[] = 'action';
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

        $region->trigger((object)['test' => true], false);

        $this->assertEquals(['notification', 'action'], $callOrder,
            'notificationChain should be called before actionChain');
    }

    public function testListenersReturnedByNotificationChainAreInvoked(): void
    {
        $events = \Mockery::mock(Events::class);
        $actionChain = \Mockery::mock(DispatchAction::class);
        $actionChain->shouldReceive('link');
        $actionChain->shouldReceive('call')->andReturn('initial');

        $transitionChain = \Mockery::mock(DoTransition::class);
        $pathChain = \Mockery::mock(Path::class);
        $notificationChain = \Mockery::mock(Notification::class);

        $listenerInvoked = false;
        $listener = function ($trigger, $region) use (&$listenerInvoked) {
            $listenerInvoked = true;
        };

        $notificationChain->shouldReceive('call')
            ->once()
            ->andReturn([$listener]);

        $region = new Region(
            $events,
            'initial',
            'final',
            $actionChain,
            $transitionChain,
            $pathChain,
            $notificationChain
        );

        $region->trigger((object)['test' => true], false);

        $this->assertTrue($listenerInvoked, 'Listeners returned by notificationChain should be invoked');
    }

    protected function tearDown(): void
    {
        \Mockery::close();
        parent::tearDown();
    }
}
