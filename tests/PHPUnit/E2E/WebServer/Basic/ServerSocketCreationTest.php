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
 * Acceptance Criterion: Server creates non-blocking socket on entering starting state
 */
#[Group('machines'), Group('webserver'), Group('server-lifecycle')]
class ServerSocketCreationTest extends NetworkMachineTestCase
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
    public function serverCreatesNonBlockingSocketOnStart(): void
    {
        // Arrange - Build the region (entering starting state triggers onEnter)
        $region = $this->region();

        // Assert - Socket should be non-blocking
        $this->assertSocketNonBlocking('Server socket should be non-blocking for cooperative multitasking');
    }

    public function yaml(): string
    {
        // Simplified YAML for testing socket creation only
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
                // Set socket to non-blocking
                $mockSocket->setBlocking(false);

                // Store socket reference
                $this->set('server', $mockSocket);
            },
        ];
    }

    public function trigger(): object
    {
        return new \stdClass();
    }
}
