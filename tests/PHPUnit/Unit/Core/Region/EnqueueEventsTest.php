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
 * Acceptance Criterion: A region can enqueue events for later dispatch
 */
#[Group('region')]
#[Group('event-triggering')]
class EnqueueEventsTest extends TestCase
{
    public function testTriggerWithEnqueueDoesNotDispatchImmediately(): void
    {
        $events = \Mockery::mock(Events::class);
        $actionChain = \Mockery::mock(DispatchAction::class);
        $actionChain->shouldReceive('link');

        $transitionChain = \Mockery::mock(DoTransition::class);
        $pathChain = \Mockery::mock(Path::class);
        $notificationChain = \Mockery::mock(Notification::class);
        $notificationChain = \Mockery::mock(Notification::class);

        // Action chain should NOT be called when enqueue is true
        $actionChain->shouldReceive('call')
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

        $payload = (object)['data' => 'test'];
        $region->trigger($payload, true);

        // If we get here without exceptions, enqueue worked
        $this->assertTrue(true);
    }

    public function testEnqueuedEventReturnsPayload(): void
    {
        $events = \Mockery::mock(Events::class);
        $actionChain = \Mockery::mock(DispatchAction::class);
        $actionChain->shouldReceive('link');

        $transitionChain = \Mockery::mock(DoTransition::class);
        $pathChain = \Mockery::mock(Path::class);
        $notificationChain = \Mockery::mock(Notification::class);
        $notificationChain = \Mockery::mock(Notification::class);

        $actionChain->shouldReceive('call')
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

        $payload = (object)['id' => 456];
        $returned = $region->trigger($payload, true);

        $this->assertSame($payload, $returned);
    }

    public function testMultipleEnqueuedEventsDoNotDispatch(): void
    {
        $events = \Mockery::mock(Events::class);
        $actionChain = \Mockery::mock(DispatchAction::class);
        $actionChain->shouldReceive('link');

        $transitionChain = \Mockery::mock(DoTransition::class);
        $pathChain = \Mockery::mock(Path::class);
        $notificationChain = \Mockery::mock(Notification::class);
        $notificationChain = \Mockery::mock(Notification::class);

        $actionChain->shouldReceive('call')
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

        $region->trigger((object)['id' => 1], true);
        $region->trigger((object)['id' => 2], true);
        $region->trigger((object)['id' => 3], true);

        $this->assertTrue(true);
    }

    protected function tearDown(): void
    {
        \Mockery::close();
        parent::tearDown();
    }
}
