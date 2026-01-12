<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Async;

use Noem\State\Feature\Async\AsyncConfig;
use Noem\State\Feature\Async\CoroutineScheduler;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Singleton check uses callback reference for identity
 * Intent: Uses WeakMap keyed by callback for efficient singleton tracking without memory leaks
 */
#[Group('async'), Group('unit'), Group('singleton')]
class SingletonCallbackIdentityTest extends TestCase
{
    public function testSingletonCheckUsesCallbackReferenceForIdentity(): void
    {
        $scheduler = new CoroutineScheduler();
        $config = new AsyncConfig(singleton: true);

        $callback = function () {
        };

        $gen1 = (function () {
            yield 'first';
        })();
        $task1 = $scheduler->enqueue($gen1, $config, $callback);

        // Second enqueue with same callback reference should return existing task
        $gen2 = (function () {
            yield 'second';
        })();
        $task2 = $scheduler->enqueue($gen2, $config, $callback);

        // Should be same task instance (singleton behavior)
        $this->assertSame($task1, $task2, 'Same callback should return existing task');

        // Different callback should create new task
        $differentCallback = function () {
        };
        $gen3 = (function () {
            yield 'third';
        })();
        $task3 = $scheduler->enqueue($gen3, $config, $differentCallback);

        $this->assertNotSame($task1, $task3, 'Different callback should create new task');
    }
}
