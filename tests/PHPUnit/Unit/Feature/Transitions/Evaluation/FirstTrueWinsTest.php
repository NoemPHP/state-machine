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
        $secondGuardCalled = false;

        $region = $builder
            ->setStates('start', 'pathA', 'pathB')
            ->markInitial('start')
            // Guards are evaluated in REVERSE order (LIFO)
            // Add pathA first (will be evaluated SECOND)
            ->addBuildStep(new AddTransition('start', 'pathA', function(object $t) use (&$secondGuardCalled): bool {
                $secondGuardCalled = true;
                return true;
            }))
            // Add pathB second (will be evaluated FIRST and wins)
            ->addBuildStep(new AddTransition('start', 'pathB', fn(object $t): bool => true))
            ->build();

        $region->trigger((object)[]);

        $this->assertTrue($region->isInState('pathB'));
        $this->assertFalse($secondGuardCalled, 'Earlier guard should not be called when later guard returns true');
    }

    public function testEvaluatesMultipleGuardsUntilFirstTrue(): void
    {
        $builder = new RegionBuilder();
        $callOrder = [];

        $region = $builder
            ->setStates('start', 'end')
            ->markInitial('start')
            // Guards are evaluated in REVERSE registration order (LIFO)
            ->addBuildStep(new AddTransition('start', 'end', function(object $t) use (&$callOrder): bool {
                $callOrder[] = 'guard1'; // This will be evaluated FOURTH (in reverse)
                return false;
            }))
            ->addBuildStep(new AddTransition('start', 'end', function(object $t) use (&$callOrder): bool {
                $callOrder[] = 'guard2'; // Third
                return false;
            }))
            ->addBuildStep(new AddTransition('start', 'end', function(object $t) use (&$callOrder): bool {
                $callOrder[] = 'guard3'; // Second
                return false;
            }))
            ->addBuildStep(new AddTransition('start', 'end', function(object $t) use (&$callOrder): bool {
                $callOrder[] = 'guard4'; // First - this one returns true
                return true;
            }))
            ->build();

        $region->trigger((object)[]);

        $this->assertTrue($region->isInState('end'));
        // Guards are evaluated in reverse order, stopping at first true
        $this->assertEquals(['guard4'], $callOrder);
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
