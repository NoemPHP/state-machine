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
 * Acceptance Criterion: Runtime.run() unregisters region from RuntimeRegistry on exception
 */
#[Group('runtime')]
#[Group('runtime-execution')]
class RuntimeRunUnregistersOnExceptionTest extends TestCase
{
    public function testRuntimeUnregistersWhenExceptionThrown(): void
    {
        $region = (new RegionBuilder())
            ->setStates('start', 'done')
            ->markInitial('start')
            ->markFinal('done')
            ->onAction('start', function (object $t) {
                throw new \RuntimeException('Test exception');
            })
            ->build();

        $runtime = new StandardRuntime($region);

        try {
            $runtime->run();
            $this->fail('Exception should have been thrown');
        } catch (\RuntimeException $e) {
            // Expected
        }

        $this->assertNull(RuntimeRegistry::get($region), 'Runtime should be unregistered even when exception thrown');
    }

    public function testUnregistersOnMaxIterationsException(): void
    {
        $region = (new RegionBuilder())
            ->setStates('infinite', 'done')
            ->markInitial('infinite')
            ->markFinal('done')
            ->addBuildStep(new AddTransition('infinite', 'infinite')) // Never reaches final state
            ->build();

        $runtime = new StandardRuntime($region, new \Noem\State\RuntimeConfig(maxIterations: 5));

        try {
            $runtime->run();
            $this->fail('Should throw RuntimeException for max iterations');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('maximum iterations', $e->getMessage());
        }

        $this->assertNull(RuntimeRegistry::get($region), 'Should unregister on maxIterations exception');
    }

    public function testUnregistersOnCustomException(): void
    {
        $region = (new RegionBuilder())
            ->setStates('start')
            ->markInitial('start')
            ->onAction('start', function (object $t) {
                throw new \LogicException('Custom error');
            })
            ->build();

        $runtime = new StandardRuntime($region);

        try {
            $runtime->run();
        } catch (\LogicException $e) {
            // Expected
        }

        $this->assertNull(RuntimeRegistry::get($region), 'Should unregister on any exception type');
    }

    protected function tearDown(): void
    {
        $reflection = new \ReflectionClass(RuntimeRegistry::class);
        $property = $reflection->getProperty('runtimes');
        $property->setValue(null, null);
    }
}
