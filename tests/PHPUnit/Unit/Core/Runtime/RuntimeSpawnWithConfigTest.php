<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Core\Runtime;

use Noem\State\RegionBuilder;
use Noem\State\Feature\Transitions\AddTransition;
use Noem\State\RuntimeConfig;
use Noem\State\StandardRuntime;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Runtime.spawn() accepts optional RuntimeConfig for child
 */
#[Group('runtime')]
#[Group('runtime-spawning')]
class RuntimeSpawnWithConfigTest extends TestCase
{
    public function testSpawnAcceptsRuntimeConfig(): void
    {
        $parentRegion = (new RegionBuilder())
            ->setStates('parent')
            ->markInitial('parent')
            ->build();

        $childRegion = (new RegionBuilder())
            ->setStates('child')
            ->markInitial('child')
            ->build();

        $childConfig = new RuntimeConfig(maxIterations: 500);

        $parentRuntime = new StandardRuntime($parentRegion);

        $childRuntime = $parentRuntime->spawn($childRegion, $childConfig);

        $this->assertSame($childConfig, $childRuntime->getConfig(), 'Spawned runtime should use provided config');
    }

    public function testSpawnWithoutConfigUsesDefaultConfig(): void
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

        $config = $childRuntime->getConfig();

        $this->assertInstanceOf(RuntimeConfig::class, $config);
        $this->assertEquals(10000, $config->maxIterations, 'Should have default maxIterations');
    }

    public function testSpawnedRuntimeCanHaveDifferentConfigThanParent(): void
    {
        $parentConfig = new RuntimeConfig(maxIterations: 1000);

        $parentRegion = (new RegionBuilder())
            ->setStates('parent')
            ->markInitial('parent')
            ->build();

        $childRegion = (new RegionBuilder())
            ->setStates('child')
            ->markInitial('child')
            ->build();

        $childConfig = new RuntimeConfig(maxIterations: 500);

        $parentRuntime = new StandardRuntime($parentRegion, $parentConfig);

        $childRuntime = $parentRuntime->spawn($childRegion, $childConfig);

        $this->assertEquals(1000, $parentRuntime->getConfig()->maxIterations);
        $this->assertEquals(500, $childRuntime->getConfig()->maxIterations);
    }

    public function testSpawnedRuntimeUsesProvidedCallbacks(): void
    {
        $onIterationCalled = false;
        $onCompleteCalled = false;

        $callback1 = function ($r, $t, $i) use (&$onIterationCalled) {
            $onIterationCalled = true;
        };

        $callback2 = function () use (&$onCompleteCalled) {
            $onCompleteCalled = true;
        };

        $childConfig = new RuntimeConfig(
            onIteration: $callback1,
            onComplete: $callback2
        );

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

        $childRuntime = $parentRuntime->spawn($childRegion, $childConfig);

        $childRuntime->run();

        $this->assertTrue($onIterationCalled, 'Child runtime should use provided onIteration');
        $this->assertTrue($onCompleteCalled, 'Child runtime should use provided onComplete');
    }
}
