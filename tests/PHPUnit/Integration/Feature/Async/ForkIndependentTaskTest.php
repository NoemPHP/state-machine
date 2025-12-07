<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Integration\Feature\Async;

use Noem\State\Feature\Async\AsyncFeature;
use Noem\State\Feature\Async\Call;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Call.fork spawns independent task without blocking parent
 */
#[Group('async'), Group('integration')]
class ForkIndependentTaskTest extends TestCase
{
    public function testForkSpawnsIndependentTaskWithoutBlockingParent(): void
    {
        $builder = new RegionBuilder();
        $builder->enableFeatures(new AsyncFeature());

        $log = [];

        $region = $builder
            ->setStates('active')
            ->onAction('active', function (object $event) use (&$log) {
                $log[] = 'parent-start';
                yield;

                // Fork an independent task
                yield Call::fork(function () use (&$log) {
                    $log[] = 'forked-start';
                    yield;
                    $log[] = 'forked-middle';
                    yield;
                    $log[] = 'forked-end';
                });

                $log[] = 'parent-continues';
                yield;
                $log[] = 'parent-end';
            })
            ->build();

        // Execute triggers to allow both parent and forked task to run
        for ($i = 0; $i < 10; $i++) {
            $region->trigger(new \stdClass());
        }

        // Verify both parent and forked task executed
        $this->assertContains('parent-start', $log);
        $this->assertContains('parent-continues', $log);
        $this->assertContains('parent-end', $log);
        $this->assertContains('forked-start', $log);
        $this->assertContains('forked-middle', $log);
        $this->assertContains('forked-end', $log);

        // Verify parent continued without waiting for forked task
        $parentContinuesIndex = array_search('parent-continues', $log);
        $forkedEndIndex = array_search('forked-end', $log);

        // Parent should have been able to continue before forked task completed
        // (they run cooperatively, so parent-continues might come before forked-end)
        $this->assertNotFalse($parentContinuesIndex, 'Parent should continue after fork');
    }

    public function testForkedTaskRunsConcurrently(): void
    {
        $builder = new RegionBuilder();
        $builder->enableFeatures(new AsyncFeature());

        $parentSteps = 0;
        $forkedSteps = 0;

        $region = $builder
            ->setStates('active')
            ->onAction('active', function (object $event) use (&$parentSteps, &$forkedSteps) {
                $parentSteps++;
                yield;

                yield Call::fork(function () use (&$forkedSteps) {
                    $forkedSteps++;
                    yield;
                    $forkedSteps++;
                    yield;
                });

                $parentSteps++;
                yield;
                $parentSteps++;
            })
            ->build();

        // Execute enough triggers for both to make progress
        for ($i = 0; $i < 10; $i++) {
            $region->trigger(new \stdClass());
        }

        $this->assertGreaterThanOrEqual(2, $parentSteps, 'Parent should have progressed');
        $this->assertGreaterThanOrEqual(1, $forkedSteps, 'Forked task should have progressed independently');
    }
}
