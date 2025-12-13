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
 * Acceptance Criterion: Connection processing handler yields control while generating response
 */
#[Group('machines'), Group('webserver'), Group('async-behavior')]
class ConnectionYieldBehaviorTest extends NetworkMachineTestCase
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
    public function connectionProcessingYieldsControlDuringResponseGeneration(): void
    {
        // Arrange
        $executionSteps = [];

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
                            'processing.action' => function(object $trigger) use (&$executionSteps) {
                                $executionSteps[] = 'step1';
                                yield;  // Yield control
                                $executionSteps[] = 'step2';
                                yield;  // Yield again
                                $executionSteps[] = 'step3';
                            },
                        ])
                    ]
                ]
            ]);

        // Act - Trigger multiple times to advance through yields
        $region->trigger(new \stdClass());
        $this->assertEquals(['step1'], $executionSteps, 'After 1st trigger: should execute step1 and yield');

        $region->trigger(new \stdClass());
        $this->assertEquals(['step1', 'step2'], $executionSteps, 'After 2nd trigger: should resume and execute step2');

        $region->trigger(new \stdClass());
        $this->assertEquals(['step1', 'step2', 'step3'], $executionSteps, 'After 3rd trigger: should complete execution');
    }

    public function yaml(): string
    {
        return <<<YAML
states:
  - name: processing
    action:
      - run: !get processing.action
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
