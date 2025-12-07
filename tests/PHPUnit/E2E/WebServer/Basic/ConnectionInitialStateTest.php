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
 * Acceptance Criterion: Connection region initializes in accept state
 */
#[Group('machines'), Group('webserver'), Group('connection-lifecycle')]
class ConnectionInitialStateTest extends NetworkMachineTestCase
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
    public function connectionStartsInAcceptState(): void
    {
        // Arrange - Build a connection region directly (without spawning)
        $region = $this->region();

        // Assert - Region should be in accept state initially
        $this->assertTrue(
            $region->isInState('accept'),
            'Connection region should initialize in accept state'
        );
    }

    public function yaml(): string
    {
        return <<<YAML
states:
  - name: accept
    transitions:
      - target: processing
  - name: processing
  - name: close
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
