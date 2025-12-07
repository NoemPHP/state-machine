<?php

declare(strict_types=1);

namespace Noem\State\Tests\Integration\Feature\Transitions;

use Noem\State\Feature\Transitions\AddTransition;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * Acceptance Criterion: Transitions work with hierarchical regions
 */
#[Group('transitions')]
#[Group('integration')]
class HierarchicalRegionsTest extends TestCase
{
    public function testParentWaitsForChildToComplete(): void
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

        // Parent cannot transition while child is not final
        $parentRegion->trigger(new stdClass());
        $this->assertEquals('parent_start', $parentRegion->currentState());

        // Complete child
        $childRegion->trigger(new stdClass());
        $this->assertTrue($childRegion->isFinal());

        // Now parent can transition
        $parentRegion->trigger(new stdClass());
        $this->assertEquals('parent_end', $parentRegion->currentState());
    }

    public function testOnEnterParentPropagation(): void
    {
        $childEntered = false;

        $parentBuilder = new RegionBuilder();

        // Child starts in finished state so parent can transition
        $childRegion = $parentBuilder
            ->newInstance()
            ->setStates('child_a')  // Single state - immediately finished
            ->onEnter('child_a', function (object $t) use (&$childEntered) {
                $childEntered = true;
            })
            ->build();

        // Child is now in final state (single state is both initial and final)
        $this->assertTrue($childRegion->isFinal(), 'Child should start in final state');

        $parentRegion = $parentBuilder
            ->setStates('parent_a', 'parent_b')
            ->connect($childRegion)
            ->addBuildStep(new AddTransition('parent_a', 'parent_b'))
            ->build();

        // Reset the flag - onEnter may have been called during child construction
        $childEntered = false;

        // Transition in parent should call onEnterParent on child
        $parentRegion->trigger(new stdClass());

        $this->assertEquals('parent_b', $parentRegion->currentState(), 'Parent should have transitioned');
        $this->assertTrue($childEntered, 'Child onEnter should be called via onEnterParent during parent transition');
    }
}
