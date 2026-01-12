<?php

declare(strict_types=1);

namespace Tests\Unit\Feature\JsonSchema\BuildStep;

use Noem\State\Feature\ExtendedState\ContextMetaType;
use Noem\State\Feature\ExtendedState\ExtendedState;
use Noem\State\Feature\JsonSchema\AddJsonSchema;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criteria: AddJsonSchema.callback() initializes each field with default value
 * Intent: Sets initial context values from schema definitions, establishing predictable runtime state
 * Criticality: contract
 */
final class InitializesDefaultValuesTest extends TestCase
{
    public function testInitializesDefaultValues(): void
    {
        $schema = [
            ['name' => 'stringField', 'type' => 'string', 'default' => 'hello'],
            ['name' => 'intField', 'type' => 'integer', 'default' => 42],
            ['name' => 'boolField', 'type' => 'boolean', 'default' => true],
        ];

        $builder = new RegionBuilder();
        $region = $builder
            ->enableFeatures(new ExtendedState())
            ->setStates('idle')
            ->addBuildStep(new AddJsonSchema($schema))
            ->build();

        // Access the context metadata directly to verify initialization
        $metaChain = $builder->chainMail->get(\Noem\State\Chains\Meta::class);
        $metadata = $metaChain->call(new \Noem\State\Chains\Params\Meta($region, ContextMetaType::get()));

        $this->assertEquals('hello', $metadata['stringField']);
        $this->assertEquals(42, $metadata['intField']);
        $this->assertEquals(true, $metadata['boolField']);
    }
}
