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
 * Acceptance Criterion: Accept loop removes closed client socket references before child region completes cleanup
 *
 * Intent: Prevents stream_select() failure when given invalid stream resources by ensuring parent loop
 * cleans up references before socket closure propagates
 */
#[Group('machines'), Group('webserver'), Group('resource-lifecycle-resilience')]
class ResourceLifecycleTimingTest extends NetworkMachineTestCase
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
    public function acceptLoopRemovesClosedSocketReferencesBeforeChildCleanupCompletes(): void
    {
        // Arrange - Build region and start accept loop
        $region = $this->region();
        $region->trigger(new \stdClass());

        // Queue a connection that will close immediately
        $connection = $this->queueHttpRequest('GET', '/test');

        // Act - Process through ticks
        // The accept loop should detect the closed socket and remove it from tracking
        // BEFORE the next stream_select() call
        $this->tickN($region, 50);

        // Assert - Connection should be closed
        $this->assertConnectionClosed($connection, 'Connection should be closed');

        // Core test: If accept loop didn't clean up closed socket references,
        // the next stream_select() would fail with invalid resource error.
        // We verify by ensuring the accept loop is still responsive after closure.

        // Queue another connection after the first closed
        $connection2 = $this->queueHttpRequest('GET', '/after-close');
        $this->tickN($region, 50);

        // If cleanup worked, second connection should be processed
        $this->assertConnectionClosed(
            $connection2,
            'Second connection should be processed after first closed, proving accept loop cleaned up properly'
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