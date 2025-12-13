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
        $mockSocket = $this->mockSocket;
        $yieldCount = 0;

        $region = $this->builder
            ->enableFeatures(
                new \Noem\State\Feature\Loader\RegionLoader(),
                new AsyncFeature(),
            )
            ->build([
                'loader' => [
                    'yaml' => $this->yaml(),
                    'yamlHelpers' => [
                        'get' => new \Noem\State\Feature\Loader\Helper\ContainerGetHelper([
                            'server.starting.accept' => function (object $trigger) use ($mockSocket, &$yieldCount) {
                                // Simulate the accept loop that yields
                                $yieldCount++; // Count entry
                                yield; // Initial yield

                                for ($i = 0; $i < 10; $i++) {
                                    $yieldCount++; // Count each iteration
                                    // Simulate checking for connections
                                    $client = $mockSocket->accept();

                                    if ($client !== null) {
                                        // Would dispatch ServerConnection here
                                    }

                                    yield; // Yield control back to scheduler
                                }
                            },
                        ])
                    ]
                ]
            ]);

        // Act & Assert - Trigger server multiple times
        $region->trigger(new \stdClass()); // Start the generator
        $this->assertEquals(1, $yieldCount, 'First trigger should execute up to first yield');

        $region->trigger(new \stdClass()); // Resume generator
        $this->assertEquals(2, $yieldCount, 'Second trigger should execute one loop iteration');

        $region->trigger(new \stdClass()); // Resume again
        $this->assertEquals(3, $yieldCount, 'Third trigger should execute another iteration');

        // Continue for several more iterations to prove sustained yielding
        $this->tickN($region, 3);
        $this->assertEquals(6, $yieldCount, 'Continued triggers should keep advancing the generator');

        // The generator should still have more iterations left (10 total)
        // This proves it's yielding cooperatively rather than blocking
        $this->assertLessThan(11, $yieldCount, 'Generator should not have completed all iterations yet');
    }

    public function yaml(): string
    {
        return <<<YAML
states:
  - name: starting
    action:
      - run: !get server.starting.accept
        async:
          enabled: true
          priority: low
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
