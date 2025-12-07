<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Transitions\Evaluation;

use Noem\State\Feature\Transitions\AddTransition;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: No transition occurs when state changed imperatively
 */
#[Group('transitions')]
#[Group('transition-evaluation')]
class SkipsOnImperativeChangeTest extends TestCase
{
    public function testSkipsAutomaticTransitionWhenActionChangesState(): void
    {
        $builder = new RegionBuilder();
        $guardCalled = false;

        // Add middleware to DispatchAction chain that imperatively changes state
        $builder->chainMail->use(function (\Noem\State\Chains\DispatchAction $dispatchAction) {
            $dispatchAction->link(function (\Noem\State\Chains\Params\Action $action, callable $next): string {
                // Call the next middleware first
                $next($action);
                // Then imperatively return a different state
                return 'three';
            });
        });

        $region = $builder
            ->setStates('one', 'two', 'three')
            ->markInitial('one')
            // Add automatic transition from one to two
            ->addBuildStep(new AddTransition('one', 'two', function (object $t) use (&$guardCalled): bool {
                $guardCalled = true;
                return true;
            }))
            ->build();

        $this->assertTrue($region->isInState('one'));

        $region->trigger((object)['test' => 'data']);

        // Should be in three (imperative), not two (automatic)
        $this->assertTrue($region->isInState('three'));
        // Guard should not have been called since state changed imperatively
        $this->assertFalse($guardCalled);
    }

    public function testAllowsAutomaticTransitionWhenActionReturnsCurrentState(): void
    {
        $builder = new RegionBuilder();
        $guardCalled = false;

        $region = $builder
            ->setStates('a', 'b')
            ->markInitial('a')
            ->addBuildStep(new AddTransition('a', 'b', function (object $t) use (&$guardCalled): bool {
                $guardCalled = true;
                return true;
            }))
            ->onAction('a', function (object $t) {
                // Don't return anything (no imperative change)
                // This allows automatic transitions to proceed
            })
            ->build();

        $region->trigger((object)[]);

        // Should transition automatically to b
        $this->assertTrue($region->isInState('b'));
        // Guard should have been called
        $this->assertTrue($guardCalled);
    }
}
