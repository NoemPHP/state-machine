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
 * Acceptance Criterion: A region tracks its current state
 */
#[Group('region')]
#[Group('state-management')]
class CurrentStateTrackingTest extends TestCase
{
    public function testRegionInitializesWithInitialState(): void
    {
        $region = $this->createRegion('initial', 'final');

        $this->assertEquals('initial', $region->currentState());
    }

    public function testCurrentStateReturnsCorrectState(): void
    {
        $region = $this->createRegion('start', 'end');

        $currentState = $region->currentState();

        $this->assertIsString($currentState);
        $this->assertEquals('start', $currentState);
    }

    public function testCurrentStateRemainsConsistent(): void
    {
        $region = $this->createRegion('myState', 'finalState');
        $state1 = $region->currentState();
        $state2 = $region->currentState();

        $this->assertEquals($state1, $state2);
    }

    private function createRegion(string $initial, string $final): Region
    {
        $events = \Mockery::mock(Events::class);
        $actionChain = \Mockery::mock(DispatchAction::class);
        $actionChain->shouldReceive('link');
        $transitionChain = \Mockery::mock(DoTransition::class);
        $pathChain = \Mockery::mock(Path::class);
        $notificationChain = \Mockery::mock(Notification::class);

        // Action chain should return the current state by default
        $actionChain->shouldReceive('call')
            ->andReturn($initial)
            ->byDefault();

        return new Region(
            $events,
            $initial,
            $final,
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
