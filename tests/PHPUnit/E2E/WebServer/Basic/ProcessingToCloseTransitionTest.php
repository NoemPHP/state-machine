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
 * Acceptance Criterion: Connection transitions from processing to close when response complete
 */
#[Group('machines'), Group('webserver'), Group('connection-lifecycle')]
class ProcessingToCloseTransitionTest extends NetworkMachineTestCase
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
    public function connectionTransitionsFromProcessingToCloseWhenComplete(): void
    {
        // Arrange
        $closeEntered = false;
        $processingCount = 0;

        $region = $this->builder
            ->enableFeatures(
                new RegionLoader(),
                new ExtendedState(),
                new \Noem\State\Feature\Transitions\TransitionsFeature(),
            )
            ->build([
                'loader' => [
                    'yaml' => $this->yaml(),
                    'yamlHelpers' => [
                        'get' => new \Noem\State\Feature\Loader\Helper\ContainerGetHelper([
                            'processing.action' => function(object $trigger) use (&$processingCount) {
                                // Simulate multi-step response generation
                                $processingCount++;
                                if ($processingCount >= 2) {
                                    // Response generation complete after second trigger
                                    $this->set('responseComplete', true);
                                }
                            },
                            'processing.guard' => function(object $trigger): bool {
                                // Transition when response is complete
                                return $this->get('responseComplete', false) === true;
                            },
                            'close.onEnter' => function(object $trigger) use (&$closeEntered) {
                                $closeEntered = true;
                            },
                        ])
                    ]
                ]
            ]);

        // Act - Enter processing state (first processing)
        $region->trigger(new \stdClass());
        $this->assertFalse($closeEntered, 'Should not be in close state after first trigger');
        $this->assertEquals(1, $processingCount, 'Processing action should have run once');

        // Continue processing (marks response complete and transitions)
        $region->trigger(new \stdClass());

        // Assert - Should have transitioned to close after response complete
        $this->assertTrue($closeEntered, 'Should transition to close state when response complete');
        $this->assertEquals(2, $processingCount, 'Processing action should have run twice before transition');
    }

    public function yaml(): string
    {
        return <<<YAML
states:
  - name: processing
    action:
      - run: !get processing.action
    transitions:
      - target: close
        guard: !get processing.guard
  - name: close
    onEnter:
      - run: !get close.onEnter
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
