<?php

declare(strict_types=1);

namespace Tests\Unit\Feature\JsonSchema\BuildStep;

use Noem\State\Feature\ExtendedState\ExtendedState;
use Noem\State\Feature\JsonSchema\AddJsonSchema;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criteria: AddJsonSchema executes during build phase before region completion
 * Intent: Ensures defaults available before first state callbacks execute
 * Criticality: contract
 */
final class ExecutesDuringBuildTest extends TestCase
{
    public function testExecutesDuringBuild(): void
    {
        $schema = [
            ['name' => 'field1', 'type' => 'string', 'default' => 'initialized'],
        ];

        $builder = new RegionBuilder();
        $region = $builder
            ->enableFeatures(new ExtendedState())
            ->setStates('idle')
            ->addBuildStep(new AddJsonSchema($schema))
            ->build();

        // Value should be initialized immediately after build completes
        $metaChain = $builder->chainMail->get(\Noem\State\Chains\Meta::class);
        $metadata = $metaChain->call(new \Noem\State\Chains\Params\Meta($region, \Noem\State\Feature\ExtendedState\ContextMetaType::get()));

        $this->assertEquals(
            'initialized',
            $metadata['field1'],
            'Schema defaults must be available before first state callback executes'
        );
    }
}
