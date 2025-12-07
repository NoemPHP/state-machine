<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Loader;

use Nette\Schema\Expect;
use Nette\Schema\Processor;
use Noem\State\Feature\Loader\LoaderChains\Params\SchemaContext;
use Noem\State\Feature\Loader\LoaderChains\Schema;
use Noem\State\Feature\Loader\RegionLoader;
use Noem\State\Middleware\ChainMail;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: State schema is extended with spawn list
 */
#[Group('loader')]
#[Group('spawn-schema-extension')]
class StateSchemaSpawnExtensionTest extends TestCase
{
    public function testStateSchemaIsExtendedWithSpawnList(): void
    {
        // Create a Schema chain
        $schema = new Schema();

        // Create a RegionLoader and call the schema extension method directly
        $loader = new RegionLoader();
        $loader->extendLoaderSchemaForSpawnerSupport($schema);

        // Create initial schema context
        $callback = Expect::anyOf(Expect::string(), Expect::type(\Closure::class));
        $action = Expect::structure(['run' => $callback]);
        $state = Expect::structure(['name' => Expect::string()->required()]);
        $region = Expect::structure(['states' => Expect::listOf($state)]);

        $context = new SchemaContext($callback, $action, $state, $region);

        // Test data with spawn property
        $stateData = [
            'name' => 'test',
            'spawn' => [
                [
                    'guard' => fn() => true,
                    'region' => ['states' => [['name' => 'child']]],
                    'shared' => ['meta' => true],
                ],
            ],
        ];

        // Process through the schema chain with a provider that tests the state schema
        $result = null;
        $schema->withProvider(function (SchemaContext $context) use ($stateData, &$result) {
            $processor = new Processor();
            // This should not throw an exception if spawn was added to state schema
            $result = $processor->process($context->state, $stateData);
            return null;
        })->call($context);

        // Verify the schema accepted the spawn property
        $this->assertNotNull($result);
        $this->assertEquals('test', $result->name);
        $this->assertIsArray($result->spawn);
        $this->assertCount(1, $result->spawn);
    }
}
