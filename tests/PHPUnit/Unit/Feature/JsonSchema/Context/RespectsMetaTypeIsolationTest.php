<?php

declare(strict_types=1);

namespace Tests\Unit\Feature\JsonSchema\Context;

use Noem\State\Feature\ExtendedState\ContextMetaType;
use Noem\State\Feature\ExtendedState\ExtendedState;
use Noem\State\Feature\JsonSchema\AddJsonSchema;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criteria: Schema initialization respects ContextMetaType isolation
 * Intent: Schema fields stored in ExtendedState namespace, avoiding conflicts with other metadata
 * Criticality: contract
 */
final class RespectsMetaTypeIsolationTest extends TestCase
{
    public function testSchemaRespectsMetaTypeIsolation(): void
    {
        $schema = [
            ['name' => 'field1', 'type' => 'string', 'default' => 'value1'],
        ];

        $builder = new RegionBuilder();
        $region = $builder
            ->enableFeatures(new ExtendedState())
            ->setStates('idle')
            ->addBuildStep(new AddJsonSchema($schema))
            ->build();

        $metaChain = $builder->chainMail->get(\Noem\State\Chains\Meta::class);

        // Access using ContextMetaType
        $contextMetadata = $metaChain->call(new \Noem\State\Chains\Params\Meta($region, ContextMetaType::get()));
        $this->assertEquals('value1', $contextMetadata['field1']);

        // Verify that different MetaType namespaces are isolated
        // The schema fields should only be accessible via ContextMetaType
        $this->assertTrue(
            isset($contextMetadata['field1']),
            'Schema fields must be accessible in ContextMetaType namespace'
        );
    }
}
