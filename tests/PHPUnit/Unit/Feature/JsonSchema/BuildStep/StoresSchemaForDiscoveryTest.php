<?php

declare(strict_types=1);

namespace Tests\Unit\Feature\JsonSchema\BuildStep;

use Noem\State\Chains\Meta;
use Noem\State\Chains\Params\Meta as MetaParams;
use Noem\State\Feature\ExtendedState\ExtendedState;
use Noem\State\Feature\JsonSchema\AddJsonSchema;
use Noem\State\Feature\JsonSchema\JsonSchemaMetaType;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criteria: AddJsonSchema.callback() stores schema definition in JsonSchemaMetaType for discovery
 * Intent: Enables other features to discover schema definitions at runtime for schema-aware behavior
 * Criticality: contract
 */
final class StoresSchemaForDiscoveryTest extends TestCase
{
    public function testSchemaStoredInJsonSchemaMetaType(): void
    {
        $schema = [
            ['name' => 'progress', 'type' => 'integer', 'default' => 0],
            ['name' => 'status', 'type' => 'string', 'default' => 'pending'],
        ];

        $builder = new RegionBuilder();
        $builder
            ->enableFeatures(new ExtendedState())
            ->setStates('idle')
            ->addBuildStep(new AddJsonSchema($schema));

        $region = $builder->build();

        // Retrieve schema from JsonSchemaMetaType
        $meta = $builder->chainMail->get(Meta::class);
        $schemaMeta = $meta->call(new MetaParams($region, JsonSchemaMetaType::get()));

        // Verify schema is stored and accessible
        $this->assertNotNull($schemaMeta['schema']);
        $this->assertCount(2, $schemaMeta['schema']);
        $this->assertSame('progress', $schemaMeta['schema'][0]['name']);
        $this->assertSame('status', $schemaMeta['schema'][1]['name']);
    }

    public function testSchemaDiscoverableByOtherFeatures(): void
    {
        $schema = [
            ['name' => 'userId', 'type' => 'integer'],
            ['name' => 'userName', 'type' => 'string', 'default' => 'anonymous'],
        ];

        $builder = new RegionBuilder();
        $builder
            ->enableFeatures(new ExtendedState())
            ->setStates('idle')
            ->addBuildStep(new AddJsonSchema($schema));

        $region = $builder->build();

        // Simulate feature discovering schema at runtime
        $meta = $builder->chainMail->get(Meta::class);
        $schemaMeta = $meta->call(new MetaParams($region, JsonSchemaMetaType::get()));

        // Feature can iterate schema entries
        $discoveredFields = [];
        foreach ($schemaMeta['schema'] as $entry) {
            $discoveredFields[$entry['name']] = $entry['type'];
        }

        $this->assertSame(['userId' => 'integer', 'userName' => 'string'], $discoveredFields);
    }

    public function testSchemaPreservesAllProperties(): void
    {
        $schema = [
            [
                'name' => 'config',
                'type' => 'object',
                'default' => ['timeout' => 30],
                'description' => 'Configuration options',
            ],
        ];

        $builder = new RegionBuilder();
        $builder
            ->enableFeatures(new ExtendedState())
            ->setStates('idle')
            ->addBuildStep(new AddJsonSchema($schema));

        $region = $builder->build();

        $meta = $builder->chainMail->get(Meta::class);
        $schemaMeta = $meta->call(new MetaParams($region, JsonSchemaMetaType::get()));

        // All properties preserved in stored schema
        $storedEntry = $schemaMeta['schema'][0];
        $this->assertSame('config', $storedEntry['name']);
        $this->assertSame('object', $storedEntry['type']);
        $this->assertSame(['timeout' => 30], $storedEntry['default']);
        $this->assertSame('Configuration options', $storedEntry['description']);
    }
}
