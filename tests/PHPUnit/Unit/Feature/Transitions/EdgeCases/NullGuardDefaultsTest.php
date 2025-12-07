<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Transitions\EdgeCases;

use Noem\State\Feature\Transitions\AddTransition;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * Acceptance Criterion: Transitions with null guard default to always true
 */
#[Group('transitions')]
#[Group('edge-cases')]
class NullGuardDefaultsTest extends TestCase
{
    public function testNullGuardDefaultsToAlwaysTrue(): void
    {
        $region = (new RegionBuilder())
            ->setStates('start', 'end')
            ->markInitial('start')
            ->addBuildStep(new AddTransition('start', 'end')) // No guard specified
            ->build();

        $this->assertEquals('start', $region->currentState());

        $region->trigger(new stdClass());

        $this->assertEquals('end', $region->currentState());
    }

    public function testTransitionWithoutGuardAlwaysSucceeds(): void
    {
        $region = (new RegionBuilder())
            ->setStates('a', 'b', 'c')
            ->markInitial('a')
            ->addBuildStep(new AddTransition('a', 'b'))
            ->addBuildStep(new AddTransition('b', 'c'))
            ->build();

        // First transition
        $region->trigger(new stdClass());
        $this->assertEquals('b', $region->currentState());

        // Second transition
        $region->trigger(new stdClass());
        $this->assertEquals('c', $region->currentState());
    }
}
