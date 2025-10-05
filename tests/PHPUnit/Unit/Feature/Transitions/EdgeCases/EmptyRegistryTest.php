<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Transitions\EdgeCases;

use Noem\State\Feature\Transitions\TransitionRegistry;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Empty transition registry returns empty array
 */
#[Group('transitions')]
#[Group('edge-cases')]
class EmptyRegistryTest extends TestCase
{
    public function testEmptyRegistryReturnsEmptyArray(): void
    {
        $registry = new TransitionRegistry();
        $region = (new RegionBuilder())->setStates('a', 'b')->build();
        
        $transitions = $registry->getTransitionsForState($region, 'a');
        
        $this->assertIsArray($transitions);
        $this->assertEmpty($transitions);
    }
    
    public function testRegistryWithNoTransitionsForStateReturnsEmpty(): void
    {
        $registry = new TransitionRegistry();
        $region = (new RegionBuilder())->setStates('a', 'b', 'c')->build();
        
        // Register transition from 'a' to 'b'
        $registry->pushTransition($region, 'a', 'b', fn(object $t): bool => true);
        
        // Query for state 'c' which has no transitions
        $transitions = $registry->getTransitionsForState($region, 'c');
        
        $this->assertIsArray($transitions);
        $this->assertEmpty($transitions);
    }
}
