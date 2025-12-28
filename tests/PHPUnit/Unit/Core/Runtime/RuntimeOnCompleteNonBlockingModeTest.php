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
 * Acceptance Criterion: Runtime fires onComplete only on final run(steps) call that completes
 */
#[Group('runtime')]
#[Group('runtime-completion')]
class RuntimeOnCompleteNonBlockingModeTest extends TestCase
{
    public function testOnCompleteFiresOnlyOnFinalStepThatCompletes(): void
    {
        $callbackFired = false;

        $callback = function () use (&$callbackFired): void {
            $callbackFired = true;
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

        $runtime = new StandardRuntime($region, new RuntimeConfig(onComplete: $callback));

        $runtime->run(steps: 1);
        $this->assertFalse($callbackFired, 'Should not fire after step 1');

        $runtime->run(steps: 1);
        $this->assertFalse($callbackFired, 'Should not fire after step 2');

        $runtime->run(steps: 1);
        $this->assertTrue($callbackFired, 'Should fire on step that causes completion');
    }

    public function testOnCompleteFiresOnCorrectStepInNonBlockingLoop(): void
    {
        $completedAtIteration = null;

        $callback = function () use (&$completedAtIteration): void {
            static $iteration = 0;
            $completedAtIteration = $iteration;
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

        $runtime = new StandardRuntime($region, new RuntimeConfig(onComplete: $callback));

        $loopIteration = 0;
        while ($runtime->run(steps: 1)) {
            $loopIteration++;
        }

        $this->assertEquals(0, $completedAtIteration, 'onComplete should fire when run() returns false');
        $this->assertEquals(4, $loopIteration, 'Loop should execute 4 times before completion');
    }

    public function testOnCompleteDoesNotFireOnIntermediateSteps(): void
    {
        $callbackCount = 0;

        $callback = function () use (&$callbackCount): void {
            $callbackCount++;
        };

        $region = (new RegionBuilder())
            ->setStates('counting', 'done')
            ->markInitial('counting')
            ->markFinal('done')
            ->addBuildStep(new AddTransition('counting', 'done', function (object $t): bool {
                static $count = 0;
                $count++;
                return $count >= 10;
            }))
            ->build();

        $runtime = new StandardRuntime($region, new RuntimeConfig(onComplete: $callback));

        // Run 9 non-completing steps
        for ($i = 0; $i < 9; $i++) {
            $runtime->run(steps: 1);
        }

        $this->assertEquals(0, $callbackCount, 'onComplete should not fire on intermediate steps');

        // Final completing step
        $runtime->run(steps: 1);

        $this->assertEquals(1, $callbackCount, 'onComplete should fire exactly once on completing step');
    }

    public function testRunStepsReturnsFalseWhenOnCompleteWouldFire(): void
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

        $result = $runtime->run(steps: 1);

        $this->assertFalse($result, 'run(steps) should return false when completing');
        $this->assertTrue($callbackFired, 'onComplete should have fired');
    }
}
