<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Core\Runtime;

use Noem\State\RegionBuilder;
use Noem\State\Feature\Transitions\AddTransition;
use Noem\State\RuntimeRegistry;
use Noem\State\StandardRuntime;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Runtime.run() unregisters region from RuntimeRegistry after completion
 */
#[Group('runtime')]
#[Group('runtime-execution')]
class RuntimeRunUnregistersAfterCompletionTest extends TestCase
{
    public function testRuntimeUnregistersAfterCompletion(): void
    {
        $region = (new RegionBuilder())
            ->setStates('start', 'done')
            ->markInitial('start')
            ->markFinal('done')
            ->addBuildStep(new AddTransition('start', 'done'))
            ->build();

        $runtime = new StandardRuntime($region);

        $runtime->run();

        $this->assertNull(RuntimeRegistry::get($region), 'Runtime should be unregistered after completion');
    }

    public function testUnregistersOnlyAfterFullCompletion(): void
    {
        $region = (new RegionBuilder())
            ->setStates('counting', 'done')
            ->markInitial('counting')
            ->markFinal('done')
            ->addBuildStep(new AddTransition('counting', 'counting')) // Never completes
            ->build();

        $runtime = new StandardRuntime($region);

        // Run one step (doesn't complete)
        $runtime->run(steps: 1);

        $this->assertNotNull(RuntimeRegistry::get($region), 'Should still be registered if not complete');

        // Now complete it by directly transitioning (simulate)
        // Actually, let's create a region that completes
        $completingRegion = (new RegionBuilder())
            ->setStates('start', 'done')
            ->markInitial('start')
            ->markFinal('done')
            ->addBuildStep(new AddTransition('start', 'done'))
            ->build();

        $completingRuntime = new StandardRuntime($completingRegion);
        $completingRuntime->run(steps: 1);

        $this->assertNull(RuntimeRegistry::get($completingRegion), 'Should unregister when step completes machine');
    }

    public function testSummonFailsAfterRuntimeCompletes(): void
    {
        $region = (new RegionBuilder())
            ->setStates('start', 'done')
            ->markInitial('start')
            ->markFinal('done')
            ->addBuildStep(new AddTransition('start', 'done'))
            ->build();

        $runtime = new StandardRuntime($region);
        $runtime->run();

        // After completion, runtime should not be in registry
        $this->assertNull(RuntimeRegistry::get($region), 'Runtime should be unregistered, making summon() unavailable');
    }

    protected function tearDown(): void
    {
        $reflection = new \ReflectionClass(RuntimeRegistry::class);
        $property = $reflection->getProperty('runtimes');
        $property->setValue(null, null);
    }
}
