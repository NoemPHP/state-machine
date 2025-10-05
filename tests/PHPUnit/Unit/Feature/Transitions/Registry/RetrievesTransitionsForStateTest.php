<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Transitions\Registry;

use Noem\State\Feature\Transitions\TransitionRegistry;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: TransitionRegistry retrieves all transitions for a given state
 */
#[Group('transitions')]
#[Group('transition-registry')]
class RetrievesTransitionsForStateTest extends TestCase
{
    public function testRetrievesAllTransitionsForState(): void
    {
        $registry = new TransitionRegistry();
        $region = (new RegionBuilder())->setStates('start', 'pathA', 'pathB', 'pathC')->build();
        
        $registry->pushTransition($region, 'start', 'pathA');
        $registry->pushTransition($region, 'start', 'pathB');
        $registry->pushTransition($region, 'start', 'pathC');
        
        $transitions = $registry->getTransitionsForState($region, 'start');
        
        $this->assertIsArray($transitions);
        $this->assertCount(3, $transitions);
        $this->assertArrayHasKey('pathA', $transitions);
        $this->assertArrayHasKey('pathB', $transitions);
        $this->assertArrayHasKey('pathC', $transitions);
    }
    
    public function testReturnsEmptyArrayForStateWithNoTransitions(): void
    {
        $registry = new TransitionRegistry();
        $region = (new RegionBuilder())->setStates('isolated', 'other')->build();
        
        $registry->pushTransition($region, 'other', 'isolated');
        
        $transitions = $registry->getTransitionsForState($region, 'isolated');
        
        $this->assertIsArray($transitions);
        $this->assertEmpty($transitions);
    }
    
    public function testReturnsArrayOfGuardsPerTarget(): void
    {
        $registry = new TransitionRegistry();
        $region = (new RegionBuilder())->setStates('a', 'b')->build();
        
        $guard1 = fn(object $t): bool => $t->id === 1;
        $guard2 = fn(object $t): bool => $t->id === 2;
        
        $registry->pushTransition($region, 'a', 'b', $guard1);
        $registry->pushTransition($region, 'a', 'b', $guard2);
        
        $transitions = $registry->getTransitionsForState($region, 'a');
        
        $this->assertIsArray($transitions['b']);
        $this->assertCount(2, $transitions['b']);
        $this->assertContains($guard1, $transitions['b']);
        $this->assertContains($guard2, $transitions['b']);
    }
}
