<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\OrthogonalRegions;

use Noem\State\Feature\OrthogonalRegions\OrthogonalRegions;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: OrthogonalRegions accepts array of RegionBuilder instances
 */
#[Group('orthogonal-regions')]
#[Group('feature')]
class OrthogonalRegionsBuilderArrayTest extends TestCase
{
    public function testAcceptsRegionBuilderArray(): void
    {
        $builders = [
            (new RegionBuilder())->setStates('child1')->markInitial('child1'),
            (new RegionBuilder())->setStates('child2')->markInitial('child2'),
            (new RegionBuilder())->setStates('child3')->markInitial('child3'),
        ];

        $region = (new OrthogonalRegions(new RegionBuilder(), $builders))
            ->setStates('parent')
            ->markInitial('parent')
            ->build();

        $this->assertNotNull($region);
    }

    public function testBuilderArrayCanContainDifferentBuilderTypes(): void
    {
        $simpleBuilder = (new RegionBuilder())
            ->setStates('simple')
            ->markInitial('simple');

        $complexBuilder = (new RegionBuilder())
            ->setStates('s1', 's2', 's3')
            ->markInitial('s1')
            ->markFinal('s3');

        $region = (new OrthogonalRegions(new RegionBuilder(), [$simpleBuilder, $complexBuilder]))
            ->setStates('parent')
            ->markInitial('parent')
            ->build();

        $this->assertNotNull($region);
    }

    public function testBuilderArrayPreservesOrder(): void
    {
        $initOrder = [];

        $builders = [];
        for ($i = 1; $i <= 5; $i++) {
            $builders[] = (new RegionBuilder())
                ->setStates("child{$i}")
                ->markInitial("child{$i}")
                ->onEnter("child{$i}", function (object $t) use (&$initOrder, $i) {
                    $initOrder[] = $i;
                });
        }

        $region = (new OrthogonalRegions(new RegionBuilder(), $builders))
            ->setStates('parent')
            ->markInitial('parent')
            ->build();

        $region->init();

        $this->assertEquals([1, 2, 3, 4, 5], $initOrder, 'Children should initialize in array order');
    }

    public function testEachBuilderIsIndependent(): void
    {
        $counter1 = 0;
        $counter2 = 0;

        $builder1 = (new RegionBuilder())
            ->setStates('b1')
            ->markInitial('b1')
            ->onEnter('b1', function (object $t) use (&$counter1) {
                $counter1++;
            });

        $builder2 = (new RegionBuilder())
            ->setStates('b2')
            ->markInitial('b2')
            ->onEnter('b2', function (object $t) use (&$counter2) {
                $counter2++;
            });

        $region = (new OrthogonalRegions(new RegionBuilder(), [$builder1, $builder2]))
            ->setStates('parent')
            ->markInitial('parent')
            ->build();

        $region->init();

        $this->assertEquals(1, $counter1, 'Builder 1 should execute independently');
        $this->assertEquals(1, $counter2, 'Builder 2 should execute independently');
    }

    public function testBuilderArrayCanBeEmpty(): void
    {
        $region = (new OrthogonalRegions(new RegionBuilder(), []))
            ->setStates('parent')
            ->markInitial('parent')
            ->build();

        $region->init();

        $this->assertEquals('parent', $region->currentState());
    }
}
