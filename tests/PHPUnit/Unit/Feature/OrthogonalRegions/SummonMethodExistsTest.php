<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\OrthogonalRegions;

use Noem\State\Feature\OrthogonalRegions\OrthogonalRegions;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: OrthogonalRegions adds $this->summon() method to callbacks
 */
#[Group('orthogonal-regions')]
#[Group('summon')]
class SummonMethodExistsTest extends TestCase
{
    public function testSummonMethodExistsInCallbacks(): void
    {
        $summonExists = false;

        $region = (new OrthogonalRegions(new RegionBuilder()))
            ->setStates('test')
            ->markInitial('test')
            ->onEnter('test', function (object $t) use (&$summonExists) {
                $summonExists = method_exists($this, 'summon');
            })
            ->build();

        $region->init();

        $this->assertTrue($summonExists, 'summon() method should exist in callbacks');
    }

    public function testSummonIsCallableFromOnEnter(): void
    {
        $summonCalled = false;

        $region = (new OrthogonalRegions(new RegionBuilder()))
            ->setStates('test')
            ->markInitial('test')
            ->onEnter('test', function (object $t) use (&$summonCalled) {
                $summonCalled = is_callable([$this, 'summon']);
            })
            ->build();

        $region->init();

        $this->assertTrue($summonCalled);
    }

    public function testSummonIsCallableFromOnExit(): void
    {
        $summonCalled = false;

        $region = (new OrthogonalRegions(new RegionBuilder()))
            ->setStates('start', 'end')
            ->markInitial('start')
            ->onExit('start', function (object $t) use (&$summonCalled) {
                $summonCalled = is_callable([$this, 'summon']);
            })
            ->build();

        $region->init();
        $region->trigger((object)[], 'end');

        $this->assertTrue($summonCalled);
    }

    public function testSummonIsCallableFromOnAction(): void
    {
        $summonCalled = false;

        $region = (new OrthogonalRegions(new RegionBuilder()))
            ->setStates('test')
            ->markInitial('test')
            ->onAction('test', function (object $t) use (&$summonCalled) {
                $summonCalled = is_callable([$this, 'summon']);
                return null;
            })
            ->build();

        $region->init();
        $region->trigger((object)[]);

        $this->assertTrue($summonCalled);
    }

    public function testSummonNotAvailableOutsideCallbacks(): void
    {
        $region = (new OrthogonalRegions(new RegionBuilder()))
            ->setStates('test')
            ->markInitial('test')
            ->build();

        $region->init();

        $this->assertFalse(method_exists($region, 'summon'), 'summon should not be a public method on Region');
    }
}
