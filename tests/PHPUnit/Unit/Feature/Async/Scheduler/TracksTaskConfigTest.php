<?php

declare(strict_types=1);

namespace Tests\Unit\Feature\Async\Scheduler;

use Noem\State\Feature\Async\AsyncConfig;
use Noem\State\Feature\Async\CoroutineScheduler;
use Noem\State\Feature\Async\Priority;
use PHPUnit\Framework\TestCase;

final class TracksTaskConfigTest extends TestCase
{
    public function testSchedulerTracksTaskToAsyncConfigMapping(): void
    {
        $scheduler = new CoroutineScheduler();

        $generator = (function () {
            yield 1;
        })();

        $config = new AsyncConfig(priority: Priority::HIGH);

        $task = $scheduler->enqueue($generator, $config, function () {});

        // Verify config is stored in task
        $this->assertSame($config, $task->getConfig());
    }

    public function testMultipleTasksHaveIndependentConfigs(): void
    {
        $scheduler = new CoroutineScheduler();

        $gen1 = (function () {
            yield 1;
        })();
        $gen2 = (function () {
            yield 2;
        })();

        $config1 = new AsyncConfig(priority: Priority::HIGH);
        $config2 = new AsyncConfig(priority: Priority::LOW);

        $task1 = $scheduler->enqueue($gen1, $config1, function () {});
        $task2 = $scheduler->enqueue($gen2, $config2, function () {});

        // Verify each task has its own config
        $this->assertSame($config1, $task1->getConfig());
        $this->assertSame($config2, $task2->getConfig());
        $this->assertNotSame($config1, $config2);
    }
}
