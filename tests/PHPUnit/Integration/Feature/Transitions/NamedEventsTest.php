<?php

declare(strict_types=1);

namespace Noem\State\Tests\Integration\Feature\Transitions;

use Noem\State\Feature\Transitions\AddTransition;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * Acceptance Criterion: Transitions work with named events
 */
#[Group('transitions')]
#[Group('integration')]
class NamedEventsTest extends TestCase
{
    public function testTransitionWithObjectTrigger(): void
    {
        $region = (new RegionBuilder())
            ->setStates('idle', 'active')
            ->markInitial('idle')
            ->addBuildStep(new AddTransition('idle', 'active', fn(object $t): bool => isset($t->activate)))
            ->build();
        
        // Trigger with object payload
        $region->trigger((object)['activate' => true]);
        
        $this->assertEquals('active', $region->currentState());
    }
    
    public function testTransitionWithTypedTrigger(): void
    {
        // Create a custom event class
        $eventClass = new class {
            public string $type = 'start';
            public array $data = [];
        };
        
        $region = (new RegionBuilder())
            ->setStates('waiting', 'running')
            ->markInitial('waiting')
            ->addBuildStep(new AddTransition('waiting', 'running', function(object $t): bool {
                return property_exists($t, 'type') && $t->type === 'start';
            }))
            ->build();
        
        $region->trigger($eventClass);
        
        $this->assertEquals('running', $region->currentState());
    }
    
    public function testTransitionWithMultipleEventTypes(): void
    {
        $region = (new RegionBuilder())
            ->setStates('idle', 'processing', 'complete')
            ->markInitial('idle')
            ->addBuildStep(new AddTransition('idle', 'processing', fn(object $t): bool => ($t->action ?? '') === 'start'))
            ->addBuildStep(new AddTransition('processing', 'complete', fn(object $t): bool => ($t->action ?? '') === 'finish'))
            ->build();
        
        // Start processing
        $region->trigger((object)['action' => 'start']);
        $this->assertEquals('processing', $region->currentState());
        
        // Complete processing
        $region->trigger((object)['action' => 'finish']);
        $this->assertEquals('complete', $region->currentState());
    }
}
