<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\OrthogonalRegions;

use Noem\State\Feature\OrthogonalRegions\OrthogonalRegions;
use Noem\State\RegionBuilder;
use Noem\State\Runtime;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: summon() returned Runtime can be controlled by parent
 */
#[Group('orthogonal-regions')]
#[Group('summon')]
class SummonReturnedRuntimeTest extends TestCase
{
    public function testReturnedRuntimeCanBeStoredAndControlled(): void
    {
        $storedRuntime = null;

        $childBuilder = (new RegionBuilder())
            ->setStates('child', 'done')
            ->markInitial('child')
            ->markFinal('done')
            ->onAction('child', fn(object $t) => 'done');

        $parentRegion = (new OrthogonalRegions(new RegionBuilder()))
            ->setStates('parent')
            ->markInitial('parent')
            ->onEnter('parent', function (object $t) use ($childBuilder, &$storedRuntime) {
                $storedRuntime = $this->summon($childBuilder);
            })
            ->build();

        $parentRegion->init();

        $this->assertInstanceOf(Runtime::class, $storedRuntime);

        // Control the child runtime
        $storedRuntime->run();
        $this->assertTrue($storedRuntime->isComplete());
    }

    public function testParentCanStepChildRuntimeIncrementally(): void
    {
        $childRuntime = null;
        $iterationCount = 0;

        $childBuilder = (new RegionBuilder())
            ->setStates('counting', 'done')
            ->markInitial('counting')
            ->markFinal('done')
            ->onAction('counting', function (object $t) use (&$iterationCount) {
                $iterationCount++;
                return $iterationCount >= 3 ? 'done' : 'counting';
            });

        $parentRegion = (new OrthogonalRegions(new RegionBuilder()))
            ->setStates('parent')
            ->markInitial('parent')
            ->onEnter('parent', function (object $t) use ($childBuilder, &$childRuntime) {
                $childRuntime = $this->summon($childBuilder);
            })
            ->build();

        $parentRegion->init();

        // Parent steps child incrementally
        $childRuntime->run(steps: 1);
        $this->assertEquals(1, $iterationCount);

        $childRuntime->run(steps: 1);
        $this->assertEquals(2, $iterationCount);

        $childRuntime->run(steps: 1);
        $this->assertEquals(3, $iterationCount);
        $this->assertTrue($childRuntime->isComplete());
    }

    public function testParentCanQueryChildRuntimeState(): void
    {
        $childRuntime = null;

        $childBuilder = (new RegionBuilder())
            ->setStates('child', 'processing', 'done')
            ->markInitial('child')
            ->markFinal('done')
            ->onAction('child', fn(object $t) => 'processing')
            ->onAction('processing', fn(object $t) => 'done');

        $parentRegion = (new OrthogonalRegions(new RegionBuilder()))
            ->setStates('parent')
            ->markInitial('parent')
            ->onEnter('parent', function (object $t) use ($childBuilder, &$childRuntime) {
                $childRuntime = $this->summon($childBuilder);
            })
            ->build();

        $parentRegion->init();

        $this->assertFalse($childRuntime->isComplete());

        $childRuntime->run(steps: 1);
        $this->assertFalse($childRuntime->isComplete());

        $childRuntime->run(steps: 1);
        $this->assertTrue($childRuntime->isComplete());

        $childRegion = $childRuntime->getRegion();
        $this->assertEquals('done', $childRegion->currentState());
    }

    public function testParentCanAccessChildRuntimeEvents(): void
    {
        $childRuntime = null;
        $childEvents = [];

        $childBuilder = (new RegionBuilder())
            ->setStates('child', 'done')
            ->markInitial('child')
            ->markFinal('done')
            ->onEnter('child', function (object $t) use (&$childBuilder) {
                // Simulate child region having a reference to itself
                $region = $this->region ?? null;
                if ($region) {
                    $region->trigger((object)['type' => 'child_event'], enqueue: true);
                }
            })
            ->onAction('child', fn(object $t) => 'done');

        $parentRegion = (new OrthogonalRegions(new RegionBuilder()))
            ->setStates('parent')
            ->markInitial('parent')
            ->onEnter('parent', function (object $t) use ($childBuilder, &$childRuntime) {
                $childRuntime = $this->summon($childBuilder);
            })
            ->build();

        $parentRegion->init();

        // Parent consumes child events
        foreach ($childRuntime->events() as $event) {
            if (isset($event->type)) {
                $childEvents[] = $event;
            }
        }

        $this->assertNotEmpty($childEvents);
    }

    public function testMultipleSummonedRuntimesCanBeControlledIndependently(): void
    {
        $runtime1 = null;
        $runtime2 = null;

        $builder1 = (new RegionBuilder())
            ->setStates('child1', 'done1')
            ->markInitial('child1')
            ->markFinal('done1')
            ->onAction('child1', fn(object $t) => 'done1');

        $builder2 = (new RegionBuilder())
            ->setStates('child2', 'middle', 'done2')
            ->markInitial('child2')
            ->markFinal('done2')
            ->onAction('child2', fn(object $t) => 'middle')
            ->onAction('middle', fn(object $t) => 'done2');

        $parentRegion = (new OrthogonalRegions(new RegionBuilder()))
            ->setStates('parent')
            ->markInitial('parent')
            ->onEnter('parent', function (object $t) use ($builder1, $builder2, &$runtime1, &$runtime2) {
                $runtime1 = $this->summon($builder1);
                $runtime2 = $this->summon($builder2);
            })
            ->build();

        $parentRegion->init();

        // Control runtime1
        $runtime1->run();
        $this->assertTrue($runtime1->isComplete());
        $this->assertFalse($runtime2->isComplete());

        // Control runtime2
        $runtime2->run(steps: 1);
        $this->assertFalse($runtime2->isComplete());

        $runtime2->run(steps: 1);
        $this->assertTrue($runtime2->isComplete());
    }

    public function testParentCanSpawnAdditionalRuntimesFromSummonedRuntime(): void
    {
        $childRuntime = null;
        $grandchildRuntime = null;

        $grandchildBuilder = (new RegionBuilder())
            ->setStates('grandchild')
            ->markInitial('grandchild');

        $childBuilder = (new RegionBuilder())
            ->setStates('child')
            ->markInitial('child');

        $parentRegion = (new OrthogonalRegions(new RegionBuilder()))
            ->setStates('parent')
            ->markInitial('parent')
            ->onEnter('parent', function (object $t) use ($childBuilder, $grandchildBuilder, &$childRuntime, &$grandchildRuntime) {
                $childRuntime = $this->summon($childBuilder);

                // Parent can spawn from child's runtime
                $grandchildRuntime = $childRuntime->spawn($grandchildBuilder->build());
            })
            ->build();

        $parentRegion->init();

        $this->assertInstanceOf(Runtime::class, $childRuntime);
        $this->assertInstanceOf(Runtime::class, $grandchildRuntime);
    }
}
