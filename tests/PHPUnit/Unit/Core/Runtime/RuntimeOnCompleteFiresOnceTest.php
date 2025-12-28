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
 * Acceptance Criterion: Runtime fires onComplete callback exactly once when region completes
 */
#[Group('runtime')]
#[Group('runtime-completion')]
class RuntimeOnCompleteFiresOnceTest extends TestCase
{
    public function testOnCompleteFiresExactlyOnceWhenRegionCompletes(): void
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

        $runtime->run();

        $this->assertEquals(1, $callbackCount, 'onComplete should fire exactly once');
    }

    public function testOnCompleteDoesNotFireIfRegionDoesNotComplete(): void
    {
        $callbackFired = false;

        $callback = function () use (&$callbackFired): void {
            $callbackFired = true;
        };

        $region = (new RegionBuilder())
            ->setStates('infinite', 'done')
            ->markInitial('infinite')
            ->markFinal('done')
            ->addBuildStep(new AddTransition('infinite', 'infinite'))
            ->build();

        $runtime = new StandardRuntime($region, new RuntimeConfig(onComplete: $callback, maxIterations: 3));

        try {
            $runtime->run();
        } catch (\RuntimeException $e) {
            // Expected - max iterations
        }

        $this->assertFalse($callbackFired, 'onComplete should not fire if region does not complete');
    }

    public function testOnCompleteFiresOnlyOnceAcrossMultipleRunCalls(): void
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
                return $count >= 3;
            }))
            ->build();

        $runtime = new StandardRuntime($region, new RuntimeConfig(onComplete: $callback));

        // Run in steps
        $runtime->run(steps: 1);
        $this->assertEquals(0, $callbackCount, 'Should not fire yet');

        $runtime->run(steps: 1);
        $this->assertEquals(0, $callbackCount, 'Should still not fire');

        $runtime->run(steps: 1); // Completes
        $this->assertEquals(1, $callbackCount, 'Should fire on completion');

        // Additional calls should not fire callback again
        $runtime->run(steps: 1);
        $this->assertEquals(1, $callbackCount, 'Should not fire again');
    }

    public function testOnCompleteNotCalledOnException(): void
    {
        $callbackFired = false;

        $callback = function () use (&$callbackFired): void {
            $callbackFired = true;
        };

        $region = (new RegionBuilder())
            ->setStates('start')
            ->markInitial('start')
            ->onAction('start', function (object $t) {
                throw new \RuntimeException('Test exception');
            })
            ->build();

        $runtime = new StandardRuntime($region, new RuntimeConfig(onComplete: $callback));

        try {
            $runtime->run();
        } catch (\RuntimeException $e) {
            // Expected
        }

        $this->assertFalse($callbackFired, 'onComplete should not fire when exception thrown');
    }
}
