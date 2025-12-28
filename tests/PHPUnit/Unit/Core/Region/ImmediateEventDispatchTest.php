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
 * Acceptance Criterion: A region can trigger events with immediate dispatch
 */
#[Group('region')]
#[Group('event-triggering')]
class ImmediateEventDispatchTest extends TestCase
{
    public function testTriggerDispatchesEventImmediately(): void
    {
        $events = \Mockery::mock(Events::class);
        $actionChain = \Mockery::mock(DispatchAction::class);
        $actionChain->shouldReceive('link');

        $transitionChain = \Mockery::mock(DoTransition::class);
        $pathChain = \Mockery::mock(Path::class);
        $notificationChain = \Mockery::mock(Notification::class);

        $notificationChain->shouldReceive('call')
            ->once()
            ->andReturn([]);

        $actionChain->shouldReceive('call')
            ->once()
            ->andReturn('initial');

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
        $result = $region->trigger($payload);

        $this->assertSame($payload, $result);
    }

    public function testTriggerDefaultsToImmediateDispatch(): void
    {
        $events = \Mockery::mock(Events::class);
        $actionChain = \Mockery::mock(DispatchAction::class);
        $actionChain->shouldReceive('link');

        $transitionChain = \Mockery::mock(DoTransition::class);
        $pathChain = \Mockery::mock(Path::class);
        $notificationChain = \Mockery::mock(Notification::class);

        $notificationChain->shouldReceive('call')
            ->once()
            ->andReturn([]);

        $actionChain->shouldReceive('call')
            ->once()
            ->andReturn('initial');

        $region = new Region(
            $events,
            'initial',
            'final',
            $actionChain,
            $transitionChain,
            $pathChain,
            $notificationChain
        );

        // Call trigger without the enqueue parameter
        $region->trigger((object)['data' => 'test']);

        // If action chain was called, dispatch happened immediately
        $this->assertTrue(true);
    }

    public function testTriggerReturnsSamePayloadObject(): void
    {
        $events = \Mockery::mock(Events::class);
        $actionChain = \Mockery::mock(DispatchAction::class);
        $actionChain->shouldReceive('link');

        $transitionChain = \Mockery::mock(DoTransition::class);
        $pathChain = \Mockery::mock(Path::class);
        $notificationChain = \Mockery::mock(Notification::class);

        $notificationChain->shouldReceive('call')
            ->once()
            ->andReturn([]);

        $actionChain->shouldReceive('call')
            ->andReturn('initial');

        $region = new Region(
            $events,
            'initial',
            'final',
            $actionChain,
            $transitionChain,
            $pathChain,
            $notificationChain
        );

        $payload = (object)['id' => 123];
        $returned = $region->trigger($payload, false);

        $this->assertSame($payload, $returned);
    }

    protected function tearDown(): void
    {
        \Mockery::close();
        parent::tearDown();
    }
}
