<?php

declare(strict_types=1);

namespace Tests\Unit\Feature\Async\Scheduler;

use Noem\State\Feature\Async\AsyncConfig;
use Noem\State\Feature\Async\CoroutineScheduler;
use Noem\State\Feature\Async\Priority;
use PHPUnit\Framework\TestCase;

final class AcceptsAsyncConfigTest extends TestCase
{
    public function testEnqueueAcceptsAsyncConfigParameter(): void
    {
        $scheduler = new CoroutineScheduler();

        $generator = (function () {
            yield 1;
        })();

        $config = new AsyncConfig(
            debounce: 0.5,
            throttle: 1.0,
            singleton: true,
            priority: Priority::HIGH,
            timeout: 5.0
        );

        $callback = function () {};

        // Should not throw
        $task = $scheduler->enqueue($generator, $config, $callback);

        $this->assertNotNull($task);
    }

    public function testEnqueueAcceptsMinimalAsyncConfig(): void
    {
        $scheduler = new CoroutineScheduler();

        $generator = (function () {
            yield 1;
        })();

        $config = new AsyncConfig(); // All defaults

        $task = $scheduler->enqueue($generator, $config, function () {});

        $this->assertNotNull($task);
    }

    public function testEnqueueAcceptsCallableParameter(): void
    {
        $scheduler = new CoroutineScheduler();

        $generator = (function () {
            yield 1;
        })();

        $callback = function () {};
        $config = new AsyncConfig();

        $task = $scheduler->enqueue($generator, $config, $callback);

        $this->assertNotNull($task);
    }
}
