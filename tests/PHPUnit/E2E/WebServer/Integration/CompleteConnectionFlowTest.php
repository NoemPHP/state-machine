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
 * Acceptance Criterion: Complete request-response cycle maintains socket validity from accept through close
 *
 * Intent: Validates entire flow where socket is accepted, stored in extended state, used for response writing,
 * and properly closed, demonstrating all features working together
 */
#[Group('machines'), Group('webserver'), Group('e2e-connection-flow')]
class CompleteConnectionFlowTest extends NetworkMachineTestCase
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
    public function completeRequestResponseCycleMaintainsSocketValidity(): void
    {
        // Arrange - Build region and start accept loop
        $region = $this->region();
        $region->trigger(new \stdClass());

        // Queue HTTP request - accept loop will pick it up
        $connection = $this->queueHttpRequest('GET', '/complete-flow');

        // Act - Tick to let accept loop process connection through full lifecycle
        // Accept loop: detect connection, dispatch ServerConnection, spawn child region
        // Child region: accept -> processing (async, yields) -> close
        $this->tickN($region, 20);

        // Assert - Response should be written and socket properly closed
        $writtenData = $connection->getWrittenData();

        // Verify response headers were written (proves socket was valid during processing)
        $this->assertStringContainsString(
            'HTTP/1.1 200 OK',
            $writtenData,
            'Response headers should be written to socket'
        );
        $this->assertStringContainsString(
            'Connection: close',
            $writtenData,
            'Connection close header should be present'
        );

        // Verify socket was closed at end of lifecycle
        $this->assertConnectionClosed($connection, 'Socket should be closed after complete flow');

        // Note: Body content depends on context schema initialization
        // which is tested separately. This test focuses on socket lifecycle.
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
                default: "<html><body>Test Response</body></html>"
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
        return $this->getWebServerCallbacks($this->mockSocket);
    }

    public function trigger(): object
    {
        return new \stdClass();
    }
}
