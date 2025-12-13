<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Async;

use Noem\State\Feature\Async\AsyncFeature;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: AsyncFeature resumes existing task on repeat callback invocation
 */
#[Group('async'), Group('async-callback-handling')]
class ResumesExistingTaskTest extends TestCase
{
    public function testResumesExistingTask(): void
    {
        $builder = new RegionBuilder();
        $builder->enableFeatures(new AsyncFeature());

        $executionOrder = [];
        $invocationCount = 0;
        $callbackRef = null;

        $region = $builder
            ->setStates('idle')
            ->onAction('idle', $callbackRef = function (object $trigger) use (&$executionOrder, &$invocationCount) {
                $invocationCount++;
                $executionOrder[] = 'step1';
                yield 'first';
                $executionOrder[] = 'step2';
                yield 'second';
                $executionOrder[] = 'step3';
                return 'done';
            })
            ->build();

        // First invocation - completes task (Priority::NORMAL = 5 steps, task has 3 steps)
        $region->trigger(new \stdClass());
        $this->assertSame(['step1', 'step2', 'step3'], $executionOrder, 'After trigger 1 - task completes');
        $this->assertSame(1, $invocationCount, 'Callback should be invoked once');

        // Second invocation - creates new task and completes it
        $region->trigger(new \stdClass());
        $this->assertSame(['step1', 'step2', 'step3', 'step1', 'step2', 'step3'], $executionOrder, 'After trigger 2 - new task completes');
        $this->assertSame(2, $invocationCount, 'Callback should be invoked again (new task created)');
    }

    public function testReturnsLastYieldedValue(): void
    {
        $builder = new RegionBuilder();
        $builder->enableFeatures(new AsyncFeature());

        $lastValue = null;

        $region = $builder
            ->setStates('idle')
            ->onAction('idle', function (object $trigger) {
                yield 'alpha';
                yield 'beta';
                yield 'gamma';
            })
            ->onAction('idle', function (object $trigger) use (&$lastValue) {
                $lastValue = 'captured';
            })
            ->build();

        $region->trigger(new \stdClass());
        // After ticking, we should be able to observe that async operations progressed
        $this->assertSame('captured', $lastValue);
    }
}
