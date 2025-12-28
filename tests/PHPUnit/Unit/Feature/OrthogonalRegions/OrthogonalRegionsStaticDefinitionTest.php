<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\OrthogonalRegions;

use Noem\State\Feature\OrthogonalRegions\OrthogonalRegions;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: OrthogonalRegions accepts static orthogonal region definitions
 */
#[Group('orthogonal-regions')]
#[Group('feature')]
class OrthogonalRegionsStaticDefinitionTest extends TestCase
{
    public function testAcceptsArrayOfRegionBuilders(): void
    {
        $child1 = (new RegionBuilder())
            ->setStates('c1_idle')
            ->markInitial('c1_idle');

        $child2 = (new RegionBuilder())
            ->setStates('c2_idle')
            ->markInitial('c2_idle');

        $region = (new OrthogonalRegions(new RegionBuilder(), [$child1, $child2]))
            ->setStates('parent')
            ->markInitial('parent')
            ->build();

        $this->assertNotNull($region);
    }

    public function testAcceptsEmptyArrayForNoStaticRegions(): void
    {
        $region = (new OrthogonalRegions(new RegionBuilder(), []))
            ->setStates('parent')
            ->markInitial('parent')
            ->build();

        $this->assertNotNull($region);
    }

    public function testStaticRegionsAreBuiltDuringParentInit(): void
    {
        $child1Built = false;
        $child2Built = false;

        $child1 = (new RegionBuilder())
            ->setStates('c1')
            ->markInitial('c1')
            ->onEnter('c1', function (object $t) use (&$child1Built) {
                $child1Built = true;
            });

        $child2 = (new RegionBuilder())
            ->setStates('c2')
            ->markInitial('c2')
            ->onEnter('c2', function (object $t) use (&$child2Built) {
                $child2Built = true;
            });

        $region = (new OrthogonalRegions(new RegionBuilder(), [$child1, $child2]))
            ->setStates('parent')
            ->markInitial('parent')
            ->build();

        $region->init();

        $this->assertTrue($child1Built, 'Child 1 should be initialized');
        $this->assertTrue($child2Built, 'Child 2 should be initialized');
    }

    public function testStaticRegionsExecuteInParallel(): void
    {
        $executionOrder = [];

        $child1 = (new RegionBuilder())
            ->setStates('c1', 'c1_done')
            ->markInitial('c1')
            ->markFinal('c1_done')
            ->onAction('c1', function (object $t) use (&$executionOrder) {
                $executionOrder[] = 'child1';
                return 'c1_done';
            });

        $child2 = (new RegionBuilder())
            ->setStates('c2', 'c2_done')
            ->markInitial('c2')
            ->markFinal('c2_done')
            ->onAction('c2', function (object $t) use (&$executionOrder) {
                $executionOrder[] = 'child2';
                return 'c2_done';
            });

        $region = (new OrthogonalRegions(new RegionBuilder(), [$child1, $child2]))
            ->setStates('parent', 'parent_done')
            ->markInitial('parent')
            ->markFinal('parent_done')
            ->onAction('parent', fn(object $t) => 'parent_done')
            ->build();

        $runtime = new \Noem\State\StandardRuntime($region);
        $runtime->run();

        $this->assertContains('child1', $executionOrder);
        $this->assertContains('child2', $executionOrder);
    }

    public function testMultipleStaticRegionsCanBeNested(): void
    {
        $grandchild = (new RegionBuilder())
            ->setStates('gc')
            ->markInitial('gc');

        $child = (new OrthogonalRegions(new RegionBuilder(), [$grandchild]))
            ->setStates('child')
            ->markInitial('child');

        $parent = (new OrthogonalRegions(new RegionBuilder(), [$child]))
            ->setStates('parent')
            ->markInitial('parent')
            ->build();

        $this->assertNotNull($parent);
    }
}
