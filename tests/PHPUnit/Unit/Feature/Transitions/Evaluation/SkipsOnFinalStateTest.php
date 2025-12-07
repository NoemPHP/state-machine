<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Transitions\Evaluation;

use Noem\State\Feature\Transitions\AddTransition;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: No transition occurs when region is in final state
 */
#[Group('transitions')]
#[Group('transition-evaluation')]
class SkipsOnFinalStateTest extends TestCase
{
    public function testNoTransitionFromFinalState(): void
    {
        $builder = new RegionBuilder();
        $guardCalled = false;

        $region = $builder
            ->setStates('one', 'two', 'final')
            ->markInitial('one')
            ->markFinal('final')
            ->addBuildStep(new AddTransition('one', 'two'))
            ->addBuildStep(new AddTransition('two', 'final'))
            // This transition should never be evaluated
            ->addBuildStep(new AddTransition('final', 'one', function (object $t) use (&$guardCalled): bool {
                $guardCalled = true;
                return true;
            }))
            ->build();

        // Transition to final state
        $region->trigger((object)[]);
        $region->trigger((object)[]);

        $this->assertTrue($region->isFinal());
        $this->assertTrue($region->isInState('final'));

        // Try to trigger again - should stay in final
        $region->trigger((object)[]);

        $this->assertTrue($region->isInState('final'));
        $this->assertFalse($guardCalled, 'Guard should not be called from final state');
    }

    public function testMultipleTriggersOnFinalStateDoNothing(): void
    {
        $builder = new RegionBuilder();
        $enterFinalCount = 0;

        $region = $builder
            ->setStates('start', 'end')
            ->markInitial('start')
            ->markFinal('end')
            ->addBuildStep(new AddTransition('start', 'end'))
            ->onEnter('end', function (object $t) use (&$enterFinalCount) {
                $enterFinalCount++;
            })
            ->build();

        $region->trigger((object)[]);

        $this->assertTrue($region->isFinal());
        $this->assertEquals(1, $enterFinalCount);

        // Multiple triggers should do nothing
        $region->trigger((object)[]);
        $region->trigger((object)[]);
        $region->trigger((object)[]);

        $this->assertTrue($region->isFinal());
        $this->assertEquals(1, $enterFinalCount, 'Should only enter final state once');
    }
}
