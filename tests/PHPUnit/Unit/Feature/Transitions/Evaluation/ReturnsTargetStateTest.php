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
 * Acceptance Criterion: Target state is returned when transition enabled
 */
#[Group('transitions')]
#[Group('transition-evaluation')]
class ReturnsTargetStateTest extends TestCase
{
    public function testReturnsTargetStateWhenGuardMatches(): void
    {
        $trigger = new stdClass();

        $region = (new RegionBuilder())
            ->enableFeatures(new TransitionsFeature())
            ->setStates('start', 'end')
            ->addBuildStep(new AddTransition('start', 'end', fn(object $t): bool => true))
            ->build();

        $region->trigger($trigger);

        $this->assertTrue($region->isInState('end'));
    }
}
