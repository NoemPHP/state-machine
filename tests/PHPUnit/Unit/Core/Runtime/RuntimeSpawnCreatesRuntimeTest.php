<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Core\Runtime;

use Noem\State\Region;
use Noem\State\RegionBuilder;
use Noem\State\Feature\Transitions\AddTransition;
use Noem\State\Runtime;
use Noem\State\StandardRuntime;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Runtime.spawn() creates new runtime instance with child region
 */
#[Group('runtime')]
#[Group('runtime-spawning')]
class RuntimeSpawnCreatesRuntimeTest extends TestCase
{
    public function testSpawnCreatesNewRuntimeInstance(): void
    {
        $parentRegion = (new RegionBuilder())
            ->setStates('start')
            ->markInitial('start')
            ->build();

        $childRegion = (new RegionBuilder())
            ->setStates('child_start')
            ->markInitial('child_start')
            ->build();

        $parentRuntime = new StandardRuntime($parentRegion);

        $childRuntime = $parentRuntime->spawn($childRegion);

        $this->assertInstanceOf(Runtime::class, $childRuntime, 'spawn() should return Runtime instance');
        $this->assertNotSame($parentRuntime, $childRuntime, 'spawn() should create new instance');
    }

    public function testSpawnWrapsProvidedChildRegion(): void
    {
        $parentRegion = (new RegionBuilder())
            ->setStates('parent')
            ->markInitial('parent')
            ->build();

        $childRegion = (new RegionBuilder())
            ->setStates('child')
            ->markInitial('child')
            ->build();

        $parentRuntime = new StandardRuntime($parentRegion);

        $childRuntime = $parentRuntime->spawn($childRegion);

        $this->assertSame($childRegion, $childRuntime->getRegion(), 'Spawned runtime should wrap provided child region');
    }

    public function testSpawnedRuntimeCanBeExecuted(): void
    {
        $parentRegion = (new RegionBuilder())
            ->setStates('parent')
            ->markInitial('parent')
            ->build();

        $executed = false;

        $childRegion = (new RegionBuilder())
            ->setStates('child', 'child_done')
            ->markInitial('child')
            ->markFinal('child_done')
            ->addBuildStep(new AddTransition('child', 'child_done'))
            ->onAction('child', function (object $t) use (&$executed): void {
                $executed = true;
            })
            ->build();

        $parentRuntime = new StandardRuntime($parentRegion);

        $childRuntime = $parentRuntime->spawn($childRegion);

        $childRuntime->run();

        $this->assertTrue($executed, 'Spawned runtime should be executable');
    }

    public function testMultipleSpawnCallsCreateIndependentRuntimes(): void
    {
        $parentRegion = (new RegionBuilder())
            ->setStates('parent')
            ->markInitial('parent')
            ->build();

        $child1Region = (new RegionBuilder())
            ->setStates('child1')
            ->markInitial('child1')
            ->build();

        $child2Region = (new RegionBuilder())
            ->setStates('child2')
            ->markInitial('child2')
            ->build();

        $parentRuntime = new StandardRuntime($parentRegion);

        $child1Runtime = $parentRuntime->spawn($child1Region);
        $child2Runtime = $parentRuntime->spawn($child2Region);

        $this->assertNotSame($child1Runtime, $child2Runtime, 'Each spawn() should create independent runtime');
        $this->assertSame($child1Region, $child1Runtime->getRegion());
        $this->assertSame($child2Region, $child2Runtime->getRegion());
    }
}
