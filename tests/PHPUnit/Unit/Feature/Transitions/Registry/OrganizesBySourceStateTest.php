<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Transitions\Registry;

use Noem\State\Feature\Transitions\TransitionRegistry;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: TransitionRegistry organizes transitions by source state
 */
#[Group('transitions')]
#[Group('transition-registry')]
class OrganizesBySourceStateTest extends TestCase
{
    public function testOrganizesTransitionsBySourceState(): void
    {
        $registry = new TransitionRegistry();
        $region = (new RegionBuilder())->setStates('a', 'b', 'c', 'd')->build();
        
        $registry->pushTransition($region, 'a', 'b');
        $registry->pushTransition($region, 'a', 'c');
        $registry->pushTransition($region, 'b', 'd');
        
        $transitionsFromA = $registry->getTransitionsForState($region, 'a');
        $transitionsFromB = $registry->getTransitionsForState($region, 'b');
        
        $this->assertArrayHasKey('b', $transitionsFromA);
        $this->assertArrayHasKey('c', $transitionsFromA);
        $this->assertCount(2, $transitionsFromA);
        
        $this->assertArrayHasKey('d', $transitionsFromB);
        $this->assertCount(1, $transitionsFromB);
    }
    
    public function testRetrievesOnlyTransitionsForSpecificState(): void
    {
        $registry = new TransitionRegistry();
        $region = (new RegionBuilder())->setStates('one', 'two', 'three')->build();
        
        $registry->pushTransition($region, 'one', 'two');
        $registry->pushTransition($region, 'two', 'three');
        
        $transitionsFromOne = $registry->getTransitionsForState($region, 'one');
        
        $this->assertArrayHasKey('two', $transitionsFromOne);
        $this->assertArrayNotHasKey('three', $transitionsFromOne);
    }
}
