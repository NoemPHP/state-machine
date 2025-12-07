<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Transitions\Evaluation;

use Noem\State\Feature\Transitions\AddTransition;
use Noem\State\Feature\Transitions\TransitionsFeature;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * Acceptance Criterion: Transitions are checked after each action dispatch
 */
#[Group('transitions')]
#[Group('transition-evaluation')]
class ChecksAfterActionTest extends TestCase
{
    public function testChecksTransitionAfterActionDispatch(): void
    {
        $trigger = new stdClass();
        $trigger->ready = true;

        $region = (new RegionBuilder())
            ->enableFeatures(new TransitionsFeature())
            ->setStates('idle', 'working')
            ->addBuildStep(new AddTransition('idle', 'working', fn(object $t): bool => $t->ready))
            ->build();

        $this->assertFalse($region->isInState('working'));

        // Trigger action - should automatically transition
        $region->trigger($trigger);

        $this->assertTrue($region->isInState('working'));
    }

    public function testChecksTransitionEvenWithoutExplicitActionHandler(): void
    {
        $trigger = new stdClass();

        $region = (new RegionBuilder())
            ->enableFeatures(new TransitionsFeature())
            ->setStates('start', 'end')
            ->addBuildStep(new AddTransition('start', 'end'))
            ->build();

        $region->trigger($trigger);

        $this->assertTrue($region->isInState('end'));
    }
}
