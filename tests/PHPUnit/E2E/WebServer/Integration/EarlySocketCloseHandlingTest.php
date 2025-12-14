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
 * Acceptance Criterion: Child region can close socket early without causing parent accept loop stream_select failure
 *
 * Intent: Validates parent's resource tracking correctly handles premature socket closure,
 * the core bug that was discovered in production
 */
#[Group('machines'), Group('webserver'), Group('e2e-connection-flow')]
class EarlySocketCloseHandlingTest extends NetworkMachineTestCase
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
    public function parentAcceptLoopHandlesEarlySocketClosure(): void
    {
        // Arrange - Build region
        $region = $this->region();
        $region->trigger(new \stdClass());

        // Queue connection that will close early (before normal lifecycle completes)
        $connection = $this->queueHttpRequest('GET', '/early-close');

        // Act - Let accept loop process connection
        // Accept state will close socket immediately due to /early-close path
        $this->tickN($region, 10);

        // Socket should be closed early (in accept state, not waiting for processing)
        $this->assertConnectionClosed($connection, 'Socket should close early in accept state');

        // Critical test: Accept loop continues without errors
        // This was the production bug - parent's $clients array still referenced closed socket
        // Next iteration of accept loop would fail on closed resource

        // Tick accept loop several more times
        // If bug exists, accept loop would crash
        $this->tickN($region, 10);

        // If we reach here, accept loop successfully cleaned up closed socket
        $this->assertTrue(true, 'Accept loop continued without error after early close');

        // Verify accept loop still accepts new connections
        $newConnection = $this->queueHttpRequest('GET', '/new');

        $this->tickN($region, 20);

        // New connection should be processed
        $this->assertConnectionClosed(
            $newConnection,
            'Accept loop should still accept new connections after early close'
        );

        // Verify response was written to new connection
        $this->assertStringContainsString(
            'HTTP/1.1 200 OK',
            $newConnection->getWrittenData(),
            'New connection should receive response'
        );
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
          context:
            schema:
              - name: bodyTemplate
                type: string
                default: "<html><body>Response</body></html>"
          states:
            - name: accept
              transitions:
                - target: processing
              action:
                - run: !get request.action.accept
            - name: processing
              action:
                - run: !get request.action.processing
                  async:
                    enabled: true
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
            'request.action.accept' => function (\ServerConnection $connection) {
                $uri = $connection->uri;
                $client = $connection->client;

                $this->set('client', $client);
                $this->set('uri', $uri);

                // Simulate early close for /early-close path
                if ($uri === '/early-close') {
                    // Close socket immediately in accept state
                    if ($client && !$client->isClosed()) {
                        $client->close();
                    }
                    return;
                }

                // Normal processing for other paths
                $this->set('headers', []);
                $this->set('body', '');
            },
            'transition.guard.processing.close' => function (object $trigger): bool {
                // Allow transition immediately for /new path
                $uri = $this->get('uri');
                return $uri === '/new';
            },
        ]);
    }

    public function trigger(): object
    {
        return new \stdClass();
    }
}
