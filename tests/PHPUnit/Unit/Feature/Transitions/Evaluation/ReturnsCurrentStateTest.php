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
 * Acceptance Criterion: Current state is returned when no transition enabled
 */
#[Group('transitions')]
#[Group('transition-evaluation')]
class ReturnsCurrentStateTest extends TestCase
{
    public function testMaintainsStateWhenNoGuardMatches(): void
    {
        $trigger = new stdClass();
        $trigger->ready = false;
        
        $region = (new RegionBuilder())
            ->enableFeatures(new TransitionsFeature())
            ->setStates('idle', 'working')
            ->addBuildStep(new AddTransition('idle', 'working', fn(object $t): bool => $t->ready))
            ->build();
        
        $this->assertTrue($region->isInState('idle'));

        $region->trigger($trigger);

        // Should stay in idle since guard returns false
        $this->assertTrue($region->isInState('idle'));
    }
}
