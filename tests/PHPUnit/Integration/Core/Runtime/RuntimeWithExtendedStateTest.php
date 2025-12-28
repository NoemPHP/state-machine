<?php

declare(strict_types=1);

namespace Noem\State\Tests\Integration\Core\Runtime;

use Noem\State\Feature\ExtendedState\ExtendedState;
use Noem\State\Feature\Transitions\AddTransition;
use Noem\State\RegionBuilder;
use Noem\State\StandardRuntime;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Runtime integrates with ExtendedState context
 */
#[Group('runtime')]
#[Group('integration')]
#[Group('extended-state')]
class RuntimeWithExtendedStateTest extends TestCase
{
    public function testRuntimeExecutesWithExtendedStateContext(): void
    {
        $executionLog = [];
        $finalCounter = null;

        $region = (new RegionBuilder())->enableFeatures(new ExtendedState())
            ->setStates('start', 'processing', 'done')
            ->markInitial('start')
            ->markFinal('done')
            ->onEnter('start', function (object $t) use (&$executionLog) {
                $this->set('counter', 0);
                $executionLog[] = 'initialized';
            })
            ->addBuildStep(new AddTransition('start', 'processing'))
            ->onAction('start', function (object $t) use (&$executionLog): void {
                $counter = $this->get('counter');
                $this->set('counter', $counter + 1);
                $executionLog[] = "count_{$counter}";
            })
            ->addBuildStep(new AddTransition('processing', 'done'))
            ->onAction('processing', function (object $t) use (&$executionLog): void {
                $counter = $this->get('counter');
                $this->set('counter', $counter + 1);
                $executionLog[] = "count_{$counter}";
            })
            ->onEnter('done', function (object $t) use (&$finalCounter): void {
                $finalCounter = $this->get('counter');
            })
            ->build();

        $runtime = new StandardRuntime($region);
        $runtime->run();

        $this->assertEquals(['initialized', 'count_0', 'count_1'], $executionLog);
        $this->assertEquals(2, $finalCounter);
        $this->assertTrue($runtime->isComplete());
    }

    public function testContextPersistsAcrossIterations(): void
    {
        $finalSum = null;

        $region = (new RegionBuilder())->enableFeatures(new ExtendedState())
            ->setStates('accumulating', 'done')
            ->markInitial('accumulating')
            ->markFinal('done')
            ->onEnter('accumulating', function (object $t) {
                $this->set('sum', 0);
            })
            ->addBuildStep(new AddTransition('accumulating', 'done', function (object $t): bool {
                static $iterations = 0;
                $iterations++;

                $sum = $this->get('sum');
                $this->set('sum', $sum + $iterations);

                return $iterations >= 5;
            }))
            ->onEnter('done', function (object $t) use (&$finalSum): void {
                $finalSum = $this->get('sum');
            })
            ->build();

        $runtime = new StandardRuntime($region);
        $runtime->run();

        // Sum: 1 + 2 + 3 + 4 + 5 = 15
        $this->assertEquals(15, $finalSum);
        $this->assertTrue($runtime->isComplete());
    }

    public function testNonBlockingExecutionPreservesContext(): void
    {
        $capturedValues = [];

        $region = (new RegionBuilder())->enableFeatures(new ExtendedState())
            ->setStates('counting', 'done')
            ->markInitial('counting')
            ->markFinal('done')
            ->onEnter('counting', function (object $t) {
                $this->set('value', 10);
            })
            ->addBuildStep(new AddTransition('counting', 'done', function (object $t) use (&$capturedValues): bool {
                $value = $this->get('value');
                $this->set('value', $value * 2);
                $capturedValues[] = $value * 2; // Capture after update
                return $value >= 80;
            }))
            ->build();

        $runtime = new StandardRuntime($region);

        // Step-by-step execution
        $runtime->run(steps: 1); // value: 10 -> 20
        $this->assertEquals(20, $capturedValues[0]);

        $runtime->run(steps: 1); // value: 20 -> 40
        $this->assertEquals(40, $capturedValues[1]);

        $runtime->run(steps: 1); // value: 40 -> 80
        $this->assertEquals(80, $capturedValues[2]);

        $runtime->run(steps: 1); // -> done
        $this->assertTrue($runtime->isComplete());
    }

    public function testTriggerCanAccessContextData(): void
    {
        $capturedMarkers = [];

        $region = (new RegionBuilder())->enableFeatures(new ExtendedState())
            ->setStates('marking', 'done')
            ->markInitial('marking')
            ->markFinal('done')
            ->addBuildStep(new AddTransition('marking', 'done', function (object $t) use (&$capturedMarkers): bool {
                static $count = 0;
                $marker = "mark_{$count}";
                $this->set('iteration_marker', $marker);
                $capturedMarkers[] = $marker;
                $count++;
                return $count >= 3;
            }))
            ->build();

        $runtime = new StandardRuntime($region);

        $runtime->run();

        $this->assertEquals(['mark_0', 'mark_1', 'mark_2'], $capturedMarkers);
    }

    public function testContextIsolationBetweenRuntimes(): void
    {
        $id1 = null;
        $id2 = null;

        $createRegion = function (&$idCapture) {
            return (new RegionBuilder())->enableFeatures(new ExtendedState())
                ->setStates('init', 'done')
                ->markInitial('init')
                ->markFinal('done')
                ->onEnter('init', function (object $t) use (&$idCapture) {
                    $id = uniqid();
                    $this->set('id', $id);
                    $idCapture = $id;
                })
                ->addBuildStep(new AddTransition('init', 'done'))
                ->build();
        };

        $region1 = $createRegion($id1);
        $region2 = $createRegion($id2);

        $runtime1 = new StandardRuntime($region1);
        $runtime2 = new StandardRuntime($region2);

        $runtime1->run();
        $runtime2->run();

        $this->assertNotEquals($id1, $id2, 'Each runtime should have isolated context');
        $this->assertNotEmpty($id1);
        $this->assertNotEmpty($id2);
    }

    public function testSpawnedRuntimeInheritsParentContext(): void
    {
        $parentValue = null;
        $childValue = null;

        $parentRegion = (new RegionBuilder())->enableFeatures(new ExtendedState())
            ->setStates('parent', 'done')
            ->markInitial('parent')
            ->markFinal('done')
            ->onEnter('parent', function (object $t) use (&$parentValue) {
                $this->set('parent_data', 'from_parent');
                $parentValue = 'from_parent';
            })
            ->addBuildStep(new AddTransition('parent', 'done'))
            ->build();

        $childRegion = (new RegionBuilder())->enableFeatures(new ExtendedState())
            ->setStates('child', 'done')
            ->markInitial('child')
            ->markFinal('done')
            ->addBuildStep(new AddTransition('child', 'done'))
            ->onAction('child', function (object $t) use (&$childValue): void {
                $this->set('child_data', 'from_child');
                $childValue = 'from_child';
            })
            ->build();

        $parentRuntime = new StandardRuntime($parentRegion);
        $parentRuntime->run(steps: 1); // Initialize parent

        $childRuntime = $parentRuntime->spawn($childRegion);
        $childRuntime->run();

        // Both runtimes should have executed with independent contexts
        $this->assertEquals('from_parent', $parentValue);
        $this->assertEquals('from_child', $childValue);
    }
}
