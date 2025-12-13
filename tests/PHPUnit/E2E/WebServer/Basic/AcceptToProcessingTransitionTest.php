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
 * Acceptance Criterion: Connection transitions from accept to processing state
 */
#[Group('machines'), Group('webserver'), Group('connection-lifecycle')]
class AcceptToProcessingTransitionTest extends NetworkMachineTestCase
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
    public function connectionTransitionsFromAcceptToProcessing(): void
    {
        // Arrange
        $acceptEntered = false;
        $processingEntered = false;

        $region = $this->builder
            ->enableFeatures(
                new RegionLoader(),
                new ExtendedState(),
            )
            ->build([
                'loader' => [
                    'yaml' => $this->yaml(),
                    'yamlHelpers' => [
                        'get' => new \Noem\State\Feature\Loader\Helper\ContainerGetHelper([
                            'accept.onEnter' => function(object $trigger) use (&$acceptEntered) {
                                $acceptEntered = true;
                            },
                            'processing.onEnter' => function(object $trigger) use (&$processingEntered) {
                                $processingEntered = true;
                            },
                        ])
                    ]
                ]
            ]);

        // Act - Trigger to enter accept state
        $region->trigger(new \stdClass());
        $this->assertTrue($acceptEntered, 'Should enter accept state');

        // Trigger again to transition to processing
        $region->trigger(new \stdClass());
        $this->assertTrue($processingEntered, 'Should transition to processing state');
    }

    public function yaml(): string
    {
        return <<<YAML
states:
  - name: accept
    onEnter:
      - run: !get accept.onEnter
    transitions:
      - target: processing
  - name: processing
    onEnter:
      - run: !get processing.onEnter
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
