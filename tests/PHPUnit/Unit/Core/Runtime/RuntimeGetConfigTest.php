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
 * Acceptance Criterion: Runtime.getConfig() returns runtime configuration
 */
#[Group('runtime')]
#[Group('runtime-queries')]
class RuntimeGetConfigTest extends TestCase
{
    public function testGetConfigReturnsRuntimeConfiguration(): void
    {
        $config = new RuntimeConfig(maxIterations: 500);

        $region = (new RegionBuilder())
            ->setStates('start')
            ->markInitial('start')
            ->build();

        $runtime = new StandardRuntime($region, $config);

        $retrievedConfig = $runtime->getConfig();

        $this->assertSame($config, $retrievedConfig, 'getConfig() should return the runtime configuration');
    }

    public function testGetConfigReturnsDefaultConfigWhenNoneProvided(): void
    {
        $region = (new RegionBuilder())
            ->setStates('start')
            ->markInitial('start')
            ->build();

        $runtime = new StandardRuntime($region);

        $config = $runtime->getConfig();

        $this->assertInstanceOf(RuntimeConfig::class, $config);
        $this->assertEquals(10000, $config->maxIterations, 'Should have default maxIterations');
    }

    public function testGetConfigAllowsInspectingMaxIterations(): void
    {
        $region = (new RegionBuilder())
            ->setStates('start')
            ->markInitial('start')
            ->build();

        $runtime = new StandardRuntime($region, new RuntimeConfig(maxIterations: 1234));

        $config = $runtime->getConfig();

        $this->assertEquals(1234, $config->maxIterations);
    }

    public function testGetConfigAllowsInspectingTriggerFactory(): void
    {
        $factory = fn($i, $r) => (object)['custom' => true];

        $region = (new RegionBuilder())
            ->setStates('start')
            ->markInitial('start')
            ->build();

        $runtime = new StandardRuntime($region, new RuntimeConfig(triggerFactory: $factory));

        $config = $runtime->getConfig();

        $this->assertSame($factory, $config->triggerFactory);
    }

    public function testGetConfigAllowsInspectingCallbacks(): void
    {
        $onIteration = fn($r, $t, $i) => null;
        $onComplete = fn() => null;

        $region = (new RegionBuilder())
            ->setStates('start')
            ->markInitial('start')
            ->build();

        $runtime = new StandardRuntime(
            $region,
            new RuntimeConfig(
                onIteration: $onIteration,
                onComplete: $onComplete
            )
        );

        $config = $runtime->getConfig();

        $this->assertSame($onIteration, $config->onIteration);
        $this->assertSame($onComplete, $config->onComplete);
    }

    public function testGetConfigAvailableBeforeExecution(): void
    {
        $config = new RuntimeConfig(maxIterations: 999);

        $region = (new RegionBuilder())
            ->setStates('start', 'done')
            ->markInitial('start')
            ->markFinal('done')
            ->build();

        $runtime = new StandardRuntime($region, $config);

        $this->assertSame($config, $runtime->getConfig(), 'Config should be available before run()');
    }
}
