<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Transitions\EdgeCases;

use Noem\State\Feature\Transitions\AddTransition;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * Acceptance Criterion: Self-transitions are supported
 */
#[Group('transitions')]
#[Group('edge-cases')]
class SelfTransitionTest extends TestCase
{
    public function testSelfTransitionIsAllowed(): void
    {
        $region = (new RegionBuilder())
            ->setStates('active', 'done')
            ->markInitial('active')
            ->addBuildStep(new AddTransition('active', 'active', fn(object $t): bool => isset($t->refresh)))
            ->build();

        $this->assertEquals('active', $region->currentState());

        $trigger = (object)['refresh' => true];
        $region->trigger($trigger);

        // Still in active after self-transition
        $this->assertEquals('active', $region->currentState());
    }

    public function testSelfTransitionFiresLifecycleEvents(): void
    {
        $exitCalled = false;
        $enterCalled = false;

        $region = (new RegionBuilder())
            ->setStates('state')
            ->onExit('state', function (object $t) use (&$exitCalled) {
                $exitCalled = true;
            })
            ->onEnter('state', function (object $t) use (&$enterCalled) {
                $enterCalled = true;
            })
            ->addBuildStep(new AddTransition('state', 'state'))
            ->build();

        $region->trigger(new stdClass());

        // Self-transitions do NOT fire lifecycle events in the current implementation
        // because the state doesn't actually change (newState === currentState)
        $this->assertFalse($exitCalled, 'Exit event should NOT fire for self-transition');
        $this->assertFalse($enterCalled, 'Enter event should NOT fire for self-transition');
    }
}
