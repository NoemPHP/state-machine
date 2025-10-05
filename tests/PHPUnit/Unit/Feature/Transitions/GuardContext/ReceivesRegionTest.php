<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Transitions\GuardContext;

use Noem\State\Feature\Transitions\Chains\Params\Guard;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * Acceptance Criterion: Guard chain receives context with region instance
 */
#[Group('transitions')]
#[Group('guard-context')]
class ReceivesRegionTest extends TestCase
{
    public function testGuardContextContainsRegion(): void
    {
        $region = (new RegionBuilder())->setStates('a', 'b')->build();
        $trigger = new stdClass();
        $handler = fn(object $t): bool => true;
        
        $context = new Guard($region, 'a', 'b', $handler, $trigger);
        
        $this->assertSame($region, $context->region);
    }
    
    public function testGuardContextRegionIsAccessible(): void
    {
        $region = (new RegionBuilder())->setStates('start', 'end')->build();
        $trigger = new stdClass();
        $handler = fn(object $t): bool => true;
        
        $context = new Guard($region, 'start', 'end', $handler, $trigger);
        
        // Verify we can access region properties
        $this->assertEquals('start', $context->region->currentState());
    }
}
