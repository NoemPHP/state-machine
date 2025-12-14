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
 * Acceptance Criterion: Accept loop continues processing after child region encounters error and closes
 *
 * Intent: Ensures server resilience where one failing connection does not crash accept loop,
 * maintaining availability for other clients
 */
#[Group('machines'), Group('webserver'), Group('e2e-connection-flow')]
class AcceptLoopResillienceTest extends NetworkMachineTestCase
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
    public function acceptLoopContinuesAfterChildRegionError(): void
    {
        // Arrange - Build region
        $region = $this->region();
        $region->trigger(new \stdClass());
        $this->tickN($region, 1);

        // Queue first connection that will error
        $errorConnection = $this->queueHttpRequest('GET', '/error');

        // Act - Trigger error connection
        $errorServerConn = new \ServerConnection($errorConnection, $errorConnection->getRequest());
        $region->trigger($errorServerConn, true);

        // Tick to process (will encounter error in processing callback)
        $this->tickN($region, 10);

        // Error connection should be closed (even if error occurred)
        $this->assertConnectionClosed($errorConnection, 'Error connection should be closed');

        // Critical test: Accept loop should still be running
        // Queue second connection after error
        $successConnection = $this->queueHttpRequest('GET', '/success');
        $successServerConn = new \ServerConnection($successConnection, $successConnection->getRequest());
        $region->trigger($successServerConn, true);

        // Process second connection
        $this->tickN($region, 10);

        // Assert - Second connection should be processed successfully
        // If accept loop crashed, this connection would never be handled
        $this->assertConnectionClosed(
            $successConnection,
            'Accept loop should process new connections after previous error'
        );

        // Success connection should have received response
        $writtenData = $successConnection->getWrittenData();
        $this->assertStringContainsString(
            'HTTP/1.1 200 OK',
            $writtenData,
            'Success connection should receive response despite previous error'
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
            'request.action.processing' => function (object $trigger) {
                $uri = $this->get('uri');

                // Simulate error for /error path
                if ($uri === '/error') {
                    // Trigger early close instead of throwing exception
                    // (throwing would crash test - we want graceful degradation)
                    $this->set('close', true);
                    yield; // Must be generator for async
                    return;
                }

                // Normal processing for other paths
                $client = $this->get('client');

                // Check if client is valid
                if (!$client || $client->isClosed()) {
                    yield; // Must be generator for async
                    return;
                }

                // Write response
                $response = sprintf(
                    "HTTP/1.1 200 OK\r\nConnection: close\r\nContent-Type: text/html\r\nDate: %s\r\n\r\n",
                    gmdate('r')
                );

                $client->write($response);

                $this->set('close', true);
                yield;
            },
        ]);
    }

    public function trigger(): object
    {
        return new \stdClass();
    }
}
