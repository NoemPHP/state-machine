<?php

declare(strict_types=1);

namespace Tests\Unit\Feature\JsonSchema;

use Noem\State\Feature\Feature;
use Noem\State\Feature\JsonSchema\JsonSchemaFeature;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criteria: JsonSchemaFeature implements Feature interface
 * Intent: Ensures feature integrates correctly with ChainMail feature loading system
 * Criticality: contract
 */
final class ImplementsFeatureTest extends TestCase
{
    public function testJsonSchemaFeatureImplementsFeature(): void
    {
        $feature = new JsonSchemaFeature();

        $this->assertInstanceOf(Feature::class, $feature);
    }
}
