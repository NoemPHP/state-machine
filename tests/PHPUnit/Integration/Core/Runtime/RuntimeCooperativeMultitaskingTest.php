<?php

declare(strict_types=1);

namespace Noem\State\Tests\Integration\Core\Runtime;

use Noem\State\Feature\Transitions\AddTransition;
use Noem\State\RegionBuilder;
use Noem\State\StandardRuntime;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Runtime supports cooperative multitasking with multiple regions
 */
#[Group('runtime')]
#[Group('integration')]
class RuntimeCooperativeMultitaskingTest extends TestCase
{
    public function testMultipleRuntimesExecuteCooperatively(): void
    {
        $execution = [];

        // Create first region
        $region1 = (new RegionBuilder())
            ->setStates('r1_start', 'r1_middle', 'r1_done')
            ->markInitial('r1_start')
            ->markFinal('r1_done')
            ->addBuildStep(new AddTransition('r1_start', 'r1_middle'))
            ->addBuildStep(new AddTransition('r1_middle', 'r1_done'))
            ->onAction('r1_start', function (object $t) use (&$execution): void {
                $execution[] = 'r1_start';
            })
            ->onAction('r1_middle', function (object $t) use (&$execution): void {
                $execution[] = 'r1_middle';
            })
            ->build();

        // Create second region
        $region2 = (new RegionBuilder())
            ->setStates('r2_start', 'r2_middle', 'r2_done')
            ->markInitial('r2_start')
            ->markFinal('r2_done')
            ->addBuildStep(new AddTransition('r2_start', 'r2_middle'))
            ->addBuildStep(new AddTransition('r2_middle', 'r2_done'))
            ->onAction('r2_start', function (object $t) use (&$execution): void {
                $execution[] = 'r2_start';
            })
            ->onAction('r2_middle', function (object $t) use (&$execution): void {
                $execution[] = 'r2_middle';
            })
            ->build();

        $runtime1 = new StandardRuntime($region1);
        $runtime2 = new StandardRuntime($region2);

        // Execute cooperatively in round-robin fashion
        while (!$runtime1->isComplete() || !$runtime2->isComplete()) {
            if (!$runtime1->isComplete()) {
                $runtime1->run(steps: 1);
            }
            if (!$runtime2->isComplete()) {
                $runtime2->run(steps: 1);
            }
        }

        // Verify interleaved execution
        $this->assertCount(4, $execution, 'Should execute all steps from both regions');
        $this->assertEquals('r1_start', $execution[0]);
        $this->assertEquals('r2_start', $execution[1]);
        $this->assertEquals('r1_middle', $execution[2]);
        $this->assertEquals('r2_middle', $execution[3]);
    }

    public function testRoundRobinExecutionMaintainsIndependentState(): void
    {
        $region1State = [];
        $region2State = [];

        $region1 = (new RegionBuilder())
            ->setStates('counting', 'done')
            ->markInitial('counting')
            ->markFinal('done')
            ->addBuildStep(new AddTransition('counting', 'done', function (object $t) use (&$region1State): bool {
                static $count = 0;
                $count++;
                $region1State[] = $count;
                return $count >= 3;
            }))
            ->build();

        $region2 = (new RegionBuilder())
            ->setStates('counting', 'done')
            ->markInitial('counting')
            ->markFinal('done')
            ->addBuildStep(new AddTransition('counting', 'done', function (object $t) use (&$region2State): bool {
                static $count = 0;
                $count++;
                $region2State[] = $count;
                return $count >= 5;
            }))
            ->build();

        $runtime1 = new StandardRuntime($region1);
        $runtime2 = new StandardRuntime($region2);

        // Execute cooperatively
        while (!$runtime1->isComplete() || !$runtime2->isComplete()) {
            if (!$runtime1->isComplete()) {
                $runtime1->run(steps: 1);
            }
            if (!$runtime2->isComplete()) {
                $runtime2->run(steps: 1);
            }
        }

        $this->assertEquals([1, 2, 3], $region1State, 'Region 1 should maintain independent counter');
        $this->assertEquals([1, 2, 3, 4, 5], $region2State, 'Region 2 should maintain independent counter');
    }

