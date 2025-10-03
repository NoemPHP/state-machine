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
 * Acceptance Criterion: Action chain receives region and trigger payload
 */
#[Group('region')]
#[Group('action-chain-integration')]
class ActionChainContextTest extends TestCase
{
    public function testActionChainReceivesActionContext(): void
    {
        $events = \Mockery::mock(Events::class);
        $actionChain = \Mockery::mock(DispatchAction::class);
        $transitionChain = \Mockery::mock(DoTransition::class);
        $pathChain = \Mockery::mock(Path::class);

        $actionChain->shouldReceive('call')
            ->once()
            ->with(\Mockery::type(Action::class))
            ->andReturn('initial');

        $region = new Region(
            $events,
            'initial',
            'final',
            $actionChain,
            $transitionChain,
            $pathChain
        );

        $region->trigger((object)['data' => 'test'], false);

        $this->assertTrue(true);
    }

    public function testActionContextContainsRegion(): void
    {
        $events = \Mockery::mock(Events::class);
        $actionChain = \Mockery::mock(DispatchAction::class);
        $transitionChain = \Mockery::mock(DoTransition::class);
        $pathChain = \Mockery::mock(Path::class);

        $capturedRegion = null;
        $actionChain->shouldReceive('call')
            ->andReturnUsing(function (Action $context) use (&$capturedRegion) {
                $capturedRegion = $context->region;
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

        $region->trigger((object)['data' => 'test'], false);

        $this->assertSame($region, $capturedRegion);
    }

    public function testActionContextContainsPayload(): void
    {
        $events = \Mockery::mock(Events::class);
        $actionChain = \Mockery::mock(DispatchAction::class);
        $transitionChain = \Mockery::mock(DoTransition::class);
        $pathChain = \Mockery::mock(Path::class);

        $capturedPayload = null;
        $actionChain->shouldReceive('call')
            ->andReturnUsing(function (Action $context) use (&$capturedPayload) {
                $capturedPayload = $context->payload;
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

        $payload = (object)['id' => 123, 'name' => 'test'];
        $region->trigger($payload, false);

        $this->assertSame($payload, $capturedPayload);
    }

    public function testActionContextHasCurrentState(): void
    {
        $events = \Mockery::mock(Events::class);
        $actionChain = \Mockery::mock(DispatchAction::class);
        $transitionChain = \Mockery::mock(DoTransition::class);
        $pathChain = \Mockery::mock(Path::class);

        $capturedState = null;
        $actionChain->shouldReceive('call')
            ->andReturnUsing(function (Action $context) use (&$capturedState) {
                $capturedState = $context->currentState;
                return 'myState'; // Return the same state to avoid triggering transition
            });

        $region = new Region(
            $events,
            'myState',
            'final',
            $actionChain,
            $transitionChain,
            $pathChain
        );

        $region->trigger((object)['data' => 'test'], false);

        $this->assertEquals('myState', $capturedState);
    }

    protected function tearDown(): void
    {
        \Mockery::close();
        parent::tearDown();
    }
}
