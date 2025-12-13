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
 * Acceptance Criterion: Multiple connections spawn independent child regions
 */
#[Group('machines'), Group('webserver'), Group('connection-spawning')]
class MultipleConnectionSpawningTest extends NetworkMachineTestCase
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
    public function multipleConnectionsSpawnIndependentChildRegions(): void
    {
        // Arrange
        $spawnedConnections = [];

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
                            'spawn.guard' => function(object $trigger) use (&$spawnedConnections): bool {
                                if ($trigger instanceof \ServerConnection) {
                                    $spawnedConnections[] = $trigger;
                                    return true;
                                }
                                return false;
                            },
                        ])
                    ]
                ]
            ]);

        $connection1 = $this->queueHttpRequest('GET', '/page1');
        $connection2 = $this->queueHttpRequest('POST', '/api/data');
        $connection3 = $this->queueHttpRequest('GET', '/page2');

        // Act - Trigger multiple ServerConnections
        $serverConn1 = new \ServerConnection($connection1, $connection1->getRequest());
        $serverConn2 = new \ServerConnection($connection2, $connection2->getRequest());
        $serverConn3 = new \ServerConnection($connection3, $connection3->getRequest());

        $region->trigger($serverConn1, true);
        $this->tickN($region, 2);

        $region->trigger($serverConn2, true);
        $this->tickN($region, 2);

        $region->trigger($serverConn3, true);
        $this->tickN($region, 2);

        // Assert - All three connections should have spawned
        $this->assertCount(
            3,
            $spawnedConnections,
            'Three independent connections should spawn three child regions'
        );

        $this->assertSame($serverConn1, $spawnedConnections[0]);
        $this->assertSame($serverConn2, $spawnedConnections[1]);
        $this->assertSame($serverConn3, $spawnedConnections[2]);
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
