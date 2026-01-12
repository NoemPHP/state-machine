<?php

declare(strict_types=1);

namespace Noem\State\Test\E2E\WebServer\Basic;

use Noem\State\Feature\Ai\AiFeature;
use Noem\State\Feature\Async\AsyncFeature;
use Noem\State\Feature\ExtendedState\ExtendedState;
use Noem\State\Feature\JsonSchema\JsonSchemaFeature;
use Noem\State\Feature\Loader\RegionLoader;
use Noem\State\Feature\OrthogonalRegions\OrthogonalRegions;
use Noem\State\Feature\Template\TemplateFeature;
use Noem\State\Test\E2E\NetworkMachineTestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * Acceptance Criterion: Multiple connections progress concurrently through their states
 */
#[Group('machines'), Group('webserver'), Group('async-behavior')]
class ConcurrentProgressTest extends NetworkMachineTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->builder->enableFeatures(
            new RegionLoader(),
            new ExtendedState(),
            new TemplateFeature(),
            new AiFeature(),
            new AsyncFeature(),
            new OrthogonalRegions(),
            new JsonSchemaFeature(),
        );
    }

    #[Test]
    public function multipleConnectionsProgressConcurrently(): void
    {
        // Arrange
        $execution = [];

        $region = $this->builder
            ->enableFeatures(
                new RegionLoader(),
                new ExtendedState(),
                new AsyncFeature(),
            )
            ->build([
                'loader' => [
                    'yaml' => $this->yaml(),
                    'yamlHelpers' => [
                        'get' => new \Noem\State\Feature\Loader\Helper\ContainerGetHelper([
                            'task1.action' => function (object $trigger) use (&$execution) {
                                $execution[] = 'task1-start';
                                yield;
                                $execution[] = 'task1-end';
                            },
                            'task2.action' => function (object $trigger) use (&$execution) {
                                $execution[] = 'task2-start';
                                yield;
                                $execution[] = 'task2-end';
                            },
                        ])
                    ]
                ]
            ]);

        // Act - Single trigger should start both tasks
        $region->trigger(new \stdClass());

        // Assert - Both tasks started (concurrent execution)
        $this->assertContains('task1-start', $execution, 'Task 1 should start');
        $this->assertContains('task2-start', $execution, 'Task 2 should start');

        // Both should yield, not complete yet
        $this->assertNotContains('task1-end', $execution, 'Task 1 should not complete yet');
        $this->assertNotContains('task2-end', $execution, 'Task 2 should not complete yet');

        // Next trigger should resume both tasks
        $region->trigger(new \stdClass());

        // Assert - Both tasks completed concurrently
        $this->assertContains('task1-end', $execution, 'Task 1 should complete');
        $this->assertContains('task2-end', $execution, 'Task 2 should complete');

        // Verify concurrent behavior (both started before either ended)
        $task1StartPos = array_search('task1-start', $execution);
        $task2StartPos = array_search('task2-start', $execution);
        $task1EndPos = array_search('task1-end', $execution);
        $task2EndPos = array_search('task2-end', $execution);

        $this->assertTrue(
            $task1StartPos < $task1EndPos && $task2StartPos < $task2EndPos,
            'Both tasks should progress concurrently (start before ending)'
        );
    }

    public function yaml(): string
    {
        return <<<YAML
states:
  - name: active
    action:
      - run: !get task1.action
        async:
          enabled: true
          priority: low
      - run: !get task2.action
        async:
          enabled: true
          priority: low
YAML;
    }

    public function container(): iterable
    {
        return [];
    }

    public function trigger(): object
    {
        return new \stdClass();
    }
}
