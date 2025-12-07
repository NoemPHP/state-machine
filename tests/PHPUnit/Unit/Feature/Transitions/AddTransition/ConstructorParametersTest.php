<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Transitions\AddTransition;

use Noem\State\Feature\Transitions\AddTransition;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: AddTransition accepts from state, to state, and optional guard
 */
#[Group('transitions')]
#[Group('add-transition-buildstep')]
class ConstructorParametersTest extends TestCase
{
    public function testAcceptsFromToStatesWithoutGuard(): void
    {
        $addTransition = new AddTransition('start', 'end');

        $this->assertInstanceOf(AddTransition::class, $addTransition);
    }

    public function testAcceptsFromToStatesWithGuard(): void
    {
        $guard = fn(object $trigger): bool => true;
        $addTransition = new AddTransition('start', 'end', $guard);

        $this->assertInstanceOf(AddTransition::class, $addTransition);
    }

    public function testAcceptsFromToStatesWithNullGuard(): void
    {
        $addTransition = new AddTransition('start', 'end', null);

        $this->assertInstanceOf(AddTransition::class, $addTransition);
    }
}
