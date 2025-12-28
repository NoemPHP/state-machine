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
 * Acceptance Criterion: Runtime does not fire onComplete if region already completed
 */
#[Group('runtime')]
#[Group('runtime-completion')]
class RuntimeOnCompleteNoDoubleFireTest extends TestCase
{
    public function testOnCompleteDoesNotFireTwiceWhenRunCalledAfterCompletion(): void
    {
        $callbackCount = 0;

        $callback = function () use (&$callbackCount): void {
            $callbackCount++;
        };

        $region = (new RegionBuilder())
            ->setStates('start', 'done')
            ->markInitial('start')
            ->markFinal('done')
            ->addBuildStep(new AddTransition('start', 'done'))
            ->build();

        $runtime = new StandardRuntime($region, new RuntimeConfig(onComplete: $callback));

        // First run completes and fires callback
        $runtime->run();
        $this->assertEquals(1, $callbackCount);

        // Subsequent runs should not fire callback again
        $runtime->run();
        $this->assertEquals(1, $callbackCount, 'onComplete should not fire second time');

        $runtime->run(steps: 1);
        $this->assertEquals(1, $callbackCount, 'onComplete should not fire third time');
    }

    public function testOnCompleteNotFiredOnSubsequentStepCalls(): void
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
                return $count >= 2;
            }))
            ->build();

        $runtime = new StandardRuntime($region, new RuntimeConfig(onComplete: $callback));

        // Complete via steps
        $runtime->run(steps: 1);
        $runtime->run(steps: 1); // Completes here
        $this->assertEquals(1, $callbackCount);

        // Try calling more steps
        $runtime->run(steps: 1);
        $runtime->run(steps: 1);
        $runtime->run(steps: 1);

        $this->assertEquals(1, $callbackCount, 'onComplete should only fire once, not on subsequent calls');
    }

    public function testCompletionCallbackFiredFlagPersistsAcrossRunCalls(): void
    {
        $callbackHistory = [];

        $callback = function () use (&$callbackHistory): void {
            $callbackHistory[] = 'fired';
        };

        $region = (new RegionBuilder())
            ->setStates('start', 'done')
            ->markInitial('start')
            ->markFinal('done')
            ->addBuildStep(new AddTransition('start', 'done'))
            ->build();

        $runtime = new StandardRuntime($region, new RuntimeConfig(onComplete: $callback));

        // Complete in blocking mode
        $runtime->run();

        // Try various ways to run after completion
        $runtime->run();
        $runtime->run(steps: 1);
        $runtime->run(steps: 100);
        $runtime->run(0);

        $this->assertEquals(['fired'], $callbackHistory, 'Callback should fire exactly once regardless of how many times run() is called');
    }

    public function testRunReturnsFalseImmediatelyWhenAlreadyComplete(): void
    {
        $region = (new RegionBuilder())
            ->setStates('start', 'done')
            ->markInitial('start')
            ->markFinal('done')
            ->addBuildStep(new AddTransition('start', 'done'))
            ->build();

        $runtime = new StandardRuntime($region, new RuntimeConfig());

        // Complete it
        $firstResult = $runtime->run();
        $this->assertFalse($firstResult);

        // Subsequent calls should also return false
        $secondResult = $runtime->run(steps: 1);
        $this->assertFalse($secondResult, 'Should return false immediately when already complete');

        $thirdResult = $runtime->run();
        $this->assertFalse($thirdResult, 'Should continue returning false');
    }
}
