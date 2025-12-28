<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\OrthogonalRegions;

use Noem\State\Feature\OrthogonalRegions\OrthogonalRegions;
use Noem\State\Feature\RegionLoader\RegionLoader;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: OrthogonalRegions works with RegionLoader for static region definitions
 */
#[Group('orthogonal-regions')]
#[Group('yaml-compatibility')]
class YamlCompatibilityStaticTest extends TestCase
{
    public function testStaticRegionsCanBeDefinedFromArrays(): void
    {
        $child1Config = [
            'states' => ['child1'],
            'initial' => 'child1',
        ];

        $child2Config = [
            'states' => ['child2'],
            'initial' => 'child2',
        ];

        $child1Builder = (new RegionLoader(new RegionBuilder()))->fromArray($child1Config);
        $child2Builder = (new RegionLoader(new RegionBuilder()))->fromArray($child2Config);

        $region = (new OrthogonalRegions(new RegionBuilder(), [$child1Builder, $child2Builder]))
            ->setStates('parent')
            ->markInitial('parent')
            ->build();

        $this->assertNotNull($region);
    }

    public function testRegionLoaderCanLoadStaticChildren(): void
    {
        $child1Config = [
            'states' => ['c1', 'done1'],
            'initial' => 'c1',
            'final' => ['done1'],
        ];

        $child2Config = [
            'states' => ['c2', 'done2'],
            'initial' => 'c2',
            'final' => ['done2'],
        ];

        $loader = new RegionLoader(new RegionBuilder());
        $child1 = $loader->fromArray($child1Config);
        $child2 = $loader->fromArray($child2Config);

        $region = (new OrthogonalRegions(new RegionBuilder(), [$child1, $child2]))
            ->setStates('parent', 'parent_done')
            ->markInitial('parent')
            ->markFinal('parent_done')
            ->build();

        $region->init();

        $this->assertEquals('parent', $region->currentState());
    }

    public function testStaticChildrenCanHaveComplexConfig(): void
    {
        $enterLog = [];

        $childConfig = [
            'states' => ['idle', 'processing', 'done'],
            'initial' => 'idle',
            'final' => ['done'],
            'on' => [
                'idle' => [
                    'enter' => function (object $t) use (&$enterLog) {
                        $enterLog[] = 'entered_idle';
                    },
                ],
            ],
        ];

        $childBuilder = (new RegionLoader(new RegionBuilder()))->fromArray($childConfig);

        $region = (new OrthogonalRegions(new RegionBuilder(), [$childBuilder]))
            ->setStates('parent')
            ->markInitial('parent')
            ->build();

        $region->init();

        $this->assertContains('entered_idle', $enterLog);
    }

    public function testMultipleStaticChildrenFromDifferentSources(): void
    {
        // One child from array
        $arrayChild = (new RegionLoader(new RegionBuilder()))->fromArray([
            'states' => ['array_child'],
            'initial' => 'array_child',
        ]);

        // One child from builder
        $builderChild = (new RegionBuilder())
            ->setStates('builder_child')
            ->markInitial('builder_child');

        $region = (new OrthogonalRegions(new RegionBuilder(), [$arrayChild, $builderChild]))
            ->setStates('parent')
            ->markInitial('parent')
            ->build();

        $this->assertNotNull($region);
    }

    public function testNestedStaticRegionsFromArrays(): void
    {
        $grandchildConfig = [
            'states' => ['grandchild'],
            'initial' => 'grandchild',
        ];

        $grandchild = (new RegionLoader(new RegionBuilder()))->fromArray($grandchildConfig);

        $childConfig = [
            'states' => ['child'],
            'initial' => 'child',
        ];

        $child = (new OrthogonalRegions(
            new RegionLoader(new RegionBuilder())->fromArray($childConfig),
            [$grandchild]
        ));

        $region = (new OrthogonalRegions(new RegionBuilder(), [$child]))
            ->setStates('parent')
            ->markInitial('parent')
            ->build();

        $this->assertNotNull($region);
    }
}
