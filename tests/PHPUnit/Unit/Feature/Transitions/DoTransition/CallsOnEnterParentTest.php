<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Transitions\DoTransition;

use Noem\State\Chains\ConnectedRegions;
use Noem\State\Chains\DoTransition;
use Noem\State\Chains\Params\Connection;
use Noem\State\Chains\Params\Transition;
use Noem\State\Events;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * Acceptance Criterion: DoTransition calls onEnterParent on connected regions
 */
#[Group('transitions')]
#[Group('transition-execution')]
class CallsOnEnterParentTest extends TestCase
{
    public function testCallsOnEnterParentOnConnectedRegions(): void
    {
        $parentRegion = (new RegionBuilder())
            ->setStates('parent_a', 'parent_b')
            ->build();

        // Create a tracking mechanism to verify onEnterParent is called
        $onEnterParentCalled = false;
        $receivedPayload = null;

        // Create a mock child region
        $childRegion = \Mockery::mock(\Noem\State\Region::class);
        $childRegion->shouldReceive('onEnterParent')
            ->with(\Mockery::type('object'))
            ->once()
            ->andReturnUsing(function ($payload) use (&$onEnterParentCalled, &$receivedPayload) {
                $onEnterParentCalled = true;
                $receivedPayload = $payload;
            });

        $events = \Mockery::mock(Events::class);
        $events->shouldReceive('onExitState')->once();
        $events->shouldReceive('onEnterState')->once();

        $connectedRegions = \Mockery::mock(ConnectedRegions::class);
        $connectedRegions->shouldReceive('call')
            ->with(\Mockery::type(Connection::class))
            ->andReturn([$childRegion])
            ->once();

        $doTransition = new DoTransition($connectedRegions, $events);

        $payload = new stdClass();
        $context = new Transition($parentRegion, $payload, 'parent_a');

        $doTransition->call($context);

        $this->assertTrue($onEnterParentCalled, 'onEnterParent should be called on connected regions');
        $this->assertSame($payload, $receivedPayload, 'Payload should be passed to onEnterParent');

        \Mockery::close();
    }
    
    public function testCallsOnEnterParentAfterExitBeforeEnter(): void
    {
        $parentRegion = (new RegionBuilder())
            ->setStates('a', 'b')
            ->build();
        
        $callOrder = [];
        
        $events = \Mockery::mock(Events::class);
        $events->shouldReceive('onExitState')
            ->andReturnUsing(function () use (&$callOrder) {
                $callOrder[] = 'exit';
            });
        $events->shouldReceive('onEnterState')
            ->andReturnUsing(function () use (&$callOrder) {
                $callOrder[] = 'enter';
            });
        
        $connectedRegions = \Mockery::mock(ConnectedRegions::class);
        $connectedRegions->shouldReceive('call')
            ->andReturnUsing(function () use (&$callOrder) {
                $callOrder[] = 'connected';
                return [];
            });
        
        $doTransition = new DoTransition($connectedRegions, $events);
        
        $payload = new stdClass();
        $context = new Transition($parentRegion, $payload, 'a');
        
        $doTransition->call($context);
        
        // Verify order: exit, then connected regions, then enter
        $this->assertEquals(['exit', 'connected', 'enter'], $callOrder);
        
        \Mockery::close();
    }
    
    protected function tearDown(): void
    {
        \Mockery::close();
        parent::tearDown();
    }
}
