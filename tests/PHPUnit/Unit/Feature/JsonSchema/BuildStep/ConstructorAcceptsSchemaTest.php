<?php

declare(strict_types=1);

namespace Tests\Unit\Feature\JsonSchema\BuildStep;

use Noem\State\Feature\JsonSchema\AddJsonSchema;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criteria: AddJsonSchema constructor accepts schema array parameter
 * Intent: Receives parsed context.schema definitions from EnhanceRegionBuilder middleware
 * Criticality: contract
 */
final class ConstructorAcceptsSchemaTest extends TestCase
{
    public function testConstructorAcceptsSchemaArray(): void
    {
        $schema = [
            ['name' => 'field1', 'type' => 'string', 'default' => 'value1'],
            ['name' => 'field2', 'type' => 'integer', 'default' => 42],
        ];

        $buildStep = new AddJsonSchema($schema);

        $this->assertInstanceOf(AddJsonSchema::class, $buildStep);
    }
}
