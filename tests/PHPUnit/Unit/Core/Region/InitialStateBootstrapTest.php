<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Core\Region;

use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Region fires onEnter callback for initial state on first trigger dispatch
 */
#[Group('region')]
#[Group('event-lifecycle')]
class InitialStateBootstrapTest extends TestCase
{
    public function testOnEnterNotCalledDuringConstruction(): void
    {
        $enterCalled = false;
        
        $region = (new RegionBuilder())
            ->setStates('initial', 'next', 'final')
            ->markInitial('initial')
            ->onEnter('initial', function(object $t) use (&$enterCalled) {
                $enterCalled = true;
            })
            ->build();
        
        // onEnter callback should NOT have been called during construction
        $this->assertFalse($enterCalled, 'onEnter callback must not fire during construction');
        $this->assertEquals('initial', $region->currentState(), 'Region should be in initial state');
    }
    
    public function testOnEnterCalledOnFirstTrigger(): void
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
        
        // Not called yet
        $this->assertFalse($enterCalled);
        
        // First trigger should fire onEnter for initial state
        $region->trigger((object)['test' => 'data']);
        
        $this->assertTrue($enterCalled, 'onEnter callback must fire on first trigger dispatch');
        $this->assertEquals('initial', $enteredState, 'Callback should be called for initial state');
    }
    
    public function testOnEnterCalledOnlyOnceForInitialState(): void
    {
        $enterCount = 0;
        
        $region = (new RegionBuilder())
            ->setStates('initial', 'next')
            ->markInitial('initial')
            ->onEnter('initial', function(object $t) use (&$enterCount) {
                $enterCount++;
            })
            ->build();
        
        // First trigger fires onEnter
        $region->trigger((object)[]);
        $this->assertEquals(1, $enterCount, 'onEnter should fire once on first trigger');
        
        // Second trigger should NOT fire onEnter for initial state again
        $region->trigger((object)[]);
        $this->assertEquals(1, $enterCount, 'onEnter should not fire again for initial state');
    }
    
    public function testOnEnterNotCalledForNonInitialStatesDuringFirstTrigger(): void
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
        
        // First trigger only fires onEnter for initial state
        $region->trigger((object)[]);
        
        $this->assertFalse($nextEnterCalled, 'onEnter for non-initial states should not fire');
        $this->assertFalse($finalEnterCalled, 'onEnter for non-initial states should not fire');
    }
    
    public function testOnEnterReceivesBootstrapTriggerOnFirstDispatch(): void
    {
        $receivedTrigger = null;
        
        $region = (new RegionBuilder())
            ->setStates('start')
            ->markInitial('start')
            ->onEnter('start', function(object $t) use (&$receivedTrigger) {
                $receivedTrigger = $t;
            })
            ->build();
        
        // Trigger first dispatch
        $region->trigger((object)['myData' => 'test']);
        
        // The onEnter callback for initial state receives a bootstrap trigger (empty stdClass)
        // not the actual trigger payload
        $this->assertIsObject($receivedTrigger, 'onEnter callback should receive trigger object');
        $this->assertInstanceOf(\stdClass::class, $receivedTrigger, 'Bootstrap trigger should be stdClass');
    }
    
    public function testMultipleOnEnterCallbacksCalledForInitialState(): void
    {
        $firstCalled = false;
        $secondCalled = false;
        
        $region = (new RegionBuilder())
            ->setStates('initial')
            ->markInitial('initial')
            ->onEnter('initial', function(object $t) use (&$firstCalled) {
                $firstCalled = true;
            })
            ->onEnter('initial', function(object $t) use (&$secondCalled) {
                $secondCalled = true;
            })
            ->build();
        
        // Trigger first dispatch
        $region->trigger((object)[]);
        
        $this->assertTrue($firstCalled, 'First onEnter callback must fire on first dispatch');
        $this->assertTrue($secondCalled, 'Second onEnter callback must fire on first dispatch');
    }
    
    public function testOnEnterCascadingEventsProcessedImmediately(): void
    {
        $sequence = [];
        
        $region = (new RegionBuilder())
            ->setStates('initial')
            ->markInitial('initial')
            ->onEnter('initial', function(object $t) use (&$sequence, &$region) {
                $sequence[] = 'onEnter';
                // Queue another event during onEnter
                $region->trigger((object)['cascaded' => true], true);
            })
            ->onAction('initial', function(object $t) use (&$sequence) {
                $sequence[] = 'onAction';
            })
            ->build();
        
        // First trigger processes: onEnter (initial state) -> cascaded event queued
        $region->trigger((object)['first' => true]);
        
        // The cascaded event should have been processed immediately
        $this->assertEquals(['onEnter', 'onAction'], $sequence);
    }
}
