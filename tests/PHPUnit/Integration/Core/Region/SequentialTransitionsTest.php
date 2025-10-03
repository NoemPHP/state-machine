<?php

declare(strict_types=1);

namespace Noem\State\Tests\Integration\Core\Region;

use Noem\State\Feature\Transitions\AddTransition;
use Noem\State\RegionBuilder;
use Noem\State\Test\Integration\RegionBuilderTestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * Acceptance Criterion: Multiple transitions in sequence execute in order
 */
#[Group('region')]
#[Group('integration')]
class SequentialTransitionsTest extends RegionBuilderTestCase
{
    #[Test]
    public function testMultipleSequentialTransitions(): void
    {
        $r = new RegionBuilder();

        $r = $r
            ->setStates('one', 'two', 'three', 'four')
            ->markInitial('one')
            ->addBuildStep(new AddTransition('one', 'two', fn(object $t): bool => true))
            ->addBuildStep(new AddTransition('two', 'three', fn(object $t): bool => true))
            ->addBuildStep(new AddTransition('three', 'four', fn(object $t): bool => true))
            ->build();

        $this->assertTrue($r->isInState('one'));

        $r->trigger((object)['step' => 1]);
        $this->assertTrue($r->isInState('two'));

        $r->trigger((object)['step' => 2]);
        $this->assertTrue($r->isInState('three'));

        $r->trigger((object)['step' => 3]);
        $this->assertTrue($r->isInState('four'));
    }

    #[Test]
    public function testTransitionSequenceWithCallbacks(): void
    {
        $sequence = [];
        $r = new RegionBuilder();

        $r = $r
            ->setStates('one', 'two', 'three')
            ->markInitial('one')
            ->onExit('one', function(object $t) use (&$sequence) { $sequence[] = 'exit-one'; })
            ->onEnter('two', function(object $t) use (&$sequence) { $sequence[] = 'enter-two'; })
            ->onExit('two', function(object $t) use (&$sequence) { $sequence[] = 'exit-two'; })
            ->onEnter('three', function(object $t) use (&$sequence) { $sequence[] = 'enter-three'; })
            ->addBuildStep(new AddTransition('one', 'two', fn(object $t): bool => true))
            ->addBuildStep(new AddTransition('two', 'three', fn(object $t): bool => true))
            ->build();

        $r->trigger((object)['step' => 1]);
        $r->trigger((object)['step' => 2]);

        $this->assertTrue($r->isInState('three'));
        $this->assertEquals(
            ['exit-one', 'enter-two', 'exit-two', 'enter-three'],
            $sequence
        );
    }

    #[Test]
    public function testConditionalTransitions(): void
    {
        $r = new RegionBuilder();

        $r = $r
            ->setStates('start', 'pathA', 'pathB', 'end')
            ->markInitial('start')
            ->addBuildStep(new AddTransition('start', 'pathA', fn(object $t): bool => $t->choice === 'A'))
            ->addBuildStep(new AddTransition('start', 'pathB', fn(object $t): bool => $t->choice === 'B'))
            ->addBuildStep(new AddTransition('pathA', 'end', fn(object $t): bool => true))
            ->addBuildStep(new AddTransition('pathB', 'end', fn(object $t): bool => true))
            ->build();

        // Take path A
        $r->trigger((object)['choice' => 'A']);
        $this->assertTrue($r->isInState('pathA'));

        $r->trigger((object)['step' => 'next']);
        $this->assertTrue($r->isInState('end'));
    }

    #[Test]
    public function testTransitionToFinalState(): void
    {
        $r = new RegionBuilder();

        $r = $r
            ->setStates('one', 'two', 'final')
            ->markInitial('one')
            ->markFinal('final')
            ->addBuildStep(new AddTransition('one', 'two', fn(object $t): bool => true))
            ->addBuildStep(new AddTransition('two', 'final', fn(object $t): bool => true))
            ->build();

        $this->assertFalse($r->isFinal());

        $r->trigger((object)['step' => 1]);
        $this->assertFalse($r->isFinal());
        $this->assertTrue($r->isInState('two'));

        $r->trigger((object)['step' => 2]);
        $this->assertTrue($r->isFinal());
        $this->assertTrue($r->isInState('final'));
    }

    #[Test]
    public function testNoTransitionWhenGuardFails(): void
    {
        $r = new RegionBuilder();

        $r = $r
            ->setStates('one', 'two', 'three')
            ->markInitial('one')
            ->addBuildStep(new AddTransition('one', 'two', fn(object $t): bool => $t->allowed ?? false))
            ->addBuildStep(new AddTransition('two', 'three', fn(object $t): bool => true))
            ->build();

        // Guard fails
        $r->trigger((object)['allowed' => false]);
        $this->assertTrue($r->isInState('one'), 'Should stay in one when guard fails');

        // Guard succeeds
        $r->trigger((object)['allowed' => true]);
        $this->assertTrue($r->isInState('two'), 'Should transition to two when guard succeeds');
    }
}