    public function testCooperativeExecutionWithDifferentCompletionTimes(): void
    {
        $completionOrder = [];

        $fastRegion = (new RegionBuilder())
            ->setStates('fast', 'done')
            ->markInitial('fast')
            ->markFinal('done')
            ->addBuildStep(new AddTransition('fast', 'done'))
            ->onAction('fast', function (object $t) use (&$completionOrder): void {
                $completionOrder[] = 'fast_complete';
                // Transition handled by AddTransition
            })
            ->build();

        $slowRegion = (new RegionBuilder())
            ->setStates('slow', 'middle', 'done')
            ->markInitial('slow')
            ->markFinal('done')
            ->addBuildStep(new AddTransition('slow', 'middle'))
            ->addBuildStep(new AddTransition('middle', 'done'))
            ->onAction('middle', function (object $t) use (&$completionOrder): void {
                $completionOrder[] = 'slow_complete';
                // Transition handled by AddTransition
            })
            ->build();

        $fastRuntime = new StandardRuntime($fastRegion);
        $slowRuntime = new StandardRuntime($slowRegion);

        // Execute cooperatively
        while (!$fastRuntime->isComplete() || !$slowRuntime->isComplete()) {
            if (!$fastRuntime->isComplete()) {
                $fastRuntime->run(steps: 1);
            }
            if (!$slowRuntime->isComplete()) {
                $slowRuntime->run(steps: 1);
            }
        }

        $this->assertEquals(['fast_complete', 'slow_complete'], $completionOrder);
        $this->assertTrue($fastRuntime->isComplete());
        $this->assertTrue($slowRuntime->isComplete());
    }

    public function testEventStreamingFromMultipleRuntimes(): void
    {
        $region1 = (new RegionBuilder())
            ->setStates('r1', 'processing1', 'done')
            ->markInitial('r1')
            ->markFinal('done')
            ->onEnter('r1', function (object $t) use (&$region1) {
                $region1->trigger((object)['source' => 'region1'], enqueue: true);
            })
            ->addBuildStep(new AddTransition('r1', 'processing1'))
            ->addBuildStep(new AddTransition('processing1', 'done'))
            ->build();

        $region2 = (new RegionBuilder())
            ->setStates('r2', 'processing2', 'done')
            ->markInitial('r2')
            ->markFinal('done')
            ->onEnter('r2', function (object $t) use (&$region2) {
                $region2->trigger((object)['source' => 'region2'], enqueue: true);
            })
            ->addBuildStep(new AddTransition('r2', 'processing2'))
            ->addBuildStep(new AddTransition('processing2', 'done'))
            ->build();

        $runtime1 = new StandardRuntime($region1);
        $runtime2 = new StandardRuntime($region2);

        $events1 = [];
        $events2 = [];

        // Consume events from both runtimes
        foreach ($runtime1->events() as $event) {
            if (isset($event->source)) {
                $events1[] = $event;
            }
        }

        foreach ($runtime2->events() as $event) {
            if (isset($event->source)) {
                $events2[] = $event;
            }
        }

        $this->assertCount(1, $events1);
        $this->assertEquals('region1', $events1[0]->source);
        $this->assertCount(1, $events2);
        $this->assertEquals('region2', $events2[0]->source);
    }

    public function testCooperativeExecutionWithSharedResources(): void
    {
        $sharedResource = ['value' => 0];

        $incrementer = (new RegionBuilder())
            ->setStates('incrementing', 'done')
            ->markInitial('incrementing')
            ->markFinal('done')
            ->addBuildStep(new AddTransition('incrementing', 'done', function (object $t) use (&$sharedResource): bool {
                static $count = 0;
                $sharedResource['value']++;
                $count++;
                return $count >= 3;
            }))
            ->build();

        $multiplier = (new RegionBuilder())
            ->setStates('multiplying', 'done')
            ->markInitial('multiplying')
            ->markFinal('done')
            ->addBuildStep(new AddTransition('multiplying', 'done', function (object $t) use (&$sharedResource): bool {
                static $count = 0;
                $sharedResource['value'] *= 2;
                $count++;
                return $count >= 2;
            }))
            ->build();

        $runtime1 = new StandardRuntime($incrementer);
        $runtime2 = new StandardRuntime($multiplier);

        // Execute cooperatively
        while (!$runtime1->isComplete() || !$runtime2->isComplete()) {
            if (!$runtime1->isComplete()) {
                $runtime1->run(steps: 1);
            }
            if (!$runtime2->isComplete()) {
                $runtime2->run(steps: 1);
            }
        }

        // After interleaved execution: +1, *2, +1, *2, +1 = ((((0+1)*2)+1)*2)+1 = 7
        $this->assertEquals(7, $sharedResource['value'], 'Shared resource should be modified by both runtimes');
    }
}
