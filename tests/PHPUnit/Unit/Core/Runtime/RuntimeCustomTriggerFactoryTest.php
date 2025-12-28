<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Core\Runtime;

use Noem\State\Feature\Transitions\AddTransition;
use Noem\State\Region;
use Noem\State\RegionBuilder;
use Noem\State\RuntimeConfig;
use Noem\State\StandardRuntime;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Runtime uses triggerFactory when configured
 */
#[Group('runtime')]
#[Group('runtime-event-loop')]
class RuntimeCustomTriggerFactoryTest extends TestCase
{
    public function testCustomTriggerFactoryIsUsed(): void
    {
        $factoryCalled = false;
        $receivedTrigger = null;

        $factory = function (int $iteration, Region $region) use (&$factoryCalled): object {
            $factoryCalled = true;
            return (object)['custom' => 'trigger', 'iteration' => $iteration];
        };

        $region = (new RegionBuilder())
            ->setStates('start', 'done')
            ->markInitial('start')
            ->markFinal('done')
            ->addBuildStep(new AddTransition('start', 'done'))
            ->onAction('start', function (object $t) use (&$receivedTrigger): void {
                $receivedTrigger = $t;
            })
            ->build();

        $runtime = new StandardRuntime($region, new RuntimeConfig(triggerFactory: $factory));
        $runtime->run();

        $this->assertTrue($factoryCalled, 'Custom trigger factory should be called');
        $this->assertEquals('trigger', $receivedTrigger->custom, 'Should receive custom trigger');
    }

    public function testTriggerFactoryReceivesIterationNumber(): void
    {
        $receivedIterations = [];

        $factory = function (int $iteration, Region $region) use (&$receivedIterations): object {
            $receivedIterations[] = $iteration;
            return (object)['iteration' => $iteration];
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

        $runtime = new StandardRuntime($region, new RuntimeConfig(triggerFactory: $factory));
        $runtime->run();

        $this->assertEquals([0, 1, 2], $receivedIterations, 'Factory should receive incrementing iteration numbers');
    }

    public function testTriggerFactoryReceivesRegionInstance(): void
    {
        $receivedRegion = null;

        $factory = function (int $iteration, Region $region) use (&$receivedRegion): object {
            $receivedRegion = $region;
            return (object)[];
        };

        $region = (new RegionBuilder())
            ->setStates('start', 'done')
            ->markInitial('start')
            ->markFinal('done')
            ->addBuildStep(new AddTransition('start', 'done'))
            ->build();

        $runtime = new StandardRuntime($region, new RuntimeConfig(triggerFactory: $factory));
        $runtime->run();

        $this->assertSame($region, $receivedRegion, 'Factory should receive the region instance');
    }

    public function testTriggerFactoryCalledOncePerIteration(): void
    {
        $factoryCallCount = 0;

        $factory = function (int $iteration, Region $region) use (&$factoryCallCount): object {
            $factoryCallCount++;
            return (object)[];
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

        $runtime = new StandardRuntime($region, new RuntimeConfig(triggerFactory: $factory));
        $runtime->run();

        $this->assertEquals(5, $factoryCallCount, 'Factory should be called exactly once per iteration');
    }
}
