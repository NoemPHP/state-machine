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
 * Acceptance Criterion: Runtime throws exception when maxIterations exceeded without reaching final state
 */
#[Group('runtime')]
#[Group('runtime-event-loop')]
class RuntimeMaxIterationsExceptionTest extends TestCase
{
    public function testThrowsExceptionWhenMaxIterationsExceeded(): void
    {
        $region = (new RegionBuilder())
            ->setStates('infinite', 'done')
            ->markInitial('infinite')
            ->markFinal('done')
            ->addBuildStep(new AddTransition('infinite', 'infinite')) // Never reaches final state
            ->build();

        $runtime = new StandardRuntime($region, new RuntimeConfig(maxIterations: 10));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('maximum iterations');

        $runtime->run();
    }

    public function testExceptionIncludesIterationCount(): void
    {
        $region = (new RegionBuilder())
            ->setStates('loop', 'done')
            ->markInitial('loop')
            ->markFinal('done')
            ->addBuildStep(new AddTransition('loop', 'loop'))
            ->build();

        $runtime = new StandardRuntime($region, new RuntimeConfig(maxIterations: 5));

        try {
            $runtime->run();
            $this->fail('Should have thrown RuntimeException');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('5', $e->getMessage(), 'Exception message should include iteration count');
        }
    }

    public function testNoExceptionWhenFinalStateReachedBeforeMaxIterations(): void
    {
        $region = (new RegionBuilder())
            ->setStates('counting', 'done')
            ->markInitial('counting')
            ->markFinal('done')
            ->addBuildStep(new AddTransition('counting', 'done', function (object $t): bool {
                static $count = 0;
                $count++;
                return $count >= 3;
            }))
            ->build();

        $runtime = new StandardRuntime($region, new RuntimeConfig(maxIterations: 100));

        // Should not throw
        $result = $runtime->run();

        $this->assertFalse($result, 'Should complete normally when final state reached');
    }

    public function testExceptionOnlyThrownWhenNotInFinalState(): void
    {
        // Region that transitions to final exactly at max iterations
        $region = (new RegionBuilder())
            ->setStates('counting', 'done')
            ->markInitial('counting')
            ->markFinal('done')
            ->addBuildStep(new AddTransition('counting', 'done', function (object $t): bool {
                static $count = 0;
                $count++;
                return $count >= 5;
            }))
            ->build();

        $runtime = new StandardRuntime($region, new RuntimeConfig(maxIterations: 5));

        // Should not throw because it reaches final state
        $result = $runtime->run();

        $this->assertFalse($result, 'Should complete without exception when final state reached at max iterations');
    }

    public function testExceptionThrownWithNonBlockingExecution(): void
    {
        $region = (new RegionBuilder())
            ->setStates('infinite', 'done')
            ->markInitial('infinite')
            ->markFinal('done')
            ->addBuildStep(new AddTransition('infinite', 'infinite'))
            ->build();

        $runtime = new StandardRuntime($region, new RuntimeConfig(maxIterations: 3));

        // Execute step by step
        $runtime->run(steps: 1);
        $runtime->run(steps: 1);
        $runtime->run(steps: 1);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('maximum iterations');

        // This should throw because we've hit max iterations
        $runtime->run(steps: 1);
    }
}
