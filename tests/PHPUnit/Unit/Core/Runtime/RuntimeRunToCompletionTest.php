<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Core\Runtime;

use Noem\State\Feature\Transitions\AddTransition;
use Noem\State\Region;
use Noem\State\Runtime;
use Noem\State\RuntimeConfig;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Runtime.run() with no arguments runs region to completion
 */
#[Group('runtime')]
#[Group('runtime-execution')]
class RuntimeRunToCompletionTest extends TestCase
{
    public function testRunWithNoArgumentsExecutesUntilFinalState(): void
    {
        $iterationCount = 0;

        $region = (new RegionBuilder())
            ->setStates('start', 'processing', 'done')
            ->markInitial('start')
            ->markFinal('done')
            ->addBuildStep(new AddTransition('start', 'processing'))
            ->addBuildStep(new AddTransition('processing', 'done'))
            ->onAction('start', function (object $t) use (&$iterationCount): void {
                $iterationCount++;
            })
            ->onAction('processing', function (object $t) use (&$iterationCount): void {
                $iterationCount++;
            })
            ->build();

        $runtime = new \Noem\State\StandardRuntime($region);

        $result = $runtime->run();

        $this->assertFalse($result, 'run() should return false when complete');
        $this->assertTrue($region->isFinal(), 'Region should be in final state');
        $this->assertGreaterThan(0, $iterationCount, 'Should have executed iterations');
    }

    public function testRunBlocksUntilCompletion(): void
    {
        $region = (new RegionBuilder())
            ->setStates('start', 'done')
            ->markInitial('start')
            ->markFinal('done')
            ->addBuildStep(new AddTransition('start', 'done'))
            ->build();

        $runtime = new \Noem\State\StandardRuntime($region);

        $beforeRun = $region->isFinal();
        $runtime->run();
        $afterRun = $region->isFinal();

        $this->assertFalse($beforeRun, 'Region should not be final before run');
        $this->assertTrue($afterRun, 'Region should be final after run completes');
    }

    public function testRunWithDefaultStepsParameterRunsToCompletion(): void
    {
        $region = (new RegionBuilder())
            ->setStates('start', 'done')
            ->markInitial('start')
            ->markFinal('done')
            ->addBuildStep(new AddTransition('start', 'done'))
            ->build();

        $runtime = new \Noem\State\StandardRuntime($region);

        // run() and run(0) should be equivalent
        $result = $runtime->run(0);

        $this->assertFalse($result);
        $this->assertTrue($region->isFinal());
    }
}
