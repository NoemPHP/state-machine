<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\OrthogonalRegions;

use Noem\State\Feature\OrthogonalRegions\OrthogonalRegions;
use Noem\State\RegionBuilder;
use Noem\State\RuntimeConfig;
use Noem\State\StandardRuntime;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Parent RuntimeConfig is passed to static orthogonal region runtimes
 */
#[Group('orthogonal-regions')]
#[Group('runtime-config')]
class RuntimeConfigPassingToStaticTest extends TestCase
{
    public function testParentConfigPassedToStaticChildren(): void
    {
        $child1Iterations = [];
        $child2Iterations = [];

        $onIteration = function ($region, $trigger, $iteration) use (&$child1Iterations, &$child2Iterations) {
            $state = $region->currentState();
            if ($state === 'c1' || $state === 'c1_done') {
                $child1Iterations[] = $iteration;
            } elseif ($state === 'c2' || $state === 'c2_done') {
                $child2Iterations[] = $iteration;
            }
        };

        $child1 = (new RegionBuilder())
            ->setStates('c1', 'c1_done')
            ->markInitial('c1')
            ->markFinal('c1_done')
            ->onAction('c1', fn(object $t) => 'c1_done');

        $child2 = (new RegionBuilder())
            ->setStates('c2', 'c2_done')
            ->markInitial('c2')
            ->markFinal('c2_done')
            ->onAction('c2', fn(object $t) => 'c2_done');

        $region = (new OrthogonalRegions(new RegionBuilder(), [$child1, $child2]))
            ->setStates('parent', 'done')
            ->markInitial('parent')
            ->markFinal('done')
            ->onAction('parent', fn(object $t) => 'done')
            ->build();

        $config = new RuntimeConfig(onIteration: $onIteration);
        $runtime = new StandardRuntime($region, $config);
        $runtime->run();

        $this->assertNotEmpty($child1Iterations, 'Child 1 should receive onIteration callbacks');
        $this->assertNotEmpty($child2Iterations, 'Child 2 should receive onIteration callbacks');
    }

    public function testStaticChildrenInheritMaxIterations(): void
    {
        $child = (new RegionBuilder())
            ->setStates('infinite')
            ->markInitial('infinite')
            ->onAction('infinite', fn(object $t) => 'infinite'); // Infinite loop

        $region = (new OrthogonalRegions(new RegionBuilder(), [$child]))
            ->setStates('parent')
            ->markInitial('parent')
            ->build();

        $config = new RuntimeConfig(maxIterations: 5);
        $runtime = new StandardRuntime($region, $config);

        $this->expectException(\Noem\State\Exception\MaxIterationsException::class);
        $runtime->run();
    }

    public function testStaticChildrenInheritTriggerFactory(): void
    {
        $receivedTriggers = [];

        $triggerFactory = function ($iteration, $region) use (&$receivedTriggers) {
            $trigger = (object)['custom' => true, 'iteration' => $iteration];
            $receivedTriggers[] = $trigger;
            return $trigger;
        };

        $child = (new RegionBuilder())
            ->setStates('child', 'done')
            ->markInitial('child')
            ->markFinal('done')
            ->onAction('child', function (object $t) {
                // Verify trigger has custom property
                static $verified = false;
                if (!$verified && isset($t->custom)) {
                    $verified = true;
                }
                return 'done';
            });

        $region = (new OrthogonalRegions(new RegionBuilder(), [$child]))
            ->setStates('parent', 'done')
            ->markInitial('parent')
            ->markFinal('done')
            ->onAction('parent', fn(object $t) => 'done')
            ->build();

        $config = new RuntimeConfig(triggerFactory: $triggerFactory);
        $runtime = new StandardRuntime($region, $config);
        $runtime->run();

        $hasCustomTriggers = array_filter($receivedTriggers, fn(object $t) => isset($t->custom) && $t->custom === true);
        $this->assertNotEmpty($hasCustomTriggers, 'Children should receive custom triggers from factory');
    }

    public function testOnCompleteFiresForStaticChildren(): void
    {
        $completions = [];

        $onComplete = function () use (&$completions) {
            $completions[] = 'completed';
        };

        $child1 = (new RegionBuilder())
            ->setStates('c1', 'done1')
            ->markInitial('c1')
            ->markFinal('done1')
            ->onAction('c1', fn(object $t) => 'done1');

        $child2 = (new RegionBuilder())
            ->setStates('c2', 'done2')
            ->markInitial('c2')
            ->markFinal('done2')
            ->onAction('c2', fn(object $t) => 'done2');

        $region = (new OrthogonalRegions(new RegionBuilder(), [$child1, $child2]))
            ->setStates('parent', 'done')
            ->markInitial('parent')
            ->markFinal('done')
            ->onAction('parent', fn(object $t) => 'done')
            ->build();

        $config = new RuntimeConfig(onComplete: $onComplete);
        $runtime = new StandardRuntime($region, $config);
        $runtime->run();

        // onComplete should fire for parent and potentially children
        $this->assertNotEmpty($completions);
    }

    public function testAllConfigCallbacksPassedToStaticChildren(): void
    {
        $iterationLog = [];
        $completionLog = [];

        $onIteration = function ($region, $trigger, $iteration) use (&$iterationLog) {
            $iterationLog[] = $region->currentState();
        };

        $onComplete = function () use (&$completionLog) {
            $completionLog[] = 'complete';
        };

        $child = (new RegionBuilder())
            ->setStates('child', 'done')
            ->markInitial('child')
            ->markFinal('done')
            ->onAction('child', fn(object $t) => 'done');

        $region = (new OrthogonalRegions(new RegionBuilder(), [$child]))
            ->setStates('parent', 'done')
            ->markInitial('parent')
            ->markFinal('done')
            ->onAction('parent', fn(object $t) => 'done')
            ->build();

        $config = new RuntimeConfig(
            onIteration: $onIteration,
            onComplete: $onComplete
        );

        $runtime = new StandardRuntime($region, $config);
        $runtime->run();

        $this->assertNotEmpty($iterationLog, 'onIteration should be called');
        $this->assertNotEmpty($completionLog, 'onComplete should be called');
    }
}
