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
 * Acceptance Criterion: Runtime calls onIteration callback before each region trigger
 */
#[Group('runtime')]
#[Group('runtime-event-loop')]
class RuntimeOnIterationCallbackTest extends TestCase
{
    public function testOnIterationCallbackIsInvokedBeforeEachIteration(): void
    {
        $callbackInvoked = false;

        $callback = function (Region $region, object $trigger, int $iteration) use (&$callbackInvoked): void {
            $callbackInvoked = true;
        };

        $region = (new RegionBuilder())
            ->setStates('start', 'done')
            ->markInitial('start')
            ->markFinal('done')
            ->addBuildStep(new AddTransition('start', 'done'))
            ->build();

        $runtime = new StandardRuntime($region, new RuntimeConfig(onIteration: $callback));
        $runtime->run();

        $this->assertTrue($callbackInvoked, 'onIteration callback should be invoked');
    }

    public function testOnIterationReceivesRegionInstance(): void
    {
        $receivedRegion = null;

        $callback = function (Region $region, object $trigger, int $iteration) use (&$receivedRegion): void {
            $receivedRegion = $region;
        };

        $region = (new RegionBuilder())
            ->setStates('start', 'done')
            ->markInitial('start')
            ->markFinal('done')
            ->addBuildStep(new AddTransition('start', 'done'))
            ->build();

        $runtime = new StandardRuntime($region, new RuntimeConfig(onIteration: $callback));
        $runtime->run();

        $this->assertSame($region, $receivedRegion, 'Callback should receive region instance');
    }

    public function testOnIterationReceivesTriggerObject(): void
    {
        $receivedTriggers = [];

        $callback = function (Region $region, object $trigger, int $iteration) use (&$receivedTriggers): void {
            $receivedTriggers[] = $trigger;
        };

        $region = (new RegionBuilder())
            ->setStates('start', 'done')
            ->markInitial('start')
            ->markFinal('done')
            ->addBuildStep(new AddTransition('start', 'done'))
            ->build();

        $runtime = new StandardRuntime($region, new RuntimeConfig(onIteration: $callback));
        $runtime->run();

        $this->assertCount(1, $receivedTriggers, 'Should receive trigger for each iteration');
        $this->assertIsObject($receivedTriggers[0], 'Should receive trigger object');
    }

    public function testOnIterationReceivesIterationNumber(): void
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
                return $count >= 3;
            }))
            ->build();

        $runtime = new StandardRuntime($region, new RuntimeConfig(onIteration: $callback));
        $runtime->run();

        $this->assertEquals([0, 1, 2], $receivedIterations, 'Should receive incrementing iteration numbers');
    }

    public function testOnIterationCalledBeforeTriggerDispatched(): void
    {
        $executionOrder = [];

        $callback = function (Region $region, object $trigger, int $iteration) use (&$executionOrder): void {
            $executionOrder[] = 'onIteration';
        };

        $region = (new RegionBuilder())
            ->setStates('start', 'done')
            ->markInitial('start')
            ->markFinal('done')
            ->addBuildStep(new AddTransition('start', 'done'))
            ->onAction('start', function (object $t) use (&$executionOrder): void {
                $executionOrder[] = 'onAction';
            })
            ->build();

        $runtime = new StandardRuntime($region, new RuntimeConfig(onIteration: $callback));
        $runtime->run();

        $this->assertEquals(['onIteration', 'onAction'], $executionOrder, 'onIteration should execute before trigger dispatch');
    }

    public function testOnIterationCalledForEveryIteration(): void
    {
        $callbackCount = 0;

        $callback = function (Region $region, object $trigger, int $iteration) use (&$callbackCount): void {
            $callbackCount++;
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
        $runtime->run();

        $this->assertEquals(5, $callbackCount, 'onIteration should be called once per iteration');
    }
}
