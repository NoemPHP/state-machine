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
 * Acceptance Criterion: Connection closes client socket when entering close state
 */
#[Group('machines'), Group('webserver'), Group('connection-lifecycle')]
class SocketCleanupTest extends NetworkMachineTestCase
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
    public function connectionClosesClientSocketWhenEnteringCloseState(): void
    {
        // Arrange
        $connection = $this->queueHttpRequest();
        $socketClosed = false;

        $region = $this->builder
            ->enableFeatures(
                new RegionLoader(),
                new ExtendedState(),
            )
            ->build([
                'loader' => [
                    'yaml' => $this->yaml(),
                    'yamlHelpers' => [
                        'get' => new \Noem\State\Feature\Loader\Helper\ContainerGetHelper([
                            'close.onEnter' => function (object $trigger) use ($connection, &$socketClosed) {
                                // Close the client socket
                                $connection->close();
                                $socketClosed = true;
                            },
                        ])
                    ]
                ]
            ]);

        // Act - Trigger to enter close state
        $region->trigger(new \stdClass());

        // Assert - Socket should be closed
        $this->assertTrue($socketClosed, 'Socket cleanup handler should execute');
        $this->assertTrue($connection->isClosed(), 'Client socket should be closed');
    }

    public function yaml(): string
    {
        return <<<YAML
states:
  - name: close
    onEnter:
      - run: !get close.onEnter
YAML;
    }

    public function container(): iterable
    {
        return [];
    }

    public function trigger(): object
    {
        return new \stdClass();
    }
}
