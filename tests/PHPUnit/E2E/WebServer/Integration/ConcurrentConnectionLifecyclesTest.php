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
 * Acceptance Criterion: Multiple concurrent connections with different lifecycles do not interfere
 *
 * Intent: Ensures fast-completing request does not affect slow-processing request, and vice versa,
 * validating independent lifecycle management
 */
#[Group('machines'), Group('webserver'), Group('resource-lifecycle-integration')]
class ConcurrentConnectionLifecyclesTest extends NetworkMachineTestCase
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
    public function concurrentConnectionsWithDifferentLifecyclesDoNotInterfere(): void
    {
        // Arrange - Build region and start accept loop
        $region = $this->region();
        $region->trigger(new \stdClass());
        $this->tickN($region, 1);

        // Queue two connections: fast and slow
        $fastConnection = $this->queueHttpRequest('GET', '/fast');
        $slowConnection = $this->queueHttpRequest('GET', '/slow');

        // Track which connection should close based on URI
        $shouldClose = [];

        // Act - Dispatch both connections
        $fastServerConn = new \ServerConnection($fastConnection, $fastConnection->getRequest());
        $slowServerConn = new \ServerConnection($slowConnection, $slowConnection->getRequest());

        $region->trigger($fastServerConn, true);
        $region->trigger($slowServerConn, true);

        // Store connection URIs in extended state to control lifecycle
        $shouldClose['/fast'] = true;  // Fast connection closes immediately
        $shouldClose['/slow'] = false; // Slow connection stays open

        // Tick a few times - fast connection should close, slow should remain open
        $this->tickN($region, 5);

        // Assert - Fast connection closed, slow connection still open
        $this->assertConnectionClosed($fastConnection, 'Fast connection should close quickly');
        $this->assertConnectionOpen($slowConnection, 'Slow connection should remain open while fast closes');

        // Continue ticking - slow connection should eventually be closable
        $shouldClose['/slow'] = true;
        $this->tickN($region, 10);

        // Both connections eventually close
        $this->assertConnectionClosed($slowConnection, 'Slow connection should eventually close');
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
        // Track connection lifecycles independently
        $connectionStates = [];

        return $this->getWebServerCallbacks($this->mockSocket, [
            'request.action.accept' => function (\ServerConnection $connection) use (&$connectionStates) {
                $uri = $connection->uri;
                $client = $connection->client;

                // Initialize connection state
                $connectionStates[$uri] = [
                    'ticks' => 0,
                    'shouldClose' => $uri === '/fast', // Fast closes immediately
                ];

                $this->set('client', $client);
                $this->set('uri', $uri);
                $this->set('headers', []);
                $this->set('body', '');
            },
            'transition.guard.processing.close' => function (object $trigger) use (&$connectionStates): bool {
                $uri = $this->get('uri');

                if (!isset($connectionStates[$uri])) {
                    return false;
                }

                // Increment ticks for this connection
                $connectionStates[$uri]['ticks']++;

                // Fast connection closes after 1 tick, slow after 10 ticks
                if ($uri === '/fast') {
                    return $connectionStates[$uri]['ticks'] >= 1;
                }

                return $connectionStates[$uri]['ticks'] >= 10;
            },
        ]);
    }

    public function trigger(): object
    {
        return new \stdClass();
    }
}
