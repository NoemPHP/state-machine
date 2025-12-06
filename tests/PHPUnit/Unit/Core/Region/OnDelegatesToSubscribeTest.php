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
 * Acceptance Criterion: Region.on() delegates to NotificationChain.subscribe()
 *
 * @see specs/core/region.yaml
 */
#[Group('region')]
#[Group('notification-subscription')]
class OnDelegatesToSubscribeTest extends TestCase
{
    public function testOnDelegatesToNotificationChainSubscribe(): void
    {
        // Arrange
        $listener = fn(object $event) => null;
        $notificationChain = \Mockery::mock(Notification::class);
        $deregisterFunc = fn() => null;

        // Expect subscribe to be called with the listener
        $notificationChain->shouldReceive('subscribe')
            ->once()
            ->with($listener)
            ->andReturn($deregisterFunc);

        $region = $this->createRegion($notificationChain);

        // Act
        $result = $region->on($listener);

        // Assert
        $this->assertSame($deregisterFunc, $result);
    }

    public function testOnPassesListenerDirectly(): void
    {
        // Arrange
        $listener = fn(object $event, ?\Noem\State\Region $region = null) => 'handled';
        $notificationChain = \Mockery::mock(Notification::class);

        $notificationChain->shouldReceive('subscribe')
            ->once()
            ->with(\Mockery::on(function ($arg) use ($listener) {
                return $arg === $listener;
            }))
            ->andReturn(fn() => null);

        $region = $this->createRegion($notificationChain);

        // Act
        $region->on($listener);

        // Assert - verified by Mockery expectations
        $this->assertTrue(true);
    }

    private function createRegion(Notification $notificationChain): Region
    {
        $events = \Mockery::mock(Events::class);
        $actionChain = \Mockery::mock(DispatchAction::class);
        $actionChain->shouldReceive('link');
        $actionChain->shouldReceive('call')->andReturn('initial')->byDefault();
        $transitionChain = \Mockery::mock(DoTransition::class);
        $pathChain = \Mockery::mock(Path::class);

        return new Region(
            $events,
            'initial',
            'final',
            $actionChain,
            $transitionChain,
            $pathChain,
            $notificationChain
        );
    }

    protected function tearDown(): void
    {
        \Mockery::close();
        parent::tearDown();
    }
}
