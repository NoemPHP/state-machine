<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Core\Runtime;

use Noem\State\Feature\Transitions\AddTransition;
use Noem\State\RegionBuilder;
use Noem\State\StandardRuntime;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Runtime.run(steps) executes exactly N iterations and returns true if more available
 */
#[Group('runtime')]
#[Group('runtime-execution')]
class RuntimeRunStepsTest extends TestCase
{
    public function testRunWithStepsExecutesExactlyNIterations(): void
    {
        $iterationCount = 0;

        $region = (new RegionBuilder())
            ->setStates('counting', 'done')
            ->markInitial('counting')
            ->markFinal('done')
            ->addBuildStep(new AddTransition('counting', 'done', function (object $t) use (&$iterationCount): bool {
                $iterationCount++;
                return $iterationCount >= 10;
            }))
            ->build();

        $runtime = new StandardRuntime($region);

        // Run 3 steps
        $result = $runtime->run(steps: 3);

        $this->assertTrue($result, 'Should return true when more iterations available');
        $this->assertEquals(3, $iterationCount, 'Should execute exactly 3 iterations');
        $this->assertFalse($region->isFinal(), 'Region should not be final yet');
    }

    public function testRunStepsCanBeCalledMultipleTimes(): void
    {
        $iterationCount = 0;

        $region = (new RegionBuilder())
            ->setStates('counting', 'done')
            ->markInitial('counting')
            ->markFinal('done')
            ->addBuildStep(new AddTransition('counting', 'done', function (object $t) use (&$iterationCount): bool {
                $iterationCount++;
                return $iterationCount >= 5;
            }))
            ->build();

        $runtime = new StandardRuntime($region);

        $runtime->run(steps: 2);
        $this->assertEquals(2, $iterationCount);

        $runtime->run(steps: 2);
        $this->assertEquals(4, $iterationCount);

        $runtime->run(steps: 2);
        $this->assertEquals(5, $iterationCount); // Final state reached on 5th iteration
    }

    public function testRunStepsReturnsTrueWhenMoreWorkAvailable(): void
    {
        $region = (new RegionBuilder())
            ->setStates('start', 'done')
            ->markInitial('start')
            ->markFinal('done')
            // No transition defined - will never reach done
            ->build();

        $runtime = new StandardRuntime($region);

        $result = $runtime->run(steps: 1);

        $this->assertTrue($result, 'Should return true when region not complete');
    }

    public function testRunStepsStopsAtCompletionEvenIfMoreStepsRequested(): void
    {
        $iterationCount = 0;

        $region = (new RegionBuilder())
            ->setStates('start', 'done')
            ->markInitial('start')
            ->markFinal('done')
            ->addBuildStep(new AddTransition('start', 'done'))
            ->onAction('start', function (object $t) use (&$iterationCount): void {
                $iterationCount++;
            })
            ->build();

        $runtime = new StandardRuntime($region);

        // Request 10 steps but should stop after 1 (when final state reached)
        $result = $runtime->run(steps: 10);

        $this->assertFalse($result, 'Should return false when complete');
        $this->assertEquals(1, $iterationCount, 'Should stop at completion, not execute all requested steps');
    }
}
