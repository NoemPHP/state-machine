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
 * Acceptance Criterion: Multiple events can be triggered and queued
 */
#[Group('region')]
#[Group('event-triggering')]
class MultipleEventQueuingTest extends TestCase
{
    public function testMultipleEventsCanBeTriggeredAndQueued(): void
    {
        $events = \Mockery::mock(Events::class);
        $actionChain = \Mockery::mock(DispatchAction::class);
        $actionChain->shouldReceive('link');

        $transitionChain = \Mockery::mock(DoTransition::class);
        $pathChain = \Mockery::mock(Path::class);

        $region = new Region(
            $events,
            'initial',
            'final',
            $actionChain,
            $transitionChain,
            $pathChain
        );

        $payload1 = (object)['id' => 1];
        $payload2 = (object)['id' => 2];
        $payload3 = (object)['id' => 3];

        $result1 = $region->trigger($payload1, true);
        $result2 = $region->trigger($payload2, true);
        $result3 = $region->trigger($payload3, true);

        $this->assertSame($payload1, $result1);
        $this->assertSame($payload2, $result2);
        $this->assertSame($payload3, $result3);
    }

    public function testMixedImmediateAndQueuedEvents(): void
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

        // Enqueue first
        $region->trigger((object)['id' => 1], true);
        $this->assertEquals(0, $callCount, 'Enqueued event should not dispatch');

        // Immediate dispatch
        $region->trigger((object)['id' => 2], false);
        $this->assertEquals(2, $callCount, 'Immediate dispatch should process queue + current event');

        // Enqueue again
        $region->trigger((object)['id' => 3], true);
        $this->assertEquals(2, $callCount, 'Another enqueued event should not dispatch');
    }

    protected function tearDown(): void
    {
        \Mockery::close();
        parent::tearDown();
    }
}
