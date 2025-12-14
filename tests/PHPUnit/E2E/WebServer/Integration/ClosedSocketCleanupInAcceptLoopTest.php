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
 * Acceptance Criterion: Parent accept loop removes closed client sockets from tracking after child region closes them
 *
 * Intent: Prevents stream_select failures when child region closes socket but parent still references it,
 * ensuring accept loop remains healthy
 */
#[Group('machines'), Group('webserver'), Group('resource-lifecycle-integration')]
class ClosedSocketCleanupInAcceptLoopTest extends NetworkMachineTestCase
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
    public function acceptLoopRemovesClosedSocketsFromTracking(): void
    {
        // Arrange - Queue a connection that will be closed by child region
        $connection = $this->queueHttpRequest('GET', '/test');

        // Build region and trigger initial setup
        $region = $this->region();
        $region->trigger(new \stdClass());

        // Tick once to let accept loop start
        $this->tickN($region, 1);

        // Act - Dispatch ServerConnection to spawn child region that will close the socket
        $serverConnection = new \ServerConnection($connection, $connection->getRequest());
        $region->trigger($serverConnection, true);

        // Let child region process through accept -> processing -> close states
        // This should close the socket in 'request.onEnter.close' callback
        $this->tickN($region, 10);

        // Assert - Connection should be closed by child region
        $this->assertConnectionClosed($connection, 'Child region should close socket in close state');

        // Critical test: Accept loop continues without stream_select errors
        // If parent didn't remove closed socket from $clients array, next iteration would crash
        // Tick a few more times to verify accept loop can continue
        $this->tickN($region, 5);

        // If we reach here without exceptions, accept loop successfully handled closed socket
        $this->assertTrue(true, 'Accept loop continued after child closed socket');
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
                // Immediately allow transition to close to test cleanup
                return true;
            },
        ]);
    }

    public function trigger(): object
    {
        return new \stdClass();
    }
}
