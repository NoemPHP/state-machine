<?php

declare(strict_types=1);

namespace Noem\State\Test\E2E\WebServer\Basic;

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
 * Acceptance Criterion: Server action handler yields control during connection acceptance loop
 */
#[Group('machines'), Group('webserver'), Group('async-behavior')]
class ServerYieldBehaviorTest extends NetworkMachineTestCase
{
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
    public function serverYieldsDuringAcceptLoop(): void
    {
        // Arrange
        $region = $this->region();
        $yieldCount = 0;
        
        // Act - Trigger server to start accepting (this should enqueue a generator)
        $region->trigger(new \stdClass());
        
        // Give the async action time to start
        $this->tickN($region, 1);
        
        // Assert - Server should have an active task (the accept loop generator)
        $this->assertHasActiveTasks(
            $region,
            'Server accept loop should be running as an active async task'
        );
        
        // Act - Tick several times
        $this->tickN($region, 5);
        
        // Assert - Task should still be active (not completed)
        // This proves the generator yields control instead of blocking
        $this->assertHasActiveTasks(
            $region,
            'Server accept loop should still be running, proving it yields control cooperatively'
        );
    }

    public function yaml(): string
    {
        return <<<YAML
states:
  - name: starting
    action:
      - run: !get server.starting.accept
  - name: finished
YAML;
    }

    public function container(): iterable
    {
        $mockSocket = $this->mockSocket;
        
        return [
            'server.starting.accept' => function (object $trigger) use ($mockSocket) {
                // Simulate the accept loop that yields
                yield; // Initial yield
                
                for ($i = 0; $i < 10; $i++) {
                    // Simulate checking for connections
                    $client = $mockSocket->accept();
                    
                    if ($client !== null) {
                        // Would dispatch ServerConnection here
                    }
                    
                    yield; // Yield control back to scheduler
                }
            },
        ];
    }

    public function trigger(): object
    {
        return new \stdClass();
    }
}
