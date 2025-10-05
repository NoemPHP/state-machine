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
 * Acceptance Criterion: No transition occurs when connected regions not finished
 */
#[Group('transitions')]
#[Group('transition-evaluation')]
class WaitsForConnectedRegionsTest extends TestCase
{
    public function testWaitsForConnectedRegionsToFinish(): void
    {
        $parentBuilder = new RegionBuilder();

        $childRegion = $parentBuilder
            ->newInstance()
            ->setStates('child_start', 'child_end')
            ->markFinal('child_end')
            ->addBuildStep(new AddTransition('child_start', 'child_end'))
            ->build();

        $parentRegion = $parentBuilder
            ->setStates('parent_start', 'parent_end')
            ->markFinal('parent_end')
            ->connect($childRegion)
            ->addBuildStep(new AddTransition('parent_start', 'parent_end'))
            ->build();
        
        $trigger = new stdClass();
        
        // Parent should not transition while child is not in final state
        $parentRegion->trigger($trigger);
        $this->assertTrue($parentRegion->isInState('parent_start'));

        // Child transitions to final
        $parentRegion->trigger($trigger);

        // Now parent can transition
        $this->assertTrue($parentRegion->isInState('parent_end'));
    }
}
