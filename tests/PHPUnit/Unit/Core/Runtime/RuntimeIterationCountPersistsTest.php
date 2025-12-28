<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Core\Runtime;

use Noem\State\Region;
use Noem\State\RegionBuilder;
use Noem\State\Feature\Transitions\AddTransition;
use Noem\State\RuntimeConfig;
use Noem\State\StandardRuntime;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Runtime tracks iteration count across multiple run() calls
 */
#[Group('runtime')]
#[Group('runtime-event-loop')]
class RuntimeIterationCountPersistsTest extends TestCase
{
    public function testIterationCountIncrementsAcrossMultipleRunCalls(): void
    {
        $receivedIterations = [];

        $callback = function (Region $region, object $trigger, int $iteration) use (&$receivedIterations): void {
            $receivedIterations[] = $iteration;
        };

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

        $runtime = new StandardRuntime($region, new RuntimeConfig(onIteration: $callback));

        // Run 2 steps
        $runtime->run(steps: 2);
        $this->assertEquals([0, 1], $receivedIterations);

        // Run 2 more steps
        $receivedIterations = [];
        $runtime->run(steps: 2);
        $this->assertEquals([2, 3], $receivedIterations, 'Iteration count should continue from previous run');

        // Run final step
        $receivedIterations = [];
        $runtime->run(steps: 1);
        $this->assertEquals([4], $receivedIterations);
    }

    public function testMaxIterationsAppliesToTotalIterationsNotPerCall(): void
    {
        $region = (new RegionBuilder())
            ->setStates('infinite', 'done')
            ->markInitial('infinite')
            ->markFinal('done')
            ->addBuildStep(new AddTransition('infinite', 'infinite'))
            ->build();

        $runtime = new StandardRuntime($region, new RuntimeConfig(maxIterations: 5));

        // Run 3 steps
        $runtime->run(steps: 3);

        // Run 2 more steps (total = 5, should not throw yet)
        $runtime->run(steps: 2);

        // Next step should throw
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('maximum iterations');

        $runtime->run(steps: 1);
    }

    public function testIterationCountResetsNotBetweenCalls(): void
    {
        $iterationHistory = [];

        $factory = function (int $iteration, Region $region) use (&$iterationHistory): object {
            $iterationHistory[] = $iteration;
            return (object)['iteration' => $iteration];
        };

        $region = (new RegionBuilder())
            ->setStates('counting', 'done')
            ->markInitial('counting')
            ->markFinal('done')
            ->addBuildStep(new AddTransition('counting', 'done', function (object $t): bool {
                static $count = 0;
                $count++;
                return $count >= 6;
            }))
            ->build();

        $runtime = new StandardRuntime($region, new RuntimeConfig(triggerFactory: $factory));

        $runtime->run(steps: 2);
        $runtime->run(steps: 2);
        $runtime->run(steps: 2);

        // Should be continuous sequence, not reset between calls
        $this->assertEquals([0, 1, 2, 3, 4, 5], $iterationHistory, 'Iteration count should not reset between run() calls');
    }

    public function testCompletionReachedConsideringTotalIterations(): void
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

        $runtime = new StandardRuntime($region);

        $runtime->run(steps: 1);
        $this->assertFalse($region->isFinal(), 'Not complete after 1 iteration');

        $runtime->run(steps: 1);
        $this->assertFalse($region->isFinal(), 'Not complete after 2 iterations');

        $result = $runtime->run(steps: 1);
        $this->assertTrue($region->isFinal(), 'Should complete on 3rd iteration');
        $this->assertFalse($result, 'run() should return false on completion');
    }
}
