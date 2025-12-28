<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\OrthogonalRegions;

use Noem\State\Feature\OrthogonalRegions\OrthogonalRegions;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: summon() enables dynamic orthogonal region creation at runtime
 */
#[Group('orthogonal-regions')]
#[Group('summon')]
class SummonDynamicCreationTest extends TestCase
{
    public function testSummonCreatesRegionDynamically(): void
    {
        $dynamicChildCreated = false;

        $parentRegion = (new OrthogonalRegions(new RegionBuilder()))
            ->setStates('idle', 'spawning')
            ->markInitial('idle')
            ->onAction('idle', function (object $t) use (&$dynamicChildCreated) {
                // Create child dynamically based on runtime condition
                if (true) { // Condition evaluated at runtime
                    $childBuilder = (new RegionBuilder())
                        ->setStates('dynamic_child')
                        ->markInitial('dynamic_child');

                    $this->summon($childBuilder);
                    $dynamicChildCreated = true;
                }
                return 'spawning';
            })
            ->build();

        $runtime = new \Noem\State\StandardRuntime($parentRegion);
        $runtime->run(steps: 2);

        $this->assertTrue($dynamicChildCreated);
    }

    public function testSummonCanCreateDifferentRegionsBasedOnConditions(): void
    {
        $createdTypes = [];

        $testConditions = function ($condition) use (&$createdTypes) {
            $parentRegion = (new OrthogonalRegions(new RegionBuilder()))
                ->setStates('parent')
                ->markInitial('parent')
                ->onEnter('parent', function (object $t) use ($condition, &$createdTypes) {
                    if ($condition === 'typeA') {
                        $child = (new RegionBuilder())
                            ->setStates('type_a')
                            ->markInitial('type_a');
                        $this->summon($child);
                        $createdTypes[] = 'A';
                    } elseif ($condition === 'typeB') {
                        $child = (new RegionBuilder())
                            ->setStates('type_b')
                            ->markInitial('type_b');
                        $this->summon($child);
                        $createdTypes[] = 'B';
                    }
                })
                ->build();

            $runtime = new \Noem\State\StandardRuntime($parentRegion);
            $runtime->run(steps: 1);
        };

        $testConditions('typeA');
        $testConditions('typeB');

        $this->assertEquals(['A', 'B'], $createdTypes);
    }

    public function testSummonCreatesChildrenOnDemand(): void
    {
        $childCount = 0;

        $parentRegion = (new OrthogonalRegions(new RegionBuilder()))
            ->setStates('spawner', 'done')
            ->markInitial('spawner')
            ->markFinal('done')
            ->onAction('spawner', function (object $t) use (&$childCount) {
                static $iterations = 0;
                $iterations++;

                if ($iterations <= 3) {
                    // Summon a new child on each iteration
                    $child = (new RegionBuilder())
                        ->setStates("child_{$iterations}")
                        ->markInitial("child_{$iterations}");

                    $this->summon($child);
                    $childCount++;

                    return 'spawner'; // Stay in spawner state
                }

                return 'done';
            })
            ->build();

        $runtime = new \Noem\State\StandardRuntime($parentRegion);
        $runtime->run();

        $this->assertEquals(3, $childCount, 'Should create 3 children on demand');
    }

    public function testSummonSupportsConditionalRegionCreation(): void
    {
        $evenChildrenCount = 0;
        $oddChildrenCount = 0;

        $parentRegion = (new OrthogonalRegions(new RegionBuilder()))
            ->setStates('conditional', 'done')
            ->markInitial('conditional')
            ->markFinal('done')
            ->onAction('conditional', function (object $t) use (&$evenChildrenCount, &$oddChildrenCount) {
                static $iteration = 0;
                $iteration++;

                if ($iteration <= 5) {
                    if ($iteration % 2 === 0) {
                        $child = (new RegionBuilder())
                            ->setStates('even')
                            ->markInitial('even');
                        $this->summon($child);
                        $evenChildrenCount++;
                    } else {
                        $child = (new RegionBuilder())
                            ->setStates('odd')
                            ->markInitial('odd');
                        $this->summon($child);
                        $oddChildrenCount++;
                    }
                    return 'conditional';
                }

                return 'done';
            })
            ->build();

        $runtime = new \Noem\State\StandardRuntime($parentRegion);
        $runtime->run();

        $this->assertEquals(2, $evenChildrenCount, 'Should create 2 even children (iterations 2, 4)');
        $this->assertEquals(3, $oddChildrenCount, 'Should create 3 odd children (iterations 1, 3, 5)');
    }

    public function testSummonAllowsDataDrivenRegionCreation(): void
    {
        $dataItems = ['item1', 'item2', 'item3'];
        $processedItems = [];

        $parentRegion = (new OrthogonalRegions(new RegionBuilder()))
            ->setStates('processor')
            ->markInitial('processor')
            ->onEnter('processor', function (object $t) use ($dataItems, &$processedItems) {
                foreach ($dataItems as $item) {
                    $child = (new RegionBuilder())
                        ->setStates($item)
                        ->markInitial($item)
                        ->onEnter($item, function ($trigger) use ($item, &$processedItems) {
                            $processedItems[] = $item;
                        });

                    $runtime = $this->summon($child);
                    $runtime->run();
                }
            })
            ->build();

        $runtime = new \Noem\State\StandardRuntime($parentRegion);
        $runtime->run(steps: 1);

        $this->assertEquals($dataItems, $processedItems);
    }
}
