<?php

declare(strict_types=1);

namespace Noem\State\Test\E2E\WebServer\Integration;

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
 * Acceptance Criterion: Child region closing socket does not close parent server socket
 *
 * Intent: Prevents catastrophic failure where child's fclose() accidentally affects parent's listening socket,
 * maintaining server availability
 */
#[Group('machines'), Group('webserver'), Group('resource-lifecycle-integration')]
class ChildCloseDoesNotAffectServerSocketTest extends NetworkMachineTestCase
{
    use WebServerIntegrationTestTrait;

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
    public function childClosingClientSocketDoesNotAffectServerSocket(): void
    {
        // Arrange - Build region and start accept loop
        $region = $this->region();
        $region->trigger(new \stdClass());

        // Get reference to server socket
        $serverSocket = $this->mockSocket;

        // Queue two connections
        $connection1 = $this->queueHttpRequest('GET', '/first');
        $connection2 = $this->queueHttpRequest('GET', '/second');

        // Process both connections
        $this->tickN($region, 100);

        // Assert - Both connections should be closed
        $this->assertConnectionClosed($connection1, 'First client socket should be closed');
        $this->assertConnectionClosed($connection2, 'Second client socket should be closed');

        // Server socket should still be valid and non-blocking
        $this->assertFalse(
            $serverSocket->isClosed(),
            'Server socket should remain open after children close client sockets'
        );
        $this->assertSocketNonBlocking('Server socket should remain non-blocking');

        // Core test: Child region closing its client socket does not affect parent server socket
        // If it did, second connection could not have been accepted and closed
        $this->assertTrue(true, 'Server socket remains operational after child closes client socket');
    }

    public function yaml(): string
    {
        return <<<YAML
states:
  - name: starting
    spawn:
      - guard: !php |
          return function(\ServerConnection \$c): bool {
            return true;
          }
        region:
          states:
            - name: accept
              transitions:
                - target: processing
              action:
                - run: !get request.action.accept
            - name: processing
              transitions:
                - target: close
                  guard: !get transition.guard.processing.close
            - name: close
              onEnter:
                - run: !get request.onEnter.close
    onEnter:
      - run: !get server.starting.onEnter
    action:
      - run: !get server.starting.accept
        async:
          enabled: true
          singleton: true
  - name: finished
YAML;
    }

    public function container(): iterable
    {
        return $this->getWebServerCallbacks($this->mockSocket, [
            'transition.guard.processing.close' => function (object $trigger): bool {
                return true; // Close immediately to test server socket isolation
            },
        ]);
    }

    public function trigger(): object
    {
        return new \stdClass();
    }
}
