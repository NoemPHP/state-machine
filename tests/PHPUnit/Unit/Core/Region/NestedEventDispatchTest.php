<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Core\Region;

use Noem\State\Chains\DispatchAction;
use Noem\State\Chains\DoTransition;
use Noem\State\Chains\Notification;
use Noem\State\Chains\Path;
use Noem\State\Chains\Params\Action;
use Noem\State\Events;
use Noem\State\Region;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Events triggered during dispatch are queued for next cycle
 */
#[Group('region')]
#[Group('event-dispatching')]
class NestedEventDispatchTest extends TestCase
{
    public function testNestedEventsAreQueuedNotProcessedImmediately(): void
    {
        $events = \Mockery::mock(Events::class);
        $actionChain = \Mockery::mock(DispatchAction::class);
        $actionChain->shouldReceive('link');

        $transitionChain = \Mockery::mock(DoTransition::class);
        $pathChain = \Mockery::mock(Path::class);
        $notificationChain = \Mockery::mock(Notification::class);

        $notificationChain->shouldReceive('call')
            ->andReturn([]);

        $processedIds = [];
        $region = new Region(
            $events,
            'initial',
            'final',
            $actionChain,
            $transitionChain,
            $pathChain,
            $notificationChain
        );

        $actionChain->shouldReceive('call')
            ->andReturnUsing(function (Action $context) use (&$processedIds, $region) {
                $processedIds[] = $context->payload->id;

                // During processing of event 1, trigger event 2 with enqueue
                if ($context->payload->id === 1) {
                    $region->trigger((object)['id' => 2], true);
                }

                return 'initial';
            });

        // Trigger event 1 immediately
        $region->trigger((object)['id' => 1], false);

        // Only event 1 should have been processed in first batch
        $this->assertEquals([1], $processedIds);

        // Trigger another event to process the queue
        $region->trigger((object)['id' => 3], false);

        // Now both event 2 (queued) and event 3 should be processed
        $this->assertEquals([1, 2, 3], $processedIds);
    }

    public function testCopyingQueuePreventsInfiniteLoop(): void
    {
        $events = \Mockery::mock(Events::class);
        $actionChain = \Mockery::mock(DispatchAction::class);
        $actionChain->shouldReceive('link');

        $transitionChain = \Mockery::mock(DoTransition::class);
        $pathChain = \Mockery::mock(Path::class);
        $notificationChain = \Mockery::mock(Notification::class);

        $notificationChain->shouldReceive('call')
            ->andReturn([]);

        $callCount = 0;
        $maxCalls = 10; // Safety limit

        $region = new Region(
            $events,
            'initial',
            'final',
            $actionChain,
            $transitionChain,
            $pathChain,
            $notificationChain
        );

        $actionChain->shouldReceive('call')
            ->andReturnUsing(function (Action $context) use (&$callCount, $maxCalls, $region) {
                $callCount++;

                // Try to create infinite loop by triggering during dispatch
                if ($callCount < $maxCalls && $context->payload->trigger === true) {
                    $region->trigger((object)['trigger' => true], true);
                }

                return 'initial';
            });

        // This should not cause infinite loop because queue is copied before processing
        $region->trigger((object)['trigger' => true], false);

        // Should only process once per trigger, not infinite
        $this->assertEquals(1, $callCount, 'Should process only the initial event in first batch');
    }

    protected function tearDown(): void
    {
        \Mockery::close();
        parent::tearDown();
    }
}
