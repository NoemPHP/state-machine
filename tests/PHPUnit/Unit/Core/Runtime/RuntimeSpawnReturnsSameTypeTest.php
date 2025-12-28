<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Core\Runtime;

use Noem\State\Region;
use Noem\State\RegionBuilder;
use Noem\State\Feature\Transitions\AddTransition;
use Noem\State\Runtime;
use Noem\State\RuntimeConfig;
use Noem\State\StandardRuntime;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Runtime.spawn() returns Runtime instance of same class
 */
#[Group('runtime')]
#[Group('runtime-spawning')]
class RuntimeSpawnReturnsSameTypeTest extends TestCase
{
    public function testSpawnReturnsInstanceOfSameClass(): void
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

        $this->assertInstanceOf(StandardRuntime::class, $childRuntime, 'spawn() should return instance of same runtime class');
    }

    public function testSpawnFromCustomRuntimeReturnsCustomType(): void
    {
        // Custom runtime class for testing
        $customRuntime = new class(
            (new RegionBuilder())->setStates('parent')->markInitial('parent')->build()
        ) extends Runtime {
            protected function createDefaultTrigger(int $iteration): object
            {
                return (object)['custom' => true];
            }
        };

        $childRegion = (new RegionBuilder())
            ->setStates('child')
            ->markInitial('child')
            ->build();

        $childRuntime = $customRuntime->spawn($childRegion);

        $this->assertInstanceOf(get_class($customRuntime), $childRuntime, 'spawn() from custom runtime should return custom runtime type');
    }

    public function testNestedSpawningPreservesType(): void
    {
        $grandparentRegion = (new RegionBuilder())
            ->setStates('grandparent')
            ->markInitial('grandparent')
            ->build();

        $parentRegion = (new RegionBuilder())
            ->setStates('parent')
            ->markInitial('parent')
            ->build();

        $childRegion = (new RegionBuilder())
            ->setStates('child')
            ->markInitial('child')
            ->build();

        $grandparentRuntime = new StandardRuntime($grandparentRegion);

        $parentRuntime = $grandparentRuntime->spawn($parentRegion);
        $childRuntime = $parentRuntime->spawn($childRegion);

        $this->assertInstanceOf(StandardRuntime::class, $parentRuntime);
        $this->assertInstanceOf(StandardRuntime::class, $childRuntime);
    }

    public function testSpawnedRuntimeBehavesLikeParentType(): void
    {
        $parentRegion = (new RegionBuilder())
            ->setStates('parent')
            ->markInitial('parent')
            ->build();

        $childRegion = (new RegionBuilder())
            ->setStates('child', 'done')
            ->markInitial('child')
            ->markFinal('done')
            ->addBuildStep(new AddTransition('child', 'done'))
            ->build();

        $parentRuntime = new StandardRuntime($parentRegion);

        $childRuntime = $parentRuntime->spawn($childRegion);

        // Child should have all same methods as parent
        $this->assertTrue(method_exists($childRuntime, 'run'));
        $this->assertTrue(method_exists($childRuntime, 'events'));
        $this->assertTrue(method_exists($childRuntime, 'spawn'));
        $this->assertTrue(method_exists($childRuntime, 'getRegion'));
        $this->assertTrue(method_exists($childRuntime, 'getConfig'));
        $this->assertTrue(method_exists($childRuntime, 'isComplete'));

        // And should be executable
        $result = $childRuntime->run();
        $this->assertFalse($result);
        $this->assertTrue($childRuntime->isComplete());
    }
}
