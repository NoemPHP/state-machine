<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Core\Runtime;

use Noem\State\RegionBuilder;
use Noem\State\Feature\Transitions\AddTransition;
use Noem\State\StandardRuntime;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Runtime.run() returns false when region is complete
 */
#[Group('runtime')]
#[Group('runtime-execution')]
class RuntimeRunReturnsFalseWhenCompleteTest extends TestCase
{
    public function testRunReturnsFalseWhenRegionReachesFinalState(): void
    {
        $region = (new RegionBuilder())
            ->setStates('start', 'done')
            ->markInitial('start')
            ->markFinal('done')
            ->addBuildStep(new AddTransition('start', 'done'))
            ->build();

        $runtime = new StandardRuntime($region);

        $result = $runtime->run();

        $this->assertFalse($result, 'run() should return false when region completes');
    }

    public function testRunReturnsFalseWhenAlreadyComplete(): void
    {
        $region = (new RegionBuilder())
            ->setStates('start', 'done')
            ->markInitial('start')
            ->markFinal('done')
            ->addBuildStep(new AddTransition('start', 'done'))
            ->build();

        $runtime = new StandardRuntime($region);

        // First run completes
        $runtime->run();
        $this->assertTrue($region->isFinal());

        // Second run should immediately return false
        $result = $runtime->run(steps: 1);

        $this->assertFalse($result, 'run() should return false when already complete');
    }

    public function testRunStepsReturnsFalseOnCompletingIteration(): void
    {
        $region = (new RegionBuilder())
            ->setStates('counting', 'done')
            ->markInitial('counting')
            ->markFinal('done')
            ->addBuildStep(new AddTransition('counting', 'done')) // Completes on first iteration
            ->build();

        $runtime = new StandardRuntime($region);

        $result = $runtime->run(steps: 1);

        $this->assertFalse($result, 'Should return false when step causes completion');
    }

    public function testWhileLoopTerminatesOnFalseReturn(): void
    {
        $region = (new RegionBuilder())
            ->setStates('counting', 'done')
            ->markInitial('counting')
            ->markFinal('done')
            ->addBuildStep(new AddTransition('counting', 'done'))
            ->build();

        $runtime = new StandardRuntime($region);

        $loopIterations = 0;
        while ($runtime->run(steps: 1)) {
            $loopIterations++;
            if ($loopIterations > 100) {
                $this->fail('Loop should terminate when run() returns false');
            }
        }

        $this->assertEquals(0, $loopIterations, 'Loop should not execute when run() returns false immediately');
    }
}
