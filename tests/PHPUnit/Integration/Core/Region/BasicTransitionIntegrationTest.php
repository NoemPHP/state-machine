<?php

declare(strict_types=1);

namespace Noem\State\Tests\Integration\Core\Region;

use Noem\State\Feature\Transitions\AddTransition;
use Noem\State\RegionBuilder;
use Noem\State\Test\Integration\RegionBuilderTestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * Acceptance Criterion: Basic state transition with guards and callbacks executes correctly
 */
#[Group('region')]
#[Group('integration')]
class BasicTransitionIntegrationTest extends RegionBuilderTestCase
{
    #[Test]
    public function testBasicTransitionWithGuard(): void
    {
        $guardCalled = false;
        $r = new RegionBuilder();

        $r = $r
            ->setStates('one', 'two')
            ->markInitial('one')
            ->addBuildStep(
                new AddTransition('one', 'two', function (object $t) use (&$guardCalled): bool {
                    $guardCalled = true;
                    return true;
                })
            )
            ->build();

        $r->trigger((object)['foo' => 1]);

        $this->assertTrue($r->isInState('two'));
        $this->assertTrue($guardCalled, 'Guard should have been called');
    }

    #[Test]
    public function testTransitionWithOnEnterCallback(): void
    {
        $enterCalled = false;
        $r = new RegionBuilder();

        $r = $r
            ->setStates('one', 'two')
            ->onEnter('two', function (object $t) use (&$enterCalled) {
                $enterCalled = true;
            })
            ->markInitial('one')
            ->addBuildStep(
                new AddTransition('one', 'two', fn(object $t): bool => true)
            )
            ->build();

        $r->trigger((object)['foo' => 1]);

        $this->assertTrue($r->isInState('two'));
        $this->assertTrue($enterCalled, 'onEnter callback should have been called');
    }

    #[Test]
    public function testTransitionWithOnExitCallback(): void
    {
        $exitCalled = false;
        $r = new RegionBuilder();

        $r = $r
            ->setStates('one', 'two')
            ->onExit('one', function (object $t) use (&$exitCalled) {
                $exitCalled = true;
            })
            ->markInitial('one')
            ->addBuildStep(
                new AddTransition('one', 'two', fn(object $t): bool => true)
            )
            ->build();

        $r->trigger((object)['foo' => 1]);

        $this->assertTrue($r->isInState('two'));
        $this->assertTrue($exitCalled, 'onExit callback should have been called');
    }

    #[Test]
    public function testFullTransitionLifecycle(): void
    {
        $sequence = [];
        $r = new RegionBuilder();

        $r = $r
            ->setStates('one', 'two')
            ->onExit('one', function (object $t) use (&$sequence) {
                $sequence[] = 'exit-one';
            })
            ->onEnter('two', function (object $t) use (&$sequence) {
                $sequence[] = 'enter-two';
            })
            ->markInitial('one')
            ->addBuildStep(
                new AddTransition('one', 'two', function (object $t) use (&$sequence): bool {
                    $sequence[] = 'guard';
                    return true;
                })
            )
            ->build();

        $r->trigger((object)['foo' => 1]);

        $this->assertTrue($r->isInState('two'));
        $this->assertEquals(['guard', 'exit-one', 'enter-two'], $sequence);
    }
}
