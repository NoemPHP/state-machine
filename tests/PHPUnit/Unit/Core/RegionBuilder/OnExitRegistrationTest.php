<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Core\RegionBuilder;

use Noem\State\Feature\Transitions\AddTransition;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: A builder can register exit handlers using onExit
 */
#[Group('region-builder')]
#[Group('event-handler-registration')]
class OnExitRegistrationTest extends TestCase
{
    public function testOnExitAcceptsStateAndClosure(): void
    {
        $builder = new RegionBuilder();
        $builder->setStates('idle', 'processing');
        
        $handler = function (object $trigger): void {
        };
        
        $result = $builder->onExit('idle', $handler);
        
        $this->assertSame($builder, $result, 'onExit should return builder for chaining');
    }
    
    public function testOnExitHandlerIsInvokedWhenLeavingState(): void
    {
        $builder = new RegionBuilder();
        $builder->setStates('idle', 'processing')
                ->markInitial('idle');
        
        $exitCalled = false;
        $receivedTrigger = null;
        
        $builder->onExit('idle', function (object $trigger) use (&$exitCalled, &$receivedTrigger): void {
            $exitCalled = true;
            $receivedTrigger = $trigger;
        });
        
        $builder->addBuildStep(new AddTransition('idle', 'processing', fn(object $t): bool => true));
        
        $region = $builder->build();
        
        $this->assertFalse($exitCalled, 'Handler should not be called before transition');
        
        $trigger = (object)['data' => 'test'];
        $region->trigger($trigger);
        
        $this->assertTrue($exitCalled, 'onExit handler should be called when leaving idle state');
        $this->assertSame($trigger, $receivedTrigger, 'Handler should receive trigger object');
    }
}
