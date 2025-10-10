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
 * Acceptance Criterion: Dispatching clears the event queue to prevent infinite loops
 */
#[Group('region')]
#[Group('event-dispatching')]
class EventQueueClearingTest extends TestCase
{
    public function testQueueIsClearedBeforeProcessing(): void
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

        // Enqueue 3 events
        $region->trigger((object)['id' => 1], true);
        $region->trigger((object)['id' => 2], true);
        $region->trigger((object)['id' => 3], true);

        // Dispatch
        $region->trigger((object)['id' => 4], false);
        $firstBatchCount = $callCount;

        // Dispatch again - queue should be empty
        $region->trigger((object)['id' => 5], false);
        $secondBatchCount = $callCount;

        $this->assertEquals(4, $firstBatchCount, 'First batch should process 4 events');
        $this->assertEquals(5, $secondBatchCount, 'Second batch should only process 1 new event');
    }

    public function testQueueClearingPreventsReprocessing(): void
    {
        $events = \Mockery::mock(Events::class);
        $actionChain = \Mockery::mock(DispatchAction::class);
        $actionChain->shouldReceive('link');

        $transitionChain = \Mockery::mock(DoTransition::class);
        $pathChain = \Mockery::mock(Path::class);

        $processedIds = [];
        $actionChain->shouldReceive('call')
            ->andReturnUsing(function (Action $context) use (&$processedIds) {
                $processedIds[] = $context->payload->id;
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

        $region->trigger((object)['id' => 1], true);
        $region->trigger((object)['id' => 2], false);
        
        // Trigger again
        $region->trigger((object)['id' => 3], false);

        // IDs should not be duplicated
        $this->assertEquals([1, 2, 3], $processedIds);
        $this->assertCount(3, $processedIds);
    }

    protected function tearDown(): void
    {
        \Mockery::close();
        parent::tearDown();
    }
}
