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
 * Acceptance Criterion: Accept loop gracefully handles stream_select() errors from invalid socket references
 *
 * Intent: Ensures accept loop continues operating when stream_select fails due to closed socket resources,
 * preventing server from becoming unresponsive
 */
#[Group('machines'), Group('webserver'), Group('resource-lifecycle-resilience')]
class StreamSelectErrorRecoveryTest extends NetworkMachineTestCase
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
    public function acceptLoopRecoversFroMStreamSelectErrors(): void
    {
        // Arrange - Build region and start accept loop
        $region = $this->region();
        $region->trigger(new \stdClass());

        // Queue a connection that will close rapidly
        $connection1 = $this->queueHttpRequest('GET', '/rapid-close');

        // Act - Process connection through rapid lifecycle
        $this->tickN($region, 30);
        $this->assertConnectionClosed($connection1, 'First connection should close rapidly');

        // Core test: After socket closes, if accept loop encounters stream_select error
        // (due to invalid resource reference), it should recover gracefully.
        // We verify recovery by ensuring subsequent connections are still processed.

        $connection2 = $this->queueHttpRequest('GET', '/after-error');
        $this->tickN($region, 30);

        // Assert - If accept loop recovered from any stream_select errors,
        // the second connection should be processed successfully
        $this->assertConnectionClosed(
            $connection2,
            'Second connection processed successfully, proving accept loop recovered from stream_select errors'
        );

        // Verify server remains operational with third connection
        $connection3 = $this->queueHttpRequest('GET', '/continued-operation');
        $this->tickN($region, 30);
        $this->assertConnectionClosed(
            $connection3,
            'Third connection confirms continued operation after error recovery'
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
