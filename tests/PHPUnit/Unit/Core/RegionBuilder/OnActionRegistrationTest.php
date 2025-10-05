<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Core\RegionBuilder;

use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: A builder can register action handlers using onAction
 */
#[Group('region-builder')]
#[Group('event-handler-registration')]
class OnActionRegistrationTest extends TestCase
{
    public function testOnActionAcceptsStateAndClosure(): void
    {
        $builder = new RegionBuilder();
        $builder->setStates('idle', 'processing');
        
        $handlerCalled = false;
        $handler = function (object $trigger) use (&$handlerCalled): void {
            $handlerCalled = true;
        };
        
        $result = $builder->onAction('processing', $handler);
        
        $this->assertSame($builder, $result, 'onAction should return builder for chaining');
        
        $region = $builder->build();
        $region->trigger((object)['action' => 'test']);
        
        // Handler should not be called yet as we haven't transitioned
        $this->assertFalse($handlerCalled);
    }
    
    public function testOnActionHandlerIsInvokedInTargetState(): void
    {
        $builder = new RegionBuilder();
        $builder->setStates('idle', 'processing');
        
        $handlerCalled = false;
        $receivedTrigger = null;
        
        $builder->onAction('idle', function (object $trigger) use (&$handlerCalled, &$receivedTrigger): void {
            $handlerCalled = true;
            $receivedTrigger = $trigger;
        });
        
        $region = $builder->build();
        
        $this->assertTrue($region->isInState('idle'));
        
        $trigger = (object)['data' => 'test'];
        $region->trigger($trigger);
        
        $this->assertTrue($handlerCalled, 'Action handler should be called in idle state');
        $this->assertSame($trigger, $receivedTrigger, 'Handler should receive trigger object');
    }
}
