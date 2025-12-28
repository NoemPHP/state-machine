<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\OrthogonalRegions;

use Noem\State\Feature\BoundAccess;
use Noem\State\Feature\OrthogonalRegions\OrthogonalRegions;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: OrthogonalRegions extends BoundAccess
 */
#[Group('orthogonal-regions')]
#[Group('feature')]
class OrthogonalRegionsExtendsTest extends TestCase
{
    public function testOrthogonalRegionsExtendsBoundAccess(): void
    {
        $builder = new RegionBuilder();
        $feature = new OrthogonalRegions($builder);

        $this->assertInstanceOf(BoundAccess::class, $feature, 'OrthogonalRegions should extend BoundAccess');
    }

    public function testOrthogonalRegionsIsAFeatureWrapper(): void
    {
        $builder = new RegionBuilder();
        $feature = new OrthogonalRegions($builder);

        // Should have all RegionBuilder methods
        $this->assertTrue(method_exists($feature, 'setStates'));
        $this->assertTrue(method_exists($feature, 'markInitial'));
        $this->assertTrue(method_exists($feature, 'markFinal'));
        $this->assertTrue(method_exists($feature, 'onEnter'));
        $this->assertTrue(method_exists($feature, 'onExit'));
        $this->assertTrue(method_exists($feature, 'onAction'));
        $this->assertTrue(method_exists($feature, 'build'));
    }

    public function testCanWrapRegionBuilderWithOrthogonalRegions(): void
    {
        $builder = new RegionBuilder();
        $feature = new OrthogonalRegions($builder);

        $region = $feature
            ->setStates('idle')
            ->markInitial('idle')
            ->build();

        $this->assertNotNull($region);
    }

    public function testOrthogonalRegionsPreservesBoundAccessFunctionality(): void
    {
        $builder = new RegionBuilder();
        $feature = new OrthogonalRegions($builder);

        $accessedValue = null;

        $region = $feature
            ->setStates('test')
            ->markInitial('test')
            ->onEnter('test', function (object $t) use (&$accessedValue) {
                // BoundAccess allows accessing $this->region
                $accessedValue = $this->region;
            })
            ->build();

        $region->init();

        $this->assertSame($region, $accessedValue, 'BoundAccess should bind $this->region');
    }
}
