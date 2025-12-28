<?php

declare(strict_types=1);

namespace Noem\State\Tests\Integration\Feature\OrthogonalRegions;

use Noem\State\Feature\OrthogonalRegions\OrthogonalRegions;
use Noem\State\Feature\Transitions\TransitionsFeature;
use Noem\State\RegionBuilder;
use Noem\State\StandardRuntime;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: OrthogonalRegions works with TransitionsFeature guards
 */
#[Group('orthogonal-regions')]
#[Group('integration')]
#[Group('transitions')]
class OrthogonalRegionsWithTransitionsTest extends TestCase
{
    public function testOrthogonalRegionsWithTransitionsFeature(): void
    {
        $guardChecks = 0;

        $child = (new TransitionsFeature(new RegionBuilder()))
            ->setStates('locked', 'unlocked')
            ->markInitial('locked')
            ->markFinal('unlocked')
            ->addTransition('locked', 'unlocked', function (object $t) use (&$guardChecks) {
                $guardChecks++;
                return $guardChecks >= 2;
            })
            ->onAction('locked', fn(object $t) => 'unlocked');

        $region = (new OrthogonalRegions(new RegionBuilder(), [$child]))
            ->setStates('parent', 'done')
            ->markInitial('parent')
            ->markFinal('done')
            ->onAction('parent', fn(object $t) => 'done')
            ->build();

        $runtime = new StandardRuntime($region);
        $runtime->run();

        $this->assertGreaterThanOrEqual(2, $guardChecks);
        $this->assertTrue($runtime->isComplete());
    }

    public function testSummonedRegionWithTransitionGuards(): void
    {
        $guardPassed = false;

        $childBuilder = (new TransitionsFeature(new RegionBuilder()))
            ->setStates('waiting', 'ready')
            ->markInitial('waiting')
            ->markFinal('ready')
            ->addTransition('waiting', 'ready', function (object $t) use (&$guardPassed) {
                static $attempts = 0;
                $attempts++;
                if ($attempts >= 3) {
                    $guardPassed = true;
                    return true;
                }
                return false;
            })
            ->onAction('waiting', fn(object $t) => 'ready');

        $region = (new OrthogonalRegions(new RegionBuilder()))
            ->setStates('parent')
            ->markInitial('parent')
            ->onEnter('parent', function (object $t) use ($childBuilder) {
                $this->summon($childBuilder)->run();
            })
            ->build();

        $runtime = new StandardRuntime($region);
        $runtime->run(steps: 1);

        $this->assertTrue($guardPassed);
    }

    public function testParentWithTransitionsCanSummonChildren(): void
    {
        $parentGuardChecks = 0;
        $childExecuted = false;

        $childBuilder = (new RegionBuilder())
            ->setStates('child', 'child_done')
            ->markInitial('child')
            ->markFinal('child_done')
            ->onEnter('child', function (object $t) use (&$childExecuted) {
                $childExecuted = true;
            })
            ->onAction('child', fn(object $t) => 'child_done');

        $region = (new OrthogonalRegions(
            new TransitionsFeature(new RegionBuilder())
        ))
            ->setStates('idle', 'spawning', 'done')
            ->markInitial('idle')
            ->markFinal('done')
            ->addTransition('idle', 'spawning', function (object $t) use (&$parentGuardChecks) {
                $parentGuardChecks++;
                return true;
            })
            ->addTransition('spawning', 'done', fn(object $t) => true)
            ->onAction('idle', fn(object $t) => 'spawning')
            ->onEnter('spawning', function (object $t) use ($childBuilder) {
                $this->summon($childBuilder)->run();
            })
            ->onAction('spawning', fn(object $t) => 'done')
            ->build();

        $runtime = new StandardRuntime($region);
        $runtime->run();

        $this->assertGreaterThan(0, $parentGuardChecks);
        $this->assertTrue($childExecuted);
        $this->assertTrue($runtime->isComplete());
    }

