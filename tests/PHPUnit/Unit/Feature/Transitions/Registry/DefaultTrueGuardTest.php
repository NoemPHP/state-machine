<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Transitions\Registry;

use Noem\State\Feature\Transitions\TransitionRegistry;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: TransitionRegistry provides default true guard when none specified
 */
#[Group('transitions')]
#[Group('transition-registry')]
class DefaultTrueGuardTest extends TestCase
{
    public function testProvidesDefaultTrueGuardWhenNoneSpecified(): void
    {
        $registry = new TransitionRegistry();
        $region = (new RegionBuilder())->setStates('a', 'b')->build();

        // Push transition without guard (null)
        $registry->pushTransition($region, 'a', 'b', null);

        $transitions = $registry->getTransitionsForState($region, 'a');

        $this->assertArrayHasKey('b', $transitions);
        $this->assertCount(1, $transitions['b']);

        // The default guard should be callable and return true
        $defaultGuard = $transitions['b'][0];
        $this->assertIsCallable($defaultGuard);

        $trigger = (object)['test' => 'data'];
        $this->assertTrue($defaultGuard($trigger));
    }

    public function testDefaultGuardAlwaysReturnsTrue(): void
    {
        $registry = new TransitionRegistry();
        $region = (new RegionBuilder())->setStates('x', 'y')->build();

        $registry->pushTransition($region, 'x', 'y');

        $transitions = $registry->getTransitionsForState($region, 'x');
        $guard = $transitions['y'][0];

        // Test with various trigger types
        $this->assertTrue($guard((object)[]));
        $this->assertTrue($guard((object)['foo' => 'bar']));
        $this->assertTrue($guard((object)['value' => false]));
    }
}
