<?php

declare(strict_types=1);

namespace Noem\State\Tests\Integration\Core\Runtime;

use Noem\State\Feature\Transitions\AddTransition;
use Noem\State\RegionBuilder;
use Noem\State\StandardRuntime;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Runtime integrates with TransitionsFeature guards and callbacks
 */
#[Group('runtime')]
#[Group('integration')]
#[Group('transitions')]
class RuntimeWithTransitionsTest extends TestCase
{
    public function testRuntimeExecutesWithTransitionGuards(): void
    {
        $attempts = [];

        $region = (new RegionBuilder())
            ->setStates('locked', 'unlocked', 'open')
            ->markInitial('locked')
            ->markFinal('open')
            ->addBuildStep(new AddTransition('locked', 'unlocked', function (object $t) use (&$attempts): bool {
                $attempts[] = 'try_unlock';
                // Only unlock after 3 attempts
                return count($attempts) >= 3;
            }))
            ->addBuildStep(new AddTransition('unlocked', 'open', fn(object $t): bool => true))
            ->build();

        $runtime = new StandardRuntime($region);
        $runtime->run();

        $this->assertGreaterThanOrEqual(3, count($attempts), 'Should attempt transition multiple times');
        $this->assertEquals('open', $region->currentState());
        $this->assertTrue($runtime->isComplete());
    }

    public function testGuardsCanBlockTransitions(): void
    {
        $guardChecks = 0;

        $region = (new RegionBuilder())
            ->setStates('waiting', 'ready', 'processing')
            ->markInitial('waiting')
            ->addBuildStep(new AddTransition('waiting', 'ready', function (object $t) use (&$guardChecks): bool {
                $guardChecks++;
                // Never allow transition
                return false;
            }))
            ->build();

        $runtime = new StandardRuntime($region, new \Noem\State\RuntimeConfig(maxIterations: 5));

        try {
            $runtime->run();
            $this->fail('Should throw MaxIterationsException when guard blocks all transitions');
        } catch (\Noem\State\Exception\MaxIterationsException $e) {
            $this->assertGreaterThan(0, $guardChecks, 'Guard should be checked');
            $this->assertEquals('waiting', $region->currentState(), 'Should remain in waiting state');
        }
    }

    public function testTransitionCallbacksExecuteDuringRuntimeExecution(): void
    {
        $callbackLog = [];

        $region = (new RegionBuilder())
            ->setStates('start', 'middle', 'end')
            ->markInitial('start')
            ->markFinal('end')
            ->addBuildStep(new AddTransition('start', 'middle'))
            ->addBuildStep(new AddTransition('middle', 'end'))
            ->onEnter('middle', function (object $t) use (&$callbackLog) {
                $callbackLog[] = 'start->middle';
            })
            ->onEnter('end', function (object $t) use (&$callbackLog) {
                $callbackLog[] = 'middle->end';
            })
            ->build();

        $runtime = new StandardRuntime($region);
        $runtime->run();

        $this->assertEquals(['start->middle', 'middle->end'], $callbackLog);
        $this->assertTrue($runtime->isComplete());
    }

    public function testConditionalTransitionsDuringNonBlockingExecution(): void
    {
        $shouldTransition = false;

        $region = (new RegionBuilder())
            ->setStates('checking', 'passed', 'done')
            ->markInitial('checking')
            ->markFinal('done')
            ->addBuildStep(new AddTransition('checking', 'passed', function (object $t) use (&$shouldTransition): bool {
                return $shouldTransition;
            }))
            ->addBuildStep(new AddTransition('passed', 'done'))
            ->build();

        $runtime = new StandardRuntime($region);

        // First attempt - guard blocks
        $runtime->run(steps: 1);
        $this->assertEquals('checking', $region->currentState());

        // Second attempt - guard blocks again
        $runtime->run(steps: 1);
        $this->assertEquals('checking', $region->currentState());

        // Enable transition
        $shouldTransition = true;

        // Third attempt - guard allows
        $runtime->run(steps: 1);
        $this->assertEquals('passed', $region->currentState());

        // Complete
        $runtime->run();
        $this->assertTrue($runtime->isComplete());
    }

    public function testTransitionsWithTriggerData(): void
    {
        $receivedTriggers = [];

        $region = (new RegionBuilder())
            ->setStates('processing', 'done')
            ->markInitial('processing')
            ->markFinal('done')
            ->addBuildStep(new AddTransition('processing', 'done', function (object $t) use (&$receivedTriggers): bool {
                $receivedTriggers[] = $t;
                // Allow transition after 3 triggers
                return count($receivedTriggers) >= 3;
            }))
            ->build();

        $runtime = new StandardRuntime($region);
        $runtime->run();

        $this->assertCount(3, $receivedTriggers, 'Should receive trigger on each guard check');
        foreach ($receivedTriggers as $trigger) {
            $this->assertIsObject($trigger);
        }
        $this->assertTrue($runtime->isComplete());
    }

    public function testGuardCanAccessRegionState(): void
    {
        $region = (new RegionBuilder())
            ->setStates('counting', 'threshold', 'done')
            ->markInitial('counting')
            ->markFinal('done')
            ->addBuildStep(new AddTransition('counting', 'threshold', function (object $t): bool {
                static $checks = 0;
                $checks++;
                // Transition allowed after 5 checks
                return $checks >= 5;
            }))
            ->addBuildStep(new AddTransition('threshold', 'done', fn(object $t): bool => true))
            ->build();

        $runtime = new StandardRuntime($region);
        $runtime->run();

        $this->assertEquals('done', $region->currentState());
        $this->assertTrue($runtime->isComplete());
    }

    public function testMultipleGuardsForSameTransition(): void
    {
        $guard1Checks = 0;
        $guard2Checks = 0;

        $region = (new RegionBuilder())
            ->setStates('start', 'end')
            ->markInitial('start')
            ->markFinal('end')
            ->addBuildStep(new AddTransition('start', 'end', function (object $t) use (&$guard1Checks): bool {
                $guard1Checks++;
                return $guard1Checks >= 2;
            }))
            ->addBuildStep(new AddTransition('start', 'end', function (object $t) use (&$guard2Checks): bool {
                $guard2Checks++;
                return $guard2Checks >= 3;
            }))
            ->build();

        $runtime = new StandardRuntime($region);
        $runtime->run();

        // guard2 checked first (LIFO), guard1 checked second
        // guard1 returns true after 2 checks, stopping evaluation
        $this->assertEquals(2, $guard1Checks, 'guard1 should be checked until it returns true');
        $this->assertEquals(2, $guard2Checks, 'guard2 should be checked same number of times before guard1 wins');
        $this->assertTrue($runtime->isComplete());
    }

    public function testTransitionCallbacksReceiveTrigger(): void
    {
        $callbackTriggers = [];

        $region = (new RegionBuilder())
            ->setStates('start', 'end')
            ->markInitial('start')
            ->markFinal('end')
            ->addBuildStep(new AddTransition('start', 'end'))
            ->onEnter('end', function (object $t) use (&$callbackTriggers) {
                $callbackTriggers[] = $t;
            })
            ->build();

        $runtime = new StandardRuntime($region);
        $runtime->run();

        $this->assertCount(1, $callbackTriggers);
        $this->assertIsObject($callbackTriggers[0]);
        $this->assertInstanceOf(\stdClass::class, $callbackTriggers[0]);
    }
}
