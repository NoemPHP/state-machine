<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Transitions\EdgeCases;

use Noem\State\Feature\Transitions\AddTransition;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Multiple guards to same target state work correctly
 */
#[Group('transitions')]
#[Group('edge-cases')]
class MultipleGuardsSameTargetTest extends TestCase
{
    public function testMultiplePathsToSameTarget(): void
    {
        $region = (new RegionBuilder())
            ->setStates('start', 'finish')
            ->markInitial('start')
            // Multiple guards to same target (OR logic)
            ->addBuildStep(new AddTransition('start', 'finish', fn(object $t): bool => isset($t->condition1)))
            ->addBuildStep(new AddTransition('start', 'finish', fn(object $t): bool => isset($t->condition2)))
            ->addBuildStep(new AddTransition('start', 'finish', fn(object $t): bool => isset($t->condition3)))
            ->build();
        
        $this->assertEquals('start', $region->currentState());
        
        // Trigger with condition2
        $region->trigger((object)['condition2' => true]);
        
        $this->assertEquals('finish', $region->currentState());
    }
    
    public function testFirstMatchingGuardWinsWithSameTarget(): void
    {
        $callOrder = [];
        
        $region = (new RegionBuilder())
            ->setStates('start', 'end')
            ->markInitial('start')
            // Guards evaluated in reverse order (LIFO)
            ->addBuildStep(new AddTransition('start', 'end', function(object $t) use (&$callOrder): bool {
                $callOrder[] = 'first';
                return false;
            }))
            ->addBuildStep(new AddTransition('start', 'end', function(object $t) use (&$callOrder): bool {
                $callOrder[] = 'second';
                return true;
            }))
            ->addBuildStep(new AddTransition('start', 'end', function(object $t) use (&$callOrder): bool {
                $callOrder[] = 'third';
                return false;
            }))
            ->build();
        
        $region->trigger((object)[]);
        
        // Evaluation stops at first true
        $this->assertEquals(['third', 'second'], $callOrder);
        $this->assertEquals('end', $region->currentState());
    }
}
