<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Transitions\Registry;

use Noem\State\Feature\Transitions\TransitionRegistry;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: TransitionRegistry stores transitions per region using SplObjectStorage
 */
#[Group('transitions')]
#[Group('transition-registry')]
class StoresPerRegionTest extends TestCase
{
    public function testStoresTransitionsPerRegionInstance(): void
    {
        $registry = new TransitionRegistry();

        $region1 = (new RegionBuilder())->setStates('a', 'b')->build();
        $region2 = (new RegionBuilder())->setStates('x', 'y')->build();

        $guard1 = fn(object $t): bool => true;
        $guard2 = fn(object $t): bool => false;

        $registry->pushTransition($region1, 'a', 'b', $guard1);
        $registry->pushTransition($region2, 'x', 'y', $guard2);

        $transitions1 = $registry->getTransitionsForState($region1, 'a');
        $transitions2 = $registry->getTransitionsForState($region2, 'x');

        $this->assertArrayHasKey('b', $transitions1);
        $this->assertArrayHasKey('y', $transitions2);
        $this->assertCount(1, $transitions1['b']);
        $this->assertCount(1, $transitions2['y']);
    }

    public function testTransitionsIsolatedBetweenRegions(): void
    {
        $registry = new TransitionRegistry();

        $region1 = (new RegionBuilder())->setStates('state')->build();
        $region2 = (new RegionBuilder())->setStates('state')->build();

        $registry->pushTransition($region1, 'state', 'target1');

        $transitions1 = $registry->getTransitionsForState($region1, 'state');
        $transitions2 = $registry->getTransitionsForState($region2, 'state');

        $this->assertNotEmpty($transitions1);
        $this->assertEmpty($transitions2);
    }
}
