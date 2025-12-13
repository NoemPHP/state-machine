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
 * Acceptance Criterion: Server stores socket reference in extended state
 */
#[Group('machines'), Group('webserver'), Group('server-lifecycle')]
class ServerSocketStorageTest extends NetworkMachineTestCase
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
    public function serverStoresSocketInExtendedState(): void
    {
        // Arrange - Build the region
        $region = $this->region();

        // Act - Trigger once to enter initial state and execute onEnter callback
        $region->trigger(new \stdClass());

        // Assert - Socket should be accessible via extended state
        $this->assertRegionContext(
            $region,
            'server',
            $this->mockSocket,
            'Server socket should be stored in extended state for access by action handlers'
        );
    }

    public function yaml(): string
    {
        return <<<YAML
states:
  - name: starting
    onEnter:
      - run: !get server.starting.onEnter
  - name: finished
YAML;
    }

    public function container(): iterable
    {
        $mockSocket = $this->mockSocket;

        return [
            'server.starting.onEnter' => function (object $trigger) use ($mockSocket) {
                $mockSocket->setBlocking(false);
                $this->set('server', $mockSocket);
            },
        ];
    }

    public function trigger(): object
    {
        return new \stdClass();
    }
}
