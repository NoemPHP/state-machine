<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Integration\Feature\Async;

use Noem\State\Feature\Async\AsyncFeature;
use Noem\State\Feature\Async\Call;
use Noem\State\Feature\Async\IO\Load;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Async I/O operations complete without blocking scheduler
 */
#[Group('async'), Group('integration')]
class NonBlockingIOTest extends TestCase
{
    public function testAsyncIOCompletesWithoutBlockingScheduler(): void
    {
        $builder = new RegionBuilder();
        $builder->enableFeatures(new AsyncFeature());

        $log = [];

        // Create a temporary file for testing
        $tempFile = tempnam(sys_get_temp_dir(), 'async_test_');
        file_put_contents($tempFile, 'test content');

        try {
            $region = $builder
                ->setStates('active')
                ->onAction('active', function (object $event) use (&$log, $tempFile) {
                    $log[] = 'io-start';
                    yield;

                    // Start async file read - use buffer to collect yielded characters
                    $buffer = [];
                    yield Call::call(new Load($tempFile), $buffer);
                    $content = implode('', $buffer);

                    $log[] = 'io-complete';
                    $event->content = $content;
                    yield;
                })
                ->onAction('active', function (object $event) use (&$log) {
                    // This task should be able to progress while IO is happening
                    $log[] = 'other-task-1';
                    yield;
                    $log[] = 'other-task-2';
                    yield;
                    $log[] = 'other-task-3';
                })
                ->build();

            $event = (object)['content' => null];

            // Execute multiple triggers to allow I/O and other tasks to progress
            for ($i = 0; $i < 20; $i++) {
                $region->trigger($event);
            }

            // Verify I/O completed
            $this->assertContains('io-start', $log);
            $this->assertContains('io-complete', $log);
            $this->assertSame('test content', $event->content);

            // Verify other task also progressed (demonstrating non-blocking)
            $this->assertContains('other-task-1', $log);
            $this->assertContains('other-task-2', $log);
        } finally {
            unlink($tempFile);
        }
    }

    public function testMultipleIOOperationsExecuteConcurrently(): void
    {
        $builder = new RegionBuilder();
        $builder->enableFeatures(new AsyncFeature());

        $results = [];

        // Create temporary files
        $file1 = tempnam(sys_get_temp_dir(), 'async1_');
        $file2 = tempnam(sys_get_temp_dir(), 'async2_');
        file_put_contents($file1, 'content1');
        file_put_contents($file2, 'content2');

        try {
            $region = $builder
                ->setStates('active')
                ->onAction('active', function (object $event) use (&$results, $file1) {
                    $buffer = [];
                    yield Call::call(new Load($file1), $buffer);
                    $results['file1'] = implode('', $buffer);
                })
                ->onAction('active', function (object $event) use (&$results, $file2) {
                    $buffer = [];
                    yield Call::call(new Load($file2), $buffer);
                    $results['file2'] = implode('', $buffer);
                })
                ->build();

            // Execute triggers
            for ($i = 0; $i < 20; $i++) {
                $region->trigger(new \stdClass());
            }

            // Both I/O operations should have completed
            $this->assertArrayHasKey('file1', $results);
            $this->assertArrayHasKey('file2', $results);
            $this->assertSame('content1', $results['file1']);
            $this->assertSame('content2', $results['file2']);
        } finally {
            unlink($file1);
            unlink($file2);
        }
    }
}
