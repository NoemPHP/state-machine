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
 * Acceptance Criterion: Dispatching processes all queued events in order
 */
#[Group('region')]
#[Group('event-dispatching')]
class ProcessQueuedEventsTest extends TestCase
{
    public function testDispatchProcessesAllQueuedEvents(): void
    {
        $events = \Mockery::mock(Events::class);
        $actionChain = \Mockery::mock(DispatchAction::class);
        $actionChain->shouldReceive('link');

        $transitionChain = \Mockery::mock(DoTransition::class);
        $pathChain = \Mockery::mock(Path::class);
        $notificationChain = \Mockery::mock(Notification::class);
        $notificationChain = \Mockery::mock(Notification::class);

        $processedPayloads = [];
        $actionChain->shouldReceive('call')
            ->andReturnUsing(function (Action $context) use (&$processedPayloads) {
                $processedPayloads[] = $context->payload;
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

        // Enqueue multiple events
        $payload1 = (object)['id' => 1];
        $payload2 = (object)['id' => 2];
        $payload3 = (object)['id' => 3];

        $region->trigger($payload1, true);
        $region->trigger($payload2, true);
        $region->trigger($payload3, true);

        // Trigger with immediate dispatch to process queue
        $region->trigger((object)['id' => 4], false);

        $this->assertCount(4, $processedPayloads);
    }

    public function testEventsProcessedInFIFOOrder(): void
    {
        $events = \Mockery::mock(Events::class);
        $actionChain = \Mockery::mock(DispatchAction::class);
        $actionChain->shouldReceive('link');

        $transitionChain = \Mockery::mock(DoTransition::class);
        $pathChain = \Mockery::mock(Path::class);
        $notificationChain = \Mockery::mock(Notification::class);
        $notificationChain = \Mockery::mock(Notification::class);

        $order = [];
        $actionChain->shouldReceive('call')
            ->andReturnUsing(function (Action $context) use (&$order) {
                $order[] = $context->payload->id;
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

        $region->trigger((object)['id' => 1], true);
        $region->trigger((object)['id' => 2], true);
        $region->trigger((object)['id' => 3], true);
        $region->trigger((object)['id' => 4], false);

        $this->assertEquals([1, 2, 3, 4], $order);
    }

    protected function tearDown(): void
    {
        \Mockery::close();
        parent::tearDown();
    }
}
