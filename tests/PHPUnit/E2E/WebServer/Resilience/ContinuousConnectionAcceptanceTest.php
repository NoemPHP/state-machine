<?php

declare(strict_types=1);

namespace Noem\State\Test\E2E\WebServer\Resilience;

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
 * Acceptance Criterion: Server accepts new connections immediately after previous connection closes
 *
 * Intent: Validates server remains perpetually responsive by processing complete request-response cycles
 * without becoming unresponsive to subsequent connections
 */
#[Group('machines'), Group('webserver'), Group('resource-lifecycle-resilience')]
class ContinuousConnectionAcceptanceTest extends NetworkMachineTestCase
{
    use WebServerResilienceTestTrait;

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
    public function serverAcceptsConnectionsImmediatelyAfterPreviousClose(): void
    {
        // Arrange - Build region and start accept loop
        $region = $this->region();
        $region->trigger(new \stdClass());

        // Act & Assert - Process multiple sequential connections
        // Each connection should be accepted, processed, and closed
        // Server should remain responsive throughout

        $connection1 = $this->queueHttpRequest('GET', '/first');
        $this->tickN($region, 30); // Process first connection
        $this->assertConnectionClosed($connection1, 'First connection should be closed');

        // Immediately queue second connection after first closes
        $connection2 = $this->queueHttpRequest('GET', '/second');
        $this->tickN($region, 30); // Process second connection
        $this->assertConnectionClosed($connection2, 'Second connection should be accepted immediately after first closed');

        // Third connection to verify continuous operation
        $connection3 = $this->queueHttpRequest('GET', '/third');
        $this->tickN($region, 30); // Process third connection
        $this->assertConnectionClosed($connection3, 'Third connection should be accepted, proving continuous operation');

        // Core test: If accept loop became unresponsive after socket closures,
        // subsequent connections would never be processed.
        // The fact that all three connections were handled proves perpetual responsiveness.
        $this->assertTrue(true, 'Server maintained perpetual responsiveness through multiple connection lifecycles');
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
                return true; // Immediately close to test rapid lifecycle
            },
        ]);
    }

    public function trigger(): object
    {
        return new \stdClass();
    }
}
