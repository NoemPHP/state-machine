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
 * Acceptance Criterion: Server accepts new connections after previous connections close
 *
 * Intent: Validates server remains operational and responsive after handling and closing connections,
 * demonstrating proper lifecycle management
 */
#[Group('machines'), Group('webserver'), Group('resource-lifecycle-integration')]
class AcceptAfterCloseTest extends NetworkMachineTestCase
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
    public function serverAcceptsNewConnectionAfterPreviousConnectionCloses(): void
    {
        // Arrange - Build region and start accept loop
        $region = $this->region();
        $region->trigger(new \stdClass());

        // Queue two connections to test sequential processing
        $connection1 = $this->queueHttpRequest('GET', '/first');
        $connection2 = $this->queueHttpRequest('GET', '/second');

        // Act - Process through many ticks to handle both connections
        // This tests that after first connection closes, server can still accept second
        $this->tickN($region, 100);

        // Assert - Both connections should eventually be closed
        $this->assertConnectionClosed($connection1, 'First connection should be closed');
        $this->assertConnectionClosed($connection2, 'Second connection should be closed');

        // Core test: If accept loop died after first connection,
        // second connection would never be accepted or processed.
        // The fact that both are closed proves the accept loop continued.
        $this->assertTrue(true, 'Accept loop processed second connection after first closed');
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
