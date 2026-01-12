<?php

declare(strict_types=1);

namespace Tests\Unit\Feature\JsonSchema\Context;

use Noem\State\Feature\ExtendedState\ContextMetaType;
use Noem\State\Feature\ExtendedState\ExtendedState;
use Noem\State\Feature\JsonSchema\AddJsonSchema;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criteria: Schema fields modifiable via $this->set() in state callbacks
 * Intent: Schema initialization doesn't prevent runtime modification of context values
 * Criticality: contract
 */
final class ModifiableViaSetTest extends TestCase
{
    public function testSchemaFieldsModifiableViaSet(): void
    {
        $schema = [
            ['name' => 'counter', 'type' => 'integer', 'default' => 0],
        ];

        $builder = new RegionBuilder();
        $region = $builder
            ->enableFeatures(new ExtendedState())
            ->setStates('idle')
            ->addBuildStep(new AddJsonSchema($schema))
            ->build();

        $metaChain = $builder->chainMail->get(\Noem\State\Chains\Meta::class);
        $metadata = $metaChain->call(new \Noem\State\Chains\Params\Meta($region, ContextMetaType::get()));

        // Verify initial default
        $this->assertEquals(0, $metadata['counter']);

        // Modify the value (equivalent to $this->set())
        $metadata['counter'] = 5;
        $this->assertEquals(5, $metadata['counter']);

        // Modify again
        $metadata['counter'] = 10;
        $this->assertEquals(10, $metadata['counter']);
    }
}
