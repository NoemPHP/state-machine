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
 * Acceptance Criterion: Server spawns child region when ServerConnection trigger is dispatched
 */
#[Group('machines'), Group('webserver'), Group('connection-spawning')]
class ConnectionSpawningTest extends NetworkMachineTestCase
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
    public function serverSpawnsChildRegionForConnection(): void
    {
        // Arrange - Create region and queue a connection
        $region = $this->region();
        $connection = $this->queueHttpRequest();

        // Track spawned regions
        $spawnedRegions = [];

        // Act - Simulate server accepting connection and dispatching ServerConnection trigger
        // We need to manually dispatch since we're not running the full server loop
        $serverConnection = new \ServerConnection($connection, $connection->getRequest());
        $region->dispatch($serverConnection);

        // Give scheduler time to process spawning
        $this->tickN($region, 5);

        // Assert - A child region should have been spawned
        // We verify this by checking if the region has orthogonal children
        $reflection = new \ReflectionClass($region);
        $property = $reflection->getProperty('orthogonalRegions');
        $property->setAccessible(true);
        $orthogonalRegions = $property->getValue($region);

        $this->assertNotEmpty(
            $orthogonalRegions,
            'Server should spawn a child region when ServerConnection is dispatched'
        );
    }

    public function yaml(): string
    {
        return <<<YAML
states:
  - name: starting
    spawn:
      - guard: !php |
          return function(\$c):bool{
            return \$c instanceof ServerConnection;
          }
        region:
          states:
            - name: accept
  - name: finished
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
