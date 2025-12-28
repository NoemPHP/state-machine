<?php

declare(strict_types=1);

namespace Noem\State\Tests\Integration\Core\Runtime;

use Noem\State\RegionBuilder;
use Noem\State\Feature\Transitions\AddTransition;
use Noem\State\RuntimeConfig;
use Noem\State\StandardRuntime;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Runtime executes region from initial state to final state
 */
#[Group('runtime')]
#[Group('integration')]
class RuntimeFullExecutionTest extends TestCase
{
    public function testCompleteExecutionFlowFromInitialToFinal(): void
    {
        $executionLog = [];

        $region = (new RegionBuilder())
            ->setStates('idle', 'processing', 'validating', 'done')
            ->markInitial('idle')
            ->markFinal('done')
            ->addBuildStep(new AddTransition('idle', 'processing'))
            ->addBuildStep(new AddTransition('processing', 'validating'))
            ->addBuildStep(new AddTransition('validating', 'done'))
            ->onEnter('idle', function (object $t) use (&$executionLog) {
                $executionLog[] = 'enter_idle';
            })
            ->onAction('idle', function (object $t) use (&$executionLog): void {
                $executionLog[] = 'action_idle';
            })
            ->onEnter('processing', function (object $t) use (&$executionLog) {
                $executionLog[] = 'enter_processing';
            })
            ->onAction('processing', function (object $t) use (&$executionLog): void {
                $executionLog[] = 'action_processing';
            })
            ->onEnter('validating', function (object $t) use (&$executionLog) {
                $executionLog[] = 'enter_validating';
            })
            ->onAction('validating', function (object $t) use (&$executionLog): void {
                $executionLog[] = 'action_validating';
            })
            ->onEnter('done', function (object $t) use (&$executionLog) {
                $executionLog[] = 'enter_done';
            })
            ->build();

        $runtime = new StandardRuntime($region);

        $runtime->run();

        $expectedFlow = [
            'enter_idle',
            'action_idle',
            'enter_processing',
            'action_processing',
            'enter_validating',
            'action_validating',
            'enter_done',
        ];

        $this->assertEquals($expectedFlow, $executionLog, 'Should execute complete flow with all lifecycle callbacks');
        $this->assertTrue($runtime->isComplete());
        $this->assertEquals('done', $region->currentState());
    }

    public function testIterationManagementWithCallbacks(): void
    {
        $iterations = [];
        $completionFired = false;

        $onIteration = function ($region, $trigger, $iteration) use (&$iterations) {
            $iterations[] = $iteration;
        };

        $onComplete = function () use (&$completionFired) {
            $completionFired = true;
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

        $runtime = new StandardRuntime(
            $region,
            new RuntimeConfig(
                maxIterations: 100,
                onIteration: $onIteration,
                onComplete: $onComplete
            )
        );

        $runtime->run();

        $this->assertEquals([0, 1, 2, 3, 4], $iterations, 'Should call onIteration for each iteration');
        $this->assertTrue($completionFired, 'Should call onComplete when done');
        $this->assertTrue($runtime->isComplete());
    }

    public function testCompletionDetectionAndReturnValue(): void
    {
        $region = (new RegionBuilder())
            ->setStates('start', 'middle', 'done')
            ->markInitial('start')
            ->markFinal('done')
            ->addBuildStep(new AddTransition('start', 'middle'))
            ->addBuildStep(new AddTransition('middle', 'done'))
            ->build();

        $runtime = new StandardRuntime($region);

        $this->assertFalse($runtime->isComplete(), 'Should not be complete before run');

        $result = $runtime->run();

        $this->assertFalse($result, 'run() should return false when complete');
        $this->assertTrue($runtime->isComplete(), 'Should be complete after run');
        $this->assertEquals('done', $region->currentState());
    }

    public function testTriggerGenerationThroughFullExecution(): void
    {
        $receivedTriggers = [];

        $triggerFactory = function ($iteration, $region) use (&$receivedTriggers) {
            $trigger = (object)['iteration' => $iteration, 'custom' => true];
            $receivedTriggers[] = $trigger;
            return $trigger;
        };

        $region = (new RegionBuilder())
            ->setStates('processing', 'done')
            ->markInitial('processing')
            ->markFinal('done')
            ->addBuildStep(new AddTransition('processing', 'done', function (object $t): bool {
                static $count = 0;
                $count++;
                return $count >= 3;
            }))
            ->build();

        $runtime = new StandardRuntime(
            $region,
            new RuntimeConfig(triggerFactory: $triggerFactory)
        );

        $runtime->run();

        $this->assertCount(3, $receivedTriggers, 'Should generate trigger for each iteration');
        $this->assertEquals(0, $receivedTriggers[0]->iteration);
        $this->assertEquals(1, $receivedTriggers[1]->iteration);
        $this->assertEquals(2, $receivedTriggers[2]->iteration);
    }
}
