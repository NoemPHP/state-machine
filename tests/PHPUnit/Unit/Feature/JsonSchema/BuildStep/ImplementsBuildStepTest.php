<?php

declare(strict_types=1);

namespace Tests\Unit\Feature\JsonSchema\BuildStep;

use Noem\State\BuildStep;
use Noem\State\Feature\JsonSchema\AddJsonSchema;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criteria: AddJsonSchema implements BuildStep interface
 * Intent: Integrates with RegionBuilder build pipeline for schema processing
 * Criticality: contract
 */
final class ImplementsBuildStepTest extends TestCase
{
    public function testAddJsonSchemaImplementsBuildStep(): void
    {
        $schema = [
            ['name' => 'field1', 'type' => 'string', 'default' => 'test'],
        ];

        $buildStep = new AddJsonSchema($schema);

        $this->assertInstanceOf(BuildStep::class, $buildStep);
    }
}
