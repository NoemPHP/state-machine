<?php

declare(strict_types=1);

namespace Noem\State\Tests\Integration\Feature\Transitions;

use Noem\State\Feature\Transitions\AddTransition;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Conditional transitions with multiple guards work correctly
 */
#[Group('transitions')]
#[Group('integration')]
class ConditionalTransitionsTest extends TestCase
{
    public function testConditionalBranchingWithMultipleGuards(): void
    {
        $builder = new RegionBuilder();
        
        $region = $builder
            ->setStates('start', 'pathA', 'pathB', 'pathC', 'end')
            ->markInitial('start')
            ->markFinal('end')
            // Branch based on priority field
            ->addBuildStep(new AddTransition('start', 'pathA', fn(object $t): bool => ($t->priority ?? 0) === 1))
            ->addBuildStep(new AddTransition('start', 'pathB', fn(object $t): bool => ($t->priority ?? 0) === 2))
            ->addBuildStep(new AddTransition('start', 'pathC', fn(object $t): bool => ($t->priority ?? 0) === 3))
            // All paths lead to end
            ->addBuildStep(new AddTransition('pathA', 'end'))
            ->addBuildStep(new AddTransition('pathB', 'end'))
            ->addBuildStep(new AddTransition('pathC', 'end'))
            ->build();
        
        // Test path A
        $region->trigger((object)['priority' => 1]);
        $this->assertTrue($region->isInState('pathA'));
        
        $region->trigger((object)[]);
        $this->assertTrue($region->isInState('end'));
    }
    
    public function testFallbackGuardPattern(): void
    {
        $builder = new RegionBuilder();
        
        $region = $builder
            ->setStates('start', 'special', 'default', 'end')
            ->markInitial('start')
            // Try special path first, fall back to default
            ->addBuildStep(new AddTransition('start', 'special', fn(object $t): bool => isset($t->specialCondition) && $t->specialCondition))
            ->addBuildStep(new AddTransition('start', 'default', fn(object $t): bool => true)) // Always true fallback
            ->build();
        
        // Without special condition, should go to default
        $region->trigger((object)[]);
        $this->assertTrue($region->isInState('default'));
        
        // Reset for second test
        $region2 = $builder->build();
        
        // With special condition, should go to special
        $region2->trigger((object)['specialCondition' => true]);
        $this->assertTrue($region2->isInState('special'));
    }
    
    public function testComplexConditionInGuard(): void
    {
        $builder = new RegionBuilder();
        
        $region = $builder
            ->setStates('idle', 'processing', 'complete')
            ->markInitial('idle')
            ->addBuildStep(new AddTransition('idle', 'processing', function(object $t): bool {
                return isset($t->data) 
                    && is_array($t->data) 
                    && count($t->data) > 0 
                    && $t->ready === true;
            }))
            ->addBuildStep(new AddTransition('processing', 'complete'))
            ->build();
        
        // Should not transition with incomplete trigger
        $region->trigger((object)['data' => []]);
        $this->assertTrue($region->isInState('idle'));
        
        // Should transition with complete trigger
        $region->trigger((object)['data' => [1, 2, 3], 'ready' => true]);
        $this->assertTrue($region->isInState('processing'));
    }
}
