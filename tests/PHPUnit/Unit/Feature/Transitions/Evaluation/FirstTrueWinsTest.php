<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Transitions\Evaluation;

use Noem\State\Feature\Transitions\AddTransition;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: First guard returning true triggers transition
 */
#[Group('transitions')]
#[Group('transition-evaluation')]
class FirstTrueWinsTest extends TestCase
{
    public function testFirstTrueGuardWins(): void
    {
        $builder = new RegionBuilder();
        $firstGuardCalled = false;

        $region = $builder
            ->setStates('start', 'pathA', 'pathB')
            ->markInitial('start')
            // Guards are evaluated in the order they are added
            // Add pathA first (will be evaluated first)
            ->addBuildStep(new AddTransition('start', 'pathA', fn(object $t): bool => true))
            // Then pathB (will be evaluated second if pathA's guard returns false)
            ->addBuildStep(new AddTransition('start', 'pathB', function(object $t) use (&$firstGuardCalled): bool {
                $firstGuardCalled = true;
                return true;
            }))
            ->build();

        $region->trigger((object)[]);

        $this->assertTrue($region->isInState('pathA'));
        $this->assertFalse($firstGuardCalled, 'Second guard should not be called');
    }

    public function testEvaluatesMultipleGuardsUntilFirstTrue(): void
    {
        $builder = new RegionBuilder();
        $callOrder = [];

        $region = $builder
            ->setStates('start', 'end')
            ->markInitial('start')
            // Guards are evaluated in the order they are added
            ->addBuildStep(new AddTransition('start', 'end', function(object $t) use (&$callOrder): bool {
                $callOrder[] = 'guard1'; // This will be evaluated first
                return false;
            }))
            ->addBuildStep(new AddTransition('start', 'end', function(object $t) use (&$callOrder): bool {
                $callOrder[] = 'guard2'; // Second
                return false;
            }))
            ->addBuildStep(new AddTransition('start', 'end', function(object $t) use (&$callOrder): bool {
                $callOrder[] = 'guard3'; // Third
                return false;
            }))
            ->addBuildStep(new AddTransition('start', 'end', function(object $t) use (&$callOrder): bool {
                $callOrder[] = 'guard4'; // Fourth - this one returns true
                return true;
            }))
            ->build();

        $region->trigger((object)[]);

        $this->assertTrue($region->isInState('end'));
        // Guards are evaluated in the order they were added
        $this->assertEquals(['guard1', 'guard2', 'guard3', 'guard4'], $callOrder);
    }
    
    public function testMultipleTransitionsToSameTarget(): void
    {
        $builder = new RegionBuilder();
        
        $region = $builder
            ->setStates('start', 'finish')
            ->markInitial('start')
            // Multiple ways to reach the same target
            ->addBuildStep(new AddTransition('start', 'finish', fn(object $t): bool => isset($t->condition1)))
            ->addBuildStep(new AddTransition('start', 'finish', fn(object $t): bool => isset($t->condition2)))
            ->addBuildStep(new AddTransition('start', 'finish', fn(object $t): bool => isset($t->condition3)))
            ->build();
        
        // Trigger with condition2 set
        $region->trigger((object)['condition2' => true]);
        
        $this->assertTrue($region->isInState('finish'));
    }
}
