<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Transitions\EdgeCases;

use Noem\State\Feature\Transitions\AddTransition;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * Acceptance Criterion: Transition evaluation with no matching guards maintains state
 */
#[Group('transitions')]
#[Group('edge-cases')]
class NoMatchingGuardsTest extends TestCase
{
    public function testNoMatchingGuardsMaintainsState(): void
    {
        $region = (new RegionBuilder())
            ->setStates('start', 'pathA', 'pathB', 'end')
            ->markInitial('start')
            ->addBuildStep(new AddTransition('start', 'pathA', fn(object $t): bool => isset($t->goA)))
            ->addBuildStep(new AddTransition('start', 'pathB', fn(object $t): bool => isset($t->goB)))
            ->build();
        
        $this->assertEquals('start', $region->currentState());
        
        // Trigger without matching conditions
        $region->trigger(new stdClass());
        
        // State should remain unchanged
        $this->assertEquals('start', $region->currentState());
    }
    
    public function testAllGuardsFalseKeepsCurrentState(): void
    {
        $region = (new RegionBuilder())
            ->setStates('idle', 'processing', 'complete')
            ->markInitial('idle')
            ->addBuildStep(new AddTransition('idle', 'processing', fn(object $t): bool => false))
            ->addBuildStep(new AddTransition('idle', 'complete', fn(object $t): bool => false))
            ->build();
        
        $region->trigger(new stdClass());
        
        // No transition should occur
        $this->assertEquals('idle', $region->currentState());
    }
}
