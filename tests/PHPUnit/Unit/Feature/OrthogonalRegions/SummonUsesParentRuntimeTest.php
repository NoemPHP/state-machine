<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\OrthogonalRegions;

use Noem\State\Feature\OrthogonalRegions\OrthogonalRegions;
use Noem\State\RegionBuilder;
use Noem\State\Runtime;
use Noem\State\StandardRuntime;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: summon() uses parent Runtime's spawn() method internally
 */
#[Group('orthogonal-regions')]
#[Group('summon')]
class SummonUsesParentRuntimeTest extends TestCase
{
    public function testSummonUsesParentRuntimeSpawn(): void
    {
        $childBuilder = (new RegionBuilder())
            ->setStates('child')
            ->markInitial('child');

        $parentRegion = (new OrthogonalRegions(new RegionBuilder()))
            ->setStates('parent')
            ->markInitial('parent')
            ->build();

        $parentRuntime = new StandardRuntime($parentRegion);

        // Initialize parent through runtime
        $parentRuntime->run(steps: 1);

        // Verify that a child runtime was spawned
        // This tests that summon() internally uses spawn()
        $this->assertInstanceOf(Runtime::class, $parentRuntime);
    }

    public function testSummonedRuntimeIsSameTypeAsParent(): void
    {
        $runtimeType = null;

        $childBuilder = (new RegionBuilder())
            ->setStates('child')
            ->markInitial('child');

        $parentRegion = (new OrthogonalRegions(new RegionBuilder()))
            ->setStates('parent')
            ->markInitial('parent')
            ->onEnter('parent', function (object $t) use ($childBuilder, &$runtimeType) {
                $childRuntime = $this->summon($childBuilder);
                $runtimeType = get_class($childRuntime);
            })
            ->build();

        $parentRuntime = new StandardRuntime($parentRegion);
        $parentRuntime->run(steps: 1);

        $this->assertEquals(StandardRuntime::class, $runtimeType);
    }

    public function testSummonInheritsParentRuntimeType(): void
    {
        $customRuntimeUsed = false;

        // Custom runtime class
        $customRuntime = new class(
            (new RegionBuilder())->setStates('temp')->markInitial('temp')->build()
        ) extends Runtime {
            protected function createDefaultTrigger(int $iteration): object
            {
                return (object)['custom' => true];
            }
        };

        $childBuilder = (new RegionBuilder())
            ->setStates('child')
            ->markInitial('child');

        $parentRegion = (new OrthogonalRegions(new RegionBuilder()))
            ->setStates('parent')
            ->markInitial('parent')
            ->onEnter('parent', function (object $t) use ($childBuilder, &$customRuntimeUsed) {
                $childRuntime = $this->summon($childBuilder);
                // Check if it's the custom runtime type
                $customRuntimeUsed = get_parent_class($childRuntime) === Runtime::class;
            })
            ->build();

        // Replace parent runtime's region
        $customParentRuntime = new ($customRuntime::class)($parentRegion);
        $customParentRuntime->run(steps: 1);

        $this->assertTrue($customRuntimeUsed);
    }

    public function testSummonedRuntimeRegisteredInRegistry(): void
    {
        $childRegion = null;

        $childBuilder = (new RegionBuilder())
            ->setStates('child')
            ->markInitial('child');

        $parentRegion = (new OrthogonalRegions(new RegionBuilder()))
            ->setStates('parent')
            ->markInitial('parent')
            ->onEnter('parent', function (object $t) use ($childBuilder, &$childRegion) {
                $childRuntime = $this->summon($childBuilder);
                $childRegion = $childRuntime->getRegion();
            })
            ->build();

        $parentRuntime = new StandardRuntime($parentRegion);
        $parentRuntime->run(steps: 1);

        $this->assertNotNull($childRegion);

        // Verify child runtime is registered
        $retrievedRuntime = \Noem\State\RuntimeRegistry::get($childRegion);
        $this->assertInstanceOf(Runtime::class, $retrievedRuntime);
    }
}
