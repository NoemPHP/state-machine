<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Core\Region;

use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * Acceptance Criterion: Region fires onEnter callback when entering initial state during construction
 */
#[Group('region')]
#[Group('event-lifecycle')]
class InitialStateBootstrapTest extends TestCase
{
    public function testOnEnterCalledForInitialState(): void
    {
        $enterCalled = false;
        $enteredState = null;
        
        $region = (new RegionBuilder())
            ->setStates('initial', 'next', 'final')
            ->markInitial('initial')
            ->onEnter('initial', function(object $t) use (&$enterCalled, &$enteredState) {
                $enterCalled = true;
                $enteredState = 'initial';
            })
            ->build();
        
        // onEnter callback should have been called during construction
        $this->assertTrue($enterCalled, 'onEnter callback must fire when region enters initial state');
        $this->assertEquals('initial', $enteredState, 'Callback should receive initial state');
    }
    
    public function testOnEnterNotCalledForNonInitialStates(): void
    {
        $nextEnterCalled = false;
        $finalEnterCalled = false;
        
        $region = (new RegionBuilder())
            ->setStates('initial', 'next', 'final')
            ->markInitial('initial')
            ->onEnter('next', function(object $t) use (&$nextEnterCalled) {
                $nextEnterCalled = true;
            })
            ->onEnter('final', function(object $t) use (&$finalEnterCalled) {
                $finalEnterCalled = true;
            })
            ->build();
        
        // Only initial state onEnter should fire during construction
        $this->assertFalse($nextEnterCalled, 'onEnter for non-initial states should not fire during construction');
        $this->assertFalse($finalEnterCalled, 'onEnter for non-initial states should not fire during construction');
    }
    
    public function testOnEnterReceivesProperContext(): void
    {
        $receivedRegion = null;
        $receivedTrigger = null;
        
        $region = (new RegionBuilder())
            ->setStates('start')
            ->onEnter('start', function(object $t) use (&$receivedTrigger) {
                $receivedTrigger = $t;
            })
            ->build();
        
        // The trigger should be an empty stdClass or similar for bootstrap
        $this->assertIsObject($receivedTrigger, 'onEnter callback should receive trigger object during bootstrap');
    }
    
    public function testMultipleOnEnterCallbacksCalledForInitialState(): void
    {
        $firstCalled = false;
        $secondCalled = false;
        
        $region = (new RegionBuilder())
            ->setStates('initial')
            ->onEnter('initial', function(object $t) use (&$firstCalled) {
                $firstCalled = true;
            })
            ->onEnter('initial', function(object $t) use (&$secondCalled) {
                $secondCalled = true;
            })
            ->build();
        
        $this->assertTrue($firstCalled, 'First onEnter callback must fire during bootstrap');
        $this->assertTrue($secondCalled, 'Second onEnter callback must fire during bootstrap');
    }
    
    public function testOnEnterCalledBeforeRegionIsUsable(): void
    {
        $callbackExecuted = false;

        $region = (new RegionBuilder())
            ->setStates('initial', 'next')
            ->markInitial('initial')
            ->onEnter('initial', function(object $t) use (&$callbackExecuted) {
                // Callback should execute during build
                $callbackExecuted = true;
            })
            ->build();

        $this->assertTrue($callbackExecuted, 'onEnter callback should execute during build');
        $this->assertEquals('initial', $region->currentState(), 'Region should be in initial state after construction');
    }
}
