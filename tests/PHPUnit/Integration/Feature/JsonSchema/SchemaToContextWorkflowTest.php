<?php

declare(strict_types=1);

namespace Tests\Integration\Feature\JsonSchema;

use Noem\State\Feature\ExtendedState\ContextMetaType;
use Noem\State\Feature\ExtendedState\ExtendedState;
use Noem\State\Feature\JsonSchema\AddJsonSchema;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criteria: Complete workflow from YAML schema to initialized context
 * Intent: Validates entire schema definition, parsing, initialization, and access cycle
 * Criticality: integration
 */
final class SchemaToContextWorkflowTest extends TestCase
{
    public function testCompleteSchemaToContextWorkflow(): void
    {
        // Define schema programmatically
        $schema = [
            ['name' => 'username', 'type' => 'string', 'default' => 'guest'],
            ['name' => 'loginCount', 'type' => 'integer', 'default' => 0],
            ['name' => 'isAdmin', 'type' => 'boolean', 'default' => false],
        ];

        // Build region with schema
        $builder = new RegionBuilder();
        $region = $builder
            ->enableFeatures(new ExtendedState())
            ->setStates('idle')
            ->addBuildStep(new AddJsonSchema($schema))
            ->build();

        // Access initialized context
        $metaChain = $builder->chainMail->get(\Noem\State\Chains\Meta::class);
        $metadata = $metaChain->call(new \Noem\State\Chains\Params\Meta($region, ContextMetaType::get()));

        // Verify complete workflow: schema -> initialization -> access
        $this->assertEquals('guest', $metadata['username']);
        $this->assertEquals(0, $metadata['loginCount']);
        $this->assertEquals(false, $metadata['isAdmin']);
    }
}
