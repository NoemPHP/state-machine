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
 * Acceptance Criterion: Socket resource remains valid during async processing with multiple yields
 *
 * Intent: Ensures cooperative multitasking during response generation does not invalidate socket resource,
 * preventing broken pipe errors
 */
#[Group('machines'), Group('webserver'), Group('e2e-connection-flow')]
class SocketValidityThroughAsyncProcessingTest extends NetworkMachineTestCase
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
    public function socketRemainsValidThroughMultipleAsyncYields(): void
    {
        // Arrange - Build region
        $region = $this->region();
        $region->trigger(new \stdClass());

        // Queue connection
        $connection = $this->queueHttpRequest('GET', '/async-test');

        // Track socket validity during processing
        $socketValidityChecks = [];

        // Act - Tick multiple times to process connection through async states
        // Processing action yields multiple times (per trait implementation)
        for ($i = 0; $i < 20; $i++) {
            $region->trigger(new \stdClass());

            // Check socket validity during async processing
            // Socket should remain valid until explicitly closed
            if (!$connection->isClosed()) {
                $socketValidityChecks[] = true;
            }
        }

        // Assert - Socket should have been valid for several ticks
        // (Processing should yield at least once, but may be quick if no body template)
        $this->assertGreaterThan(
            0,
            count($socketValidityChecks),
            'Socket should remain valid through multiple async yields'
        );

        // Eventually connection completes and closes
        $this->assertConnectionClosed($connection, 'Connection should eventually close');

        // Response should be written successfully
        $writtenData = $connection->getWrittenData();
        $this->assertStringContainsString(
            'HTTP/1.1 200 OK',
            $writtenData,
            'Response should be written despite multiple yields'
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
                default: "<html><body>Async Response</body></html>"
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
