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
 * Acceptance Criterion: Runtime.run() registers region in RuntimeRegistry before execution
 */
#[Group('runtime')]
#[Group('runtime-execution')]
class RuntimeRunRegistersInRegistryTest extends TestCase
{
    public function testRuntimeRegistersItselfBeforeExecution(): void
    {
        $registeredDuringExecution = false;

        $region = (new RegionBuilder())
            ->setStates('start', 'done')
            ->markInitial('start')
            ->markFinal('done')
            ->addBuildStep(new AddTransition('start', 'done'))
            ->onAction('start', function (object $t) use (&$registeredDuringExecution, &$region): void {
                // Check if runtime is registered during execution
                $registeredDuringExecution = RuntimeRegistry::get($region) !== null;
            })
            ->build();

        $runtime = new StandardRuntime($region);

        // Not registered before run
        $this->assertNull(RuntimeRegistry::get($region), 'Should not be registered before run()');

        $runtime->run();

        $this->assertTrue($registeredDuringExecution, 'Runtime should be registered during execution');
    }

    public function testRuntimeIsAccessibleViaSummonDuringExecution(): void
    {
        $runtimeFromRegistry = null;

        $region = (new RegionBuilder())
            ->setStates('start', 'done')
            ->markInitial('start')
            ->markFinal('done')
            ->addBuildStep(new AddTransition('start', 'done'))
            ->onAction('start', function (object $t) use (&$runtimeFromRegistry, &$region): void {
                $runtimeFromRegistry = RuntimeRegistry::get($region);
            })
            ->build();

        $runtime = new StandardRuntime($region);
        $runtime->run();

        $this->assertSame($runtime, $runtimeFromRegistry, 'Registry should return the same runtime instance');
    }

    public function testRegistrationHappensBeforeFirstIteration(): void
    {
        $checkOrder = [];

        $region = (new RegionBuilder())
            ->setStates('start', 'done')
            ->markInitial('start')
            ->markFinal('done')
            ->addBuildStep(new AddTransition('start', 'done'))
            ->onAction('start', function (object $t) use (&$checkOrder, &$region): void {
                $checkOrder[] = 'action_executed';
                $checkOrder[] = RuntimeRegistry::get($region) ? 'registered' : 'not_registered';
            })
            ->build();

        $runtime = new StandardRuntime($region);
        $runtime->run();

        $this->assertEquals(['action_executed', 'registered'], $checkOrder, 'Registration must happen before first iteration');
    }

    protected function tearDown(): void
    {
        $reflection = new \ReflectionClass(RuntimeRegistry::class);
        $property = $reflection->getProperty('runtimes');
        $property->setValue(null, null);
    }
}
