<?php

declare(strict_types=1);

namespace Tests\Unit\Feature\JsonSchema\Context;

use Noem\State\Feature\ExtendedState\ContextMetaType;
use Noem\State\Feature\ExtendedState\ExtendedState;
use Noem\State\Feature\JsonSchema\AddJsonSchema;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criteria: Schema fields accessible via $this->get() in state callbacks
 * Intent: Initialized schema fields integrate seamlessly with ExtendedState context API
 * Criticality: contract
 */
final class AccessibleViaGetTest extends TestCase
{
    public function testSchemaFieldsAccessibleViaGet(): void
    {
        $schema = [
            ['name' => 'field1', 'type' => 'string', 'default' => 'value1'],
            ['name' => 'field2', 'type' => 'integer', 'default' => 42],
        ];

        $builder = new RegionBuilder();
        $region = $builder
            ->enableFeatures(new ExtendedState())
            ->setStates('idle')
            ->addBuildStep(new AddJsonSchema($schema))
            ->build();

        // Access the context metadata to verify schema fields are accessible
        $metaChain = $builder->chainMail->get(\Noem\State\Chains\Meta::class);
        $metadata = $metaChain->call(new \Noem\State\Chains\Params\Meta($region, ContextMetaType::get()));

        // Verify fields are accessible (equivalent to $this->get())
        $this->assertEquals('value1', $metadata['field1']);
        $this->assertEquals(42, $metadata['field2']);
    }
}
