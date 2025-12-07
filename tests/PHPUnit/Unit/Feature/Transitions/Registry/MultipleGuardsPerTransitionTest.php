<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Transitions\Registry;

use Noem\State\Feature\Transitions\TransitionRegistry;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: TransitionRegistry stores multiple guards per transition
 */
#[Group('transitions')]
#[Group('transition-registry')]
class MultipleGuardsPerTransitionTest extends TestCase
{
    public function testStoresMultipleGuardsForSameTransition(): void
    {
        $registry = new TransitionRegistry();
        $region = (new RegionBuilder())->setStates('a', 'b')->build();

        $guard1 = fn(object $t): bool => isset($t->condition1);
        $guard2 = fn(object $t): bool => isset($t->condition2);
        $guard3 = fn(object $t): bool => isset($t->condition3);

        $registry->pushTransition($region, 'a', 'b', $guard1);
        $registry->pushTransition($region, 'a', 'b', $guard2);
        $registry->pushTransition($region, 'a', 'b', $guard3);

        $transitions = $registry->getTransitionsForState($region, 'a');

        $this->assertArrayHasKey('b', $transitions);
        $this->assertCount(3, $transitions['b']);
        $this->assertSame($guard1, $transitions['b'][0]);
        $this->assertSame($guard2, $transitions['b'][1]);
        $this->assertSame($guard3, $transitions['b'][2]);
    }

    public function testGuardsStoredInOrder(): void
    {
        $registry = new TransitionRegistry();
        $region = (new RegionBuilder())->setStates('start', 'end')->build();

        $firstGuard = fn(object $t): bool => true;
        $secondGuard = fn(object $t): bool => false;

        $registry->pushTransition($region, 'start', 'end', $firstGuard);
        $registry->pushTransition($region, 'start', 'end', $secondGuard);

        $transitions = $registry->getTransitionsForState($region, 'start');
        $guards = $transitions['end'];

        $this->assertSame($firstGuard, $guards[0]);
        $this->assertSame($secondGuard, $guards[1]);
    }
}
