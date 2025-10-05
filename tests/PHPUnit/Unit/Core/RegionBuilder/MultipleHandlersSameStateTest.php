<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Core\RegionBuilder;

use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Multiple handlers can be registered for the same state and event type
 */
#[Group('region-builder')]
#[Group('event-handler-registration')]
class MultipleHandlersSameStateTest extends TestCase
{
    public function testMultipleActionHandlersForSameState(): void
    {
        $builder = new RegionBuilder();
        $builder->setStates('idle');
        
        $firstCalled = false;
        $secondCalled = false;
        $thirdCalled = false;
        
        $builder->onAction('idle', function (object $trigger) use (&$firstCalled): void {
            $firstCalled = true;
        });

        $builder->onAction('idle', function (object $trigger) use (&$secondCalled): void {
            $secondCalled = true;
        });

        $builder->onAction('idle', function (object $trigger) use (&$thirdCalled): void {
            $thirdCalled = true;
        });
        
        $region = $builder->build();
        $region->trigger((object)[]);
        
        $this->assertTrue($firstCalled, 'First action handler should be called');
        $this->assertTrue($secondCalled, 'Second action handler should be called');
        $this->assertTrue($thirdCalled, 'Third action handler should be called');
    }
    
    public function testMultipleEnterHandlersForSameState(): void
    {
        $builder = new RegionBuilder();
        $builder->setStates('idle', 'processing');
        
        $callCount = 0;
        
        $builder->onEnter('processing', function (object $trigger) use (&$callCount): void {
            $callCount++;
        });

        $builder->onEnter('processing', function (object $trigger) use (&$callCount): void {
            $callCount++;
        });
        
        $builder->addBuildStep(new \Noem\State\Feature\Transitions\AddTransition('idle', 'processing', fn(object $t): bool => true));
        
        $region = $builder->build();
        
        $this->assertEquals(0, $callCount, 'Handlers should not be called before transition');
        
        $region->trigger((object)[]);
        
        $this->assertEquals(2, $callCount, 'Both enter handlers should be called when entering processing state');
    }
    
    public function testMultipleExitHandlersForSameState(): void
    {
        $builder = new RegionBuilder();
        $builder->setStates('idle', 'processing');
        
        $callCount = 0;
        
        $builder->onExit('idle', function (object $trigger) use (&$callCount): void {
            $callCount++;
        });
        
        $builder->onExit('idle', function (object $trigger) use (&$callCount): void {
            $callCount++;
        });
        
        $builder->addBuildStep(new \Noem\State\Feature\Transitions\AddTransition('idle', 'processing', fn(object $t): bool => true));
        
        $region = $builder->build();
        $region->trigger((object)[]);
        
        $this->assertEquals(2, $callCount, 'Both exit handlers should be called during transition');
    }
}
