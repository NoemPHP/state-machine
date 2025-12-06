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
 * Acceptance Criterion: Region.on() returns deregister function from chain
 *
 * @see specs/core/region.yaml
 */
#[Group('region')]
#[Group('notification-subscription')]
class OnReturnsDeregisterTest extends TestCase
{
    public function testOnReturnsCallable(): void
    {
        // Arrange
        $notificationChain = \Mockery::mock(Notification::class);
        $deregisterFunc = fn() => 'deregistered';

        $notificationChain->shouldReceive('subscribe')
            ->andReturn($deregisterFunc);

        $region = $this->createRegion($notificationChain);

        // Act
        $result = $region->on(fn(object $e) => null);

        // Assert
        $this->assertIsCallable($result);
        $this->assertSame($deregisterFunc, $result);
    }

    public function testDeregisterFunctionFromChainWorks(): void
    {
        // Arrange
        $deregistered = false;
        $notificationChain = \Mockery::mock(Notification::class);
        $deregisterFunc = function () use (&$deregistered) {
            $deregistered = true;
        };

        $notificationChain->shouldReceive('subscribe')
            ->andReturn($deregisterFunc);

        $region = $this->createRegion($notificationChain);

        // Act
        $deregister = $region->on(fn(object $e) => null);
        $deregister();

        // Assert
        $this->assertTrue($deregistered);
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
