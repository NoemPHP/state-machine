<?php

declare(strict_types=1);

namespace Tests\Unit\Feature\JsonSchema\BuildStep;

use Noem\State\Feature\ExtendedState\ExtendedState;
use Noem\State\Feature\JsonSchema\AddJsonSchema;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criteria: AddJsonSchema.callback() uses null as default when not specified
 * Intent: Provides consistent default behavior when schema omits default property
 * Criticality: contract
 */
final class DefaultsToNullTest extends TestCase
{
    public function testDefaultsToNull(): void
    {
        $schema = [
            ['name' => 'fieldWithoutDefault', 'type' => 'string'],
        ];

        $builder = new RegionBuilder();
        $region = $builder
            ->enableFeatures(new ExtendedState())
            ->setStates('idle')
            ->addBuildStep(new AddJsonSchema($schema))
            ->build();

        // Access the context metadata directly to verify null default
        $metaChain = $builder->chainMail->get(\Noem\State\Chains\Meta::class);
        $metadata = $metaChain->call(new \Noem\State\Chains\Params\Meta($region, \Noem\State\Feature\ExtendedState\ContextMetaType::get()));

        $this->assertNull($metadata['fieldWithoutDefault']);
    }
}
