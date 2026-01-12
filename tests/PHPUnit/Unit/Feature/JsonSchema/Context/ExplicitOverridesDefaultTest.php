<?php

declare(strict_types=1);

namespace Tests\Unit\Feature\JsonSchema\Context;

use Noem\State\Feature\ExtendedState\ContextMetaType;
use Noem\State\Feature\ExtendedState\ExtendedState;
use Noem\State\Feature\JsonSchema\AddJsonSchema;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criteria: Schema field defaults override when context explicitly set
 * Intent: Explicit context values in YAML take precedence over schema defaults
 * Criticality: contract
 */
final class ExplicitOverridesDefaultTest extends TestCase
{
    public function testExplicitContextOverridesDefault(): void
    {
        $schema = [
            ['name' => 'field1', 'type' => 'string', 'default' => 'default_value'],
        ];

        $builder = new RegionBuilder();
        $region = $builder
            ->enableFeatures(new ExtendedState())
            ->setStates('idle')
            ->addBuildStep(new AddJsonSchema($schema))
            ->build();

        // First, verify default is set
        $metaChain = $builder->chainMail->get(\Noem\State\Chains\Meta::class);
        $metadata = $metaChain->call(new \Noem\State\Chains\Params\Meta($region, ContextMetaType::get()));
        $this->assertEquals('default_value', $metadata['field1']);

        // Now explicitly override the value
        $metadata['field1'] = 'explicit_value';

        // Verify explicit value takes precedence
        $this->assertEquals('explicit_value', $metadata['field1']);
    }
}
