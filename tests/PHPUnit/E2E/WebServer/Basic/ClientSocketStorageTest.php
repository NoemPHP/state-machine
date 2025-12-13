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
use Noem\State\Test\E2E\Support\MockConnection;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * Acceptance Criterion: Connection stores client socket in extended state during accept
 */
#[Group('machines'), Group('webserver'), Group('connection-lifecycle')]
class ClientSocketStorageTest extends NetworkMachineTestCase
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
    public function connectionStoresClientSocketInExtendedState(): void
    {
        // Arrange
        $mockClient = $this->queueHttpRequest();
        $storedClient = null;

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
                            'accept.action' => function(object $trigger) use ($mockClient, &$storedClient) {
                                // Store client socket in extended state
                                $this->set('client', $mockClient);
                                $storedClient = $this->get('client');
                            },
                        ])
                    ]
                ]
            ]);

        // Act - Trigger to enter accept state and execute action
        $region->trigger(new \stdClass());

        // Assert - Client socket should be stored
        $this->assertSame(
            $mockClient,
            $storedClient,
            'Client socket should be stored in extended state during accept'
        );
    }

    public function yaml(): string
    {
        return <<<YAML
states:
  - name: accept
    action:
      - run: !get accept.action
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
