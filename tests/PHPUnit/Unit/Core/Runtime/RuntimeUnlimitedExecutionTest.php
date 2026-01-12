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
 * Acceptance Criterion: Runtime skips maxIterations check when set to 0 or -1
 */
#[Group('runtime')]
#[Group('runtime-event-loop')]
class RuntimeUnlimitedExecutionTest extends TestCase
{
    public function testZeroMaxIterationsSkipsCheck(): void
    {
        $iterations = 0;

        $region = (new RegionBuilder())
            ->setStates('counting', 'done')
            ->markInitial('counting')
            ->markFinal('done')
            ->addBuildStep(new AddTransition('counting', 'done', function (object $t) use (&$iterations): bool {
                $iterations++;
                // Transition to done after 1000 iterations (far beyond normal default limit)
                return $iterations >= 1000;
            }))
            ->build();

        $runtime = new StandardRuntime($region, new RuntimeConfig(maxIterations: 0));

        // Should not throw exception even after 1000 iterations
        $result = $runtime->run();

        $this->assertFalse($result, 'Should complete normally with unlimited iterations');
        $this->assertEquals(1000, $iterations, 'Should execute all iterations until final state');
    }

    public function testNegativeOneMaxIterationsSkipsCheck(): void
    {
        $iterations = 0;

        $region = (new RegionBuilder())
            ->setStates('counting', 'done')
            ->markInitial('counting')
            ->markFinal('done')
            ->addBuildStep(new AddTransition('counting', 'done', function (object $t) use (&$iterations): bool {
                $iterations++;
                // Transition to done after 1000 iterations (far beyond normal default limit)
                return $iterations >= 1000;
            }))
            ->build();

        $runtime = new StandardRuntime($region, new RuntimeConfig(maxIterations: -1));

        // Should not throw exception even after 1000 iterations
        $result = $runtime->run();

        $this->assertFalse($result, 'Should complete normally with unlimited iterations');
        $this->assertEquals(1000, $iterations, 'Should execute all iterations until final state');
    }

    public function testUnlimitedIterationsStillStopsAtFinalState(): void
    {
        $iterations = 0;

        $region = (new RegionBuilder())
            ->setStates('running', 'done')
            ->markInitial('running')
            ->markFinal('done')
            ->addBuildStep(new AddTransition('running', 'done', function (object $t) use (&$iterations): bool {
                $iterations++;
                return $iterations >= 5;
            }))
            ->build();

        $runtime = new StandardRuntime($region, new RuntimeConfig(maxIterations: 0));

        $result = $runtime->run();

        $this->assertFalse($result, 'Should complete when final state reached');
        $this->assertEquals(5, $iterations, 'Should stop exactly at final state');
        $this->assertTrue($runtime->isComplete(), 'Runtime should be complete');
    }

    public function testUnlimitedIterationsWorksWithNonBlockingMode(): void
    {
        $iterations = 0;

        $region = (new RegionBuilder())
            ->setStates('counting', 'done')
            ->markInitial('counting')
            ->markFinal('done')
            ->addBuildStep(new AddTransition('counting', 'done', function (object $t) use (&$iterations): bool {
                $iterations++;
                return $iterations >= 500;
            }))
            ->build();

        $runtime = new StandardRuntime($region, new RuntimeConfig(maxIterations: -1));

        // Execute 100 steps at a time (would exceed normal limits)
        for ($i = 0; $i < 10; $i++) {
            $hasMore = $runtime->run(steps: 100);
            if (!$hasMore) {
                break;
            }
        }

        $this->assertFalse($runtime->run(steps: 1), 'Should be complete');
        $this->assertEquals(500, $iterations, 'Should execute all iterations in non-blocking mode');
    }

    public function testPositiveMaxIterationsStillEnforced(): void
    {
        $region = (new RegionBuilder())
            ->setStates('infinite', 'done')
            ->markInitial('infinite')
            ->markFinal('done')
            ->addBuildStep(new AddTransition('infinite', 'infinite')) // Never reaches final
            ->build();

        $runtime = new StandardRuntime($region, new RuntimeConfig(maxIterations: 10));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('maximum iterations');

        // Should still throw with positive maxIterations
        $runtime->run();
    }

    public function testZeroIterationsDoesNotPreventInitialStateEntry(): void
    {
        $enterCalled = false;

        $region = (new RegionBuilder())
            ->setStates('initial', 'final')
            ->markInitial('initial')
            ->markFinal('final')
            ->onEnter('initial', function (object $t) use (&$enterCalled) {
                $enterCalled = true;
            })
            ->addBuildStep(new AddTransition('initial', 'final'))
            ->build();

        $runtime = new StandardRuntime($region, new RuntimeConfig(maxIterations: 0));

        $runtime->run();

        $this->assertTrue($enterCalled, 'Initial state should be entered even with maxIterations=0');
    }
}
