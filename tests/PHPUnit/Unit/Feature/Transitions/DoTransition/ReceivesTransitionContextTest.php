<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Transitions\DoTransition;

use Noem\State\Chains\Params\Transition;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * Acceptance Criterion: DoTransition chain receives Transition context parameter
 */
#[Group('transitions')]
#[Group('transition-execution')]
class ReceivesTransitionContextTest extends TestCase
{
    public function testTransitionContextContainsRegion(): void
    {
        $region = (new RegionBuilder())->setStates('a', 'b')->build();
        $payload = new stdClass();
        
        $context = new Transition($region, $payload, 'a');
        
        $this->assertSame($region, $context->region);
    }
    
    public function testTransitionContextContainsPreviousState(): void
    {
        $region = (new RegionBuilder())->setStates('start', 'end')->build();
        $payload = new stdClass();
        
        $context = new Transition($region, $payload, 'start');
        
        $this->assertEquals('start', $context->previousState);
    }
    
    public function testTransitionContextContainsPayload(): void
    {
        $region = (new RegionBuilder())->setStates('a', 'b')->build();
        $payload = (object)['data' => 'test'];
        
        $context = new Transition($region, $payload, 'a');
        
        $this->assertSame($payload, $context->payload);
    }
    
    public function testTransitionContextCurrentStateReflectsRegion(): void
    {
        $region = (new RegionBuilder())->setStates('idle', 'processing')->build();
        $payload = new stdClass();
        
        $context = new Transition($region, $payload, 'idle');
        
        // currentState is a computed property from region
        $this->assertEquals($region->currentState(), $context->currentState);
    }
}
