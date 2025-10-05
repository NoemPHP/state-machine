<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Transitions;

use Noem\State\Feature\Transitions\AddTransition;
use Noem\State\Feature\Transitions\TransitionsFeature;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * Acceptance Criterion: TransitionsFeature hooks into DispatchAction chain
 */
#[Group('transitions')]
#[Group('feature-registration')]
class HooksIntoDispatchActionTest extends TestCase
{
    public function testTransitionsAreAutomaticallyCheckedAfterActionDispatch(): void
    {
        // Build a region with TransitionsFeature enabled
        $actionExecuted = false;
        
        $region = (new RegionBuilder())
            ->enableFeatures(new TransitionsFeature())
            ->setStates('idle', 'working', 'done')
            ->addBuildStep(new AddTransition('idle', 'working'))
            ->addBuildStep(new AddTransition('working', 'done'))
            ->onAction('idle', function (object $trigger) use (&$actionExecuted) {
                $actionExecuted = true;
            })
            ->build();
        
        $this->assertTrue($region->isInState('idle'));
        
        // Trigger an action - the feature should hook into DispatchAction
        // and automatically check for transitions after the action executes
        $region->trigger(new stdClass());
        
        // Verify the action was executed
        $this->assertTrue($actionExecuted, 'Action handler should have been called');
        
        // Verify automatic transition occurred due to DispatchAction hook
        $this->assertTrue($region->isInState('working'), 'Should automatically transition from idle to working');
    }
    
    public function testAutomaticTransitionEvaluationWithGuards(): void
    {
        $trigger = new stdClass();
        $trigger->shouldTransition = false;
        
        $region = (new RegionBuilder())
            ->enableFeatures(new TransitionsFeature())
            ->setStates('start', 'end')
            ->addBuildStep(new AddTransition(
                'start',
                'end',
                fn(object $t): bool => $t->shouldTransition
            ))
            ->build();
        
        // First trigger with guard returning false
        $region->trigger($trigger);
        $this->assertTrue($region->isInState('start'), 'Should not transition when guard is false');
        
        // Second trigger with guard returning true
        $trigger->shouldTransition = true;
        $region->trigger($trigger);
        $this->assertTrue($region->isInState('end'), 'Should transition when guard becomes true');
    }
    
    public function testDispatchActionHookEnablesTransitionsWithoutExplicitHandlers(): void
    {
        // Even without action handlers, the DispatchAction hook should enable transitions
        $region = (new RegionBuilder())
            ->enableFeatures(new TransitionsFeature())
            ->setStates('a', 'b', 'c')
            ->addBuildStep(new AddTransition('a', 'b'))
            ->addBuildStep(new AddTransition('b', 'c'))
            ->build();
        
        $this->assertTrue($region->isInState('a'));
        
        // Trigger causes DispatchAction to run, which hooks the transition check
        $region->trigger(new stdClass());
        $this->assertTrue($region->isInState('b'), 'First automatic transition');
        
        $region->trigger(new stdClass());
        $this->assertTrue($region->isInState('c'), 'Second automatic transition');
    }
}
