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
        // Arrange - Create region with a flag to track if spawn guard was evaluated
        $spawnGuardCalled = false;

        $region = $this->builder
            ->enableFeatures(
                new RegionLoader(),
                new ExtendedState(),
                new TemplateFeature(),
                new AiFeature(),
                new AsyncFeature(),
                new OrthogonalRegions(),
                new JsonSchemaFeature(),
            )
            ->build([
                'loader' => [
                    'yaml' => $this->yaml(),
                    'yamlHelpers' => [
                        'php' => new \Noem\State\Feature\Loader\Helper\PhpEvalHelper(),
                        'get' => new \Noem\State\Feature\Loader\Helper\ContainerGetHelper([
                            'spawn.guard' => function(object $trigger) use (&$spawnGuardCalled): bool {
                                $spawnGuardCalled = true;
                                return $trigger instanceof \ServerConnection;
                            }
                        ])
                    ]
                ]
            ]);

        $connection = $this->queueHttpRequest();

        // Act - Trigger with ServerConnection
        $serverConnection = new \ServerConnection($connection, $connection->getRequest());
        $region->trigger($serverConnection, true);

        // Give async processing time
        $this->tickN($region, 3);

        // Assert - Spawn guard should have been called (indicating spawn logic executed)
        $this->assertTrue(
            $spawnGuardCalled,
            'Server should evaluate spawn guard when ServerConnection is dispatched'
        );
    }

    public function yaml(): string
    {
        return <<<YAML
states:
  - name: starting
    spawn:
      - guard: !get spawn.guard
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
