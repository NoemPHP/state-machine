<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Core\Runtime;

use Noem\State\RegionBuilder;
use Noem\State\Feature\Transitions\AddTransition;
use Noem\State\StandardRuntime;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Runtime stops when region reaches final state
 */
#[Group('runtime')]
#[Group('runtime-event-loop')]
class RuntimeStopsAtFinalStateTest extends TestCase
{
    public function testRuntimeStopsWhenFinalStateReached(): void
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
        $runtime->run();

        $this->assertEquals(1, $iterationCount, 'Should stop after reaching final state');
        $this->assertTrue($region->isFinal(), 'Region should be in final state');
    }

    public function testNoAdditionalIterationsAfterFinalState(): void
    {
        $actionCallCount = 0;

        $region = (new RegionBuilder())
            ->setStates('start', 'done')
            ->markInitial('start')
            ->markFinal('done')
            ->addBuildStep(new AddTransition('start', 'done'))
            ->onAction('start', function (object $t) use (&$actionCallCount): void {
                $actionCallCount++;
            })
            ->onAction('done', function (object $t) use (&$actionCallCount): void {
                $actionCallCount++;
            })
            ->build();

        $runtime = new StandardRuntime($region);
        $runtime->run();

        $this->assertEquals(1, $actionCallCount, 'Should not execute actions after final state reached');
    }

    public function testStopsAfterFinalStateTransition(): void
    {
        $executionSequence = [];

        $region = (new RegionBuilder())
            ->setStates('start', 'middle', 'done')
            ->markInitial('start')
            ->markFinal('done')
            ->addBuildStep(new AddTransition('start', 'middle'))
            ->addBuildStep(new AddTransition('middle', 'done'))
            ->onAction('start', function (object $t) use (&$executionSequence): void {
                $executionSequence[] = 'start';
            })
            ->onAction('middle', function (object $t) use (&$executionSequence): void {
                $executionSequence[] = 'middle';
            })
            ->build();

        $runtime = new StandardRuntime($region);
        $runtime->run();

        $this->assertEquals(['start', 'middle'], $executionSequence, 'Should stop immediately after transitioning to final state');
    }

    public function testRunReturnsFalseAfterReachingFinalState(): void
    {
        $region = (new RegionBuilder())
            ->setStates('start', 'done')
            ->markInitial('start')
            ->markFinal('done')
            ->addBuildStep(new AddTransition('start', 'done'))
            ->build();

        $runtime = new StandardRuntime($region);

        $result = $runtime->run();

        $this->assertFalse($result, 'run() should return false when final state reached');
    }

    public function testCompletionDetectedOnWasFinalAndNowFinal(): void
    {
        $iterationCount = 0;

        $region = (new RegionBuilder())
            ->setStates('done')
            ->markInitial('done')
            ->markFinal('done')
            ->onAction('done', function (object $t) use (&$iterationCount): void {
                $iterationCount++;
            })
            ->build();

        $runtime = new StandardRuntime($region);
        $runtime->run();

        // Should execute final state's action once, then stop because wasFinal && nowFinal
        $this->assertEquals(1, $iterationCount, 'Should execute final state action once, then detect completion');
    }
}
