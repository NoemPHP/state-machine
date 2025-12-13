<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Integration\Feature\Async;

use Noem\State\Feature\Async\AsyncConfig;
use Noem\State\Feature\Async\AsyncFeature;
use Noem\State\Feature\ExtendedState\ExtendedState;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Singleton prevents new task when callback has running task
 * Intent: Implements mutex-like behavior, ensuring only one task per callback executes concurrently
 */
#[Group('async'), Group('integration'), Group('singleton')]
class SingletonPreventionTest extends TestCase
{
    public function testSingletonPreventsNewTaskWhenCallbackHasRunningTask(): void
    {
        $builder = new RegionBuilder();
        $builder->enableFeatures(new ExtendedState(), new AsyncFeature());

        $taskStarts = 0;

        $longRunningCallback = function (object $trigger) use (&$taskStarts) {
            $taskStarts++;
            for ($i = 0; $i < 50; $i++) {
                yield;
            }
        };

        $config = new AsyncConfig(singleton: true);

        $builder
            ->setStates('active')
            ->onAction('active', $longRunningCallback, $config);

        $region = $builder->build();

        // Start first task
        $region->trigger(new \stdClass());
        $this->assertEquals(1, $taskStarts, 'First task should start');

        // Try to start multiple tasks while first is running
        for ($i = 0; $i < 10; $i++) {
            $region->trigger(new \stdClass());
        }

        $this->assertEquals(1, $taskStarts, 'Singleton should prevent all concurrent task starts');
    }
}
