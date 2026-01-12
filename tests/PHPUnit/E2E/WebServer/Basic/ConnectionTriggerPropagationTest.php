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
 * Acceptance Criterion: Spawned connection region receives the ServerConnection trigger
 */
#[Group('machines'), Group('webserver'), Group('connection-spawning')]
class ConnectionTriggerPropagationTest extends NetworkMachineTestCase
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
    public function spawnedConnectionRegionReceivesServerConnectionTrigger(): void
    {
        // Arrange
        $spawnGuardReceived = null;
        $childActionReceived = null;

        $region = $this->builder
            ->enableFeatures(
                new RegionLoader(),
                new ExtendedState(),
                new OrthogonalRegions(),
            )
            ->build([
                'loader' => [
                    'yaml' => $this->yaml(),
                    'yamlHelpers' => [
                        'php' => new \Noem\State\Feature\Loader\Helper\PhpEvalHelper(),
                        'get' => new \Noem\State\Feature\Loader\Helper\ContainerGetHelper([
                            'spawn.guard' => function (object $trigger) use (&$spawnGuardReceived): bool {
                                if ($trigger instanceof \ServerConnection) {
                                    // Only capture ServerConnection triggers (not subsequent tick triggers)
                                    $spawnGuardReceived = $trigger;
                                    return true;
                                }
                                return false;
                            },
                            'child.action' => function (object $trigger) use (&$childActionReceived) {
                                if ($trigger instanceof \ServerConnection) {
                                    // Only capture ServerConnection triggers
                                    $childActionReceived = $trigger;
                                }
                            },
                        ])
                    ]
                ]
            ]);

        $connection = $this->queueHttpRequest();
        $serverConnection = new \ServerConnection($connection, $connection->getRequest());

        // Act - Trigger with ServerConnection
        $region->trigger($serverConnection, true);

        // Give async processing time
        $this->tickN($region, 5);

        // Assert - Spawn guard should receive the ServerConnection
        $this->assertNotNull($spawnGuardReceived, 'Spawn guard should have been called');
        $this->assertInstanceOf(
            \ServerConnection::class,
            $spawnGuardReceived,
            'Spawn guard should receive ServerConnection trigger. Received: ' . get_debug_type($spawnGuardReceived)
        );

        $this->assertEquals(
            $serverConnection->method ?? null,
            $spawnGuardReceived->method ?? null,
            'ServerConnection method should match'
        );

        // The spawned child region should also receive the trigger in its action
        $this->assertNotNull($childActionReceived, 'Child action should have been called');
        $this->assertInstanceOf(
            \ServerConnection::class,
            $childActionReceived,
            'Spawned connection region should receive ServerConnection trigger in its action. Received: ' . get_debug_type($childActionReceived)
        );

        $this->assertEquals(
            $serverConnection->uri ?? null,
            $childActionReceived->uri ?? null,
            'ServerConnection URI should match'
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
              action:
                - run: !get child.action
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
