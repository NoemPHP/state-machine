<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\OrthogonalRegions;

use Noem\State\Feature\BoundAccess;
use Noem\State\Feature\OrthogonalRegions\OrthogonalRegions;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: OrthogonalRegions extends BoundAccess for $this->region access
 */
#[Group('orthogonal-regions')]
#[Group('feature-dependencies')]
class FeatureDependenciesBoundAccessTest extends TestCase
{
    public function testOrthogonalRegionsExtendsBoundAccess(): void
    {
        $builder = new RegionBuilder();
        $feature = new OrthogonalRegions($builder);

        $this->assertInstanceOf(BoundAccess::class, $feature);
    }

    public function testBoundAccessProvidesTthisRegionAccess(): void
    {
        $regionAccessible = false;

        $region = (new OrthogonalRegions(new RegionBuilder()))
            ->setStates('test')
            ->markInitial('test')
            ->onEnter('test', function (object $t) use (&$regionAccessible) {
                $regionAccessible = property_exists($this, 'region') || isset($this->region);
            })
            ->build();

        $region->init();

        $this->assertTrue($regionAccessible, 'BoundAccess should provide $this->region');
    }

    public function testSummonMethodAvailableViaBoundAccess(): void
    {
        $summonAccessible = false;

        $region = (new OrthogonalRegions(new RegionBuilder()))
            ->setStates('parent')
            ->markInitial('parent')
            ->onEnter('parent', function (object $t) use (&$summonAccessible) {
                // summon() is made available through BoundAccess magic
                $summonAccessible = is_callable([$this, 'summon']);
            })
            ->build();

        $region->init();

        $this->assertTrue($summonAccessible);
    }

    public function testBoundAccessEnablesRegionMethodCalls(): void
    {
        $canTrigger = false;
        $canGetState = false;

        $region = (new OrthogonalRegions(new RegionBuilder()))
            ->setStates('test', 'next')
            ->markInitial('test')
            ->onEnter('test', function (object $t) use (&$canTrigger, &$canGetState) {
                // Access region methods via $this->region
                $canGetState = is_callable([$this->region, 'currentState']);
                $canTrigger = is_callable([$this->region, 'trigger']);
            })
            ->build();

        $region->init();

        $this->assertTrue($canGetState, 'Should be able to call region methods');
        $this->assertTrue($canTrigger, 'Should be able to call trigger');
    }

    public function testOrthogonalRegionsAddsThisSummonToBoundAccess(): void
    {
        $summonWorks = false;

        $childBuilder = (new RegionBuilder())
            ->setStates('child')
            ->markInitial('child');

        $region = (new OrthogonalRegions(new RegionBuilder()))
            ->setStates('parent')
            ->markInitial('parent')
            ->onEnter('parent', function (object $t) use ($childBuilder, &$summonWorks) {
                try {
                    $runtime = $this->summon($childBuilder);
                    $summonWorks = $runtime !== null;
                } catch (\Throwable $e) {
                    $summonWorks = false;
                }
            })
            ->build();

        $region->init();

        $this->assertTrue($summonWorks, 'OrthogonalRegions adds summon() to BoundAccess scope');
    }

    public function testBoundAccessInheritsToStaticChildren(): void
    {
        $childHasRegionAccess = false;

        $child = (new RegionBuilder())
            ->setStates('child')
            ->markInitial('child')
            ->onEnter('child', function (object $t) use (&$childHasRegionAccess) {
                $childHasRegionAccess = isset($this->region);
            });

        $region = (new OrthogonalRegions(new RegionBuilder(), [$child]))
            ->setStates('parent')
            ->markInitial('parent')
            ->build();

        $region->init();

        $this->assertTrue($childHasRegionAccess, 'Static children should have BoundAccess');
    }

    public function testBoundAccessInheritsToSummonedChildren(): void
    {
        $summonedChildHasRegionAccess = false;

        $childBuilder = (new RegionBuilder())
            ->setStates('child')
            ->markInitial('child')
            ->onEnter('child', function (object $t) use (&$summonedChildHasRegionAccess) {
                $summonedChildHasRegionAccess = isset($this->region);
            });

        $region = (new OrthogonalRegions(new RegionBuilder()))
            ->setStates('parent')
            ->markInitial('parent')
            ->onEnter('parent', function (object $t) use ($childBuilder) {
                $this->summon($childBuilder)->run();
            })
            ->build();

        $region->init();

        $this->assertTrue($summonedChildHasRegionAccess, 'Summoned children should have BoundAccess');
    }

    public function testBoundAccessEnablesRecursiveSummon(): void
    {
        $grandchildSummoned = false;

        $grandchildBuilder = (new RegionBuilder())
            ->setStates('grandchild')
            ->markInitial('grandchild')
            ->onEnter('grandchild', function (object $t) use (&$grandchildSummoned) {
                $grandchildSummoned = true;
            });

        $childBuilder = (new OrthogonalRegions(new RegionBuilder()))
            ->setStates('child')
            ->markInitial('child')
            ->onEnter('child', function (object $t) use ($grandchildBuilder) {
                // Child can summon grandchild because it has BoundAccess
                $this->summon($grandchildBuilder)->run();
            });

        $region = (new OrthogonalRegions(new RegionBuilder()))
            ->setStates('parent')
            ->markInitial('parent')
            ->onEnter('parent', function (object $t) use ($childBuilder) {
                $this->summon($childBuilder)->run();
            })
            ->build();

        $region->init();

        $this->assertTrue($grandchildSummoned, 'BoundAccess enables recursive summon');
    }
}