    public function testTransitionGuardsInNestedOrthogonalRegions(): void
    {
        $grandchildGuardChecks = 0;
        $childGuardChecks = 0;

        $grandchild = (new TransitionsFeature(new RegionBuilder()))
            ->setStates('gc_start', 'gc_end')
            ->markInitial('gc_start')
            ->markFinal('gc_end')
            ->addTransition('gc_start', 'gc_end', function (object $t) use (&$grandchildGuardChecks) {
                $grandchildGuardChecks++;
                return true;
            })
            ->onAction('gc_start', fn(object $t) => 'gc_end');

        $child = (new OrthogonalRegions(
            new TransitionsFeature(new RegionBuilder()),
            [$grandchild]
        ))
            ->setStates('child_start', 'child_end')
            ->markInitial('child_start')
            ->markFinal('child_end')
            ->addTransition('child_start', 'child_end', function (object $t) use (&$childGuardChecks) {
                $childGuardChecks++;
                return true;
            })
            ->onAction('child_start', fn(object $t) => 'child_end');

        $parent = (new OrthogonalRegions(new RegionBuilder(), [$child]))
            ->setStates('parent', 'done')
            ->markInitial('parent')
            ->markFinal('done')
            ->onAction('parent', fn(object $t) => 'done')
            ->build();

        $runtime = new StandardRuntime($parent);
        $runtime->run();

        $this->assertGreaterThan(0, $childGuardChecks, 'Child guards should be checked');
        $this->assertGreaterThan(0, $grandchildGuardChecks, 'Grandchild guards should be checked');
    }

    public function testTransitionCallbacksInOrthogonalChildren(): void
    {
        $callbackLog = [];

        $child = (new TransitionsFeature(new RegionBuilder()))
            ->setStates('start', 'middle', 'end')
            ->markInitial('start')
            ->markFinal('end')
            ->addTransition('start', 'middle', fn(object $t) => true, function (object $t) use (&$callbackLog) {
                $callbackLog[] = 'start->middle';
            })
            ->addTransition('middle', 'end', fn(object $t) => true, function (object $t) use (&$callbackLog) {
                $callbackLog[] = 'middle->end';
            })
            ->onAction('start', fn(object $t) => 'middle')
            ->onAction('middle', fn(object $t) => 'end');

        $region = (new OrthogonalRegions(new RegionBuilder(), [$child]))
            ->setStates('parent', 'done')
            ->markInitial('parent')
            ->markFinal('done')
            ->onAction('parent', fn(object $t) => 'done')
            ->build();

        $runtime = new StandardRuntime($region);
        $runtime->run();

        $this->assertEquals(['start->middle', 'middle->end'], $callbackLog);
    }

    public function testConditionalSummonBasedOnTransitionGuard(): void
    {
        $childSummoned = false;

        $childBuilder = (new RegionBuilder())
            ->setStates('child')
            ->markInitial('child')
            ->onEnter('child', function (object $t) use (&$childSummoned) {
                $childSummoned = true;
            });

        $region = (new OrthogonalRegions(
            new TransitionsFeature(new RegionBuilder())
        ))
            ->setStates('checking', 'spawning', 'done')
            ->markInitial('checking')
            ->markFinal('done')
            ->addTransition('checking', 'spawning', function (object $t) {
                static $checks = 0;
                $checks++;
                return $checks >= 2; // Only transition after 2 checks
            })
            ->addTransition('spawning', 'done', fn(object $t) => true)
            ->onAction('checking', fn(object $t) => 'spawning')
            ->onEnter('spawning', function (object $t) use ($childBuilder) {
                $this->summon($childBuilder)->run();
            })
            ->onAction('spawning', fn(object $t) => 'done')
            ->build();

        $runtime = new StandardRuntime($region);
        $runtime->run();

        $this->assertTrue($childSummoned);
    }
}
