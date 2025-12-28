<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Core\Runtime;

use Noem\State\RegionBuilder;
use Noem\State\Feature\Transitions\AddTransition;
use Noem\State\RuntimeConfig;
use Noem\State\StandardRuntime;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Runtime fires onComplete when run() with no steps completes
 */
#[Group('runtime')]
#[Group('runtime-completion')]
class RuntimeOnCompleteBlockingModeTest extends TestCase
{
    public function testOnCompleteFiresInBlockingMode(): void
    {
        $callbackFired = false;

        $callback = function () use (&$callbackFired): void {
            $callbackFired = true;
        };

        $region = (new RegionBuilder())
            ->setStates('start', 'done')
            ->markInitial('start')
            ->markFinal('done')
            ->addBuildStep(new AddTransition('start', 'done'))
            ->build();

        $runtime = new StandardRuntime($region, new RuntimeConfig(onComplete: $callback));

        $runtime->run(); // Blocking execution

        $this->assertTrue($callbackFired, 'onComplete should fire after blocking run() completes');
    }

    public function testOnCompleteFiresAfterAllIterationsInBlockingMode(): void
    {
        $executionOrder = [];

        $callback = function () use (&$executionOrder): void {
            $executionOrder[] = 'onComplete';
        };

        $region = (new RegionBuilder())
            ->setStates('counting', 'done')
            ->markInitial('counting')
            ->markFinal('done')
            ->addBuildStep(new AddTransition('counting', 'done', function (object $t) use (&$executionOrder): bool {
                static $count = 0;
                $count++;
                $executionOrder[] = "iteration_{$count}";
                return $count >= 3;
            }))
            ->build();

        $runtime = new StandardRuntime($region, new RuntimeConfig(onComplete: $callback));

        $runtime->run(0); // Explicit 0 = blocking

        $this->assertEquals(['iteration_1', 'iteration_2', 'iteration_3', 'onComplete'], $executionOrder);
    }

    public function testOnCompleteReceivesNoParameters(): void
    {
        $parameterCount = null;

        $callback = function (...$params) use (&$parameterCount): void {
            $parameterCount = count($params);
        };

        $region = (new RegionBuilder())
            ->setStates('start', 'done')
            ->markInitial('start')
            ->markFinal('done')
            ->addBuildStep(new AddTransition('start', 'done'))
            ->build();

        $runtime = new StandardRuntime($region, new RuntimeConfig(onComplete: $callback));

        $runtime->run();

        $this->assertEquals(0, $parameterCount, 'onComplete should receive no parameters');
    }

    public function testRunReturnsFalseAfterOnCompleteFiresInBlockingMode(): void
    {
        $callbackFired = false;

        $callback = function () use (&$callbackFired): void {
            $callbackFired = true;
        };

        $region = (new RegionBuilder())
            ->setStates('start', 'done')
            ->markInitial('start')
            ->markFinal('done')
            ->addBuildStep(new AddTransition('start', 'done'))
            ->build();

        $runtime = new StandardRuntime($region, new RuntimeConfig(onComplete: $callback));

        $result = $runtime->run();

        $this->assertTrue($callbackFired);
        $this->assertFalse($result, 'run() should return false after onComplete fires');
    }
}
