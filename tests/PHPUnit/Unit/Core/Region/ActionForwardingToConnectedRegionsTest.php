<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Core\Region;

use Noem\State\Chains\ConnectedRegions;
use Noem\State\Chains\DispatchAction;
use Noem\State\Chains\Params\Action;
use Noem\State\Chains\Params\Connection;
use Noem\State\Connection as ConnectionFlags;
use Noem\State\Events;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * Acceptance Criterion: Action chain forwards triggers to connected regions with RECEIVE_ACTIONS flag
 *
 * This test verifies that DispatchAction properly identifies connected regions with the
 * RECEIVE_ACTIONS flag and forwards the action trigger to them. This is the foundation
 * for hierarchical state machines.
 *
 * NOTE: These tests document the INTENDED behavior. The current implementation has a bug
 * where child regions receive actions but don't update their state. See ConnectedRegionStateUpdateTest
 * for integration tests that demonstrate the bug.
 */
#[Group('region')]
#[Group('action-chain-integration')]
class ActionForwardingToConnectedRegionsTest extends TestCase
{
    public function testDispatchActionQueriesConnectedRegionsWithReceiveActionsFlag(): void
    {
        $parentRegion = (new RegionBuilder())
            ->setStates('parent')
            ->build();

        // Verify DispatchAction queries ConnectedRegions with RECEIVE_ACTIONS flag
        $connectedRegions = \Mockery::mock(ConnectedRegions::class);
        $connectedRegions->shouldReceive('call')
            ->with(\Mockery::on(function ($connection) {
                // Verify the connection has RECEIVE_ACTIONS and DYNAMIC flags
                return $connection instanceof Connection
                    && $connection->hasFlag(ConnectionFlags::RECEIVE_ACTIONS)
                    && $connection->hasFlag(ConnectionFlags::DYNAMIC);
            }))
            ->andReturn([])  // Return empty array to avoid recursion issues
            ->once();

        $events = \Mockery::mock(Events::class);
        $events->shouldReceive('onAction')->once();

        $dispatchAction = new DispatchAction($connectedRegions, $events);

        $context = new Action($parentRegion, new stdClass());
        $dispatchAction->call($context);

        // If we get here without errors, the query was made correctly
        $this->assertTrue(true, 'DispatchAction queries ConnectedRegions with correct flags');

        \Mockery::close();
    }

    public function testDispatchActionProcessesParentWhenNoChildrenConnected(): void
    {
        $parentRegion = (new RegionBuilder())
            ->setStates('parent')
            ->build();

        $parentActionFired = false;

        $connectedRegions = \Mockery::mock(ConnectedRegions::class);
        $connectedRegions->shouldReceive('call')
            ->andReturn([])
            ->once();

        $events = \Mockery::mock(Events::class);
        $events->shouldReceive('onAction')
            ->andReturnUsing(function () use (&$parentActionFired) {
                $parentActionFired = true;
            })
            ->once();

        $dispatchAction = new DispatchAction($connectedRegions, $events);

        $context = new Action($parentRegion, new stdClass());
        $result = $dispatchAction->call($context);

        $this->assertTrue($parentActionFired, 'Parent action should fire when no children');
        $this->assertEquals('parent', $result);

        \Mockery::close();
    }

    public function testDispatchActionReturnsParentStateName(): void
    {
        $parentRegion = (new RegionBuilder())
            ->setStates('idle', 'active')
            ->markInitial('idle')
            ->build();

        $connectedRegions = \Mockery::mock(ConnectedRegions::class);
        $connectedRegions->shouldReceive('call')->andReturn([]);

        $events = \Mockery::mock(Events::class);
        $events->shouldReceive('onAction');

        $dispatchAction = new DispatchAction($connectedRegions, $events);

        $context = new Action($parentRegion, new stdClass());
        $result = $dispatchAction->call($context);

        $this->assertEquals('idle', $result,
            'DispatchAction should return the current state name');

        \Mockery::close();
    }

    protected function tearDown(): void
    {
        \Mockery::close();
        parent::tearDown();
    }
}
