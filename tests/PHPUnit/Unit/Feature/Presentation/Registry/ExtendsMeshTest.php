<?php

declare(strict_types=1);

namespace Tests\Unit\Feature\Presentation\Registry;

use Noem\State\Feature\Presentation\PresentationRegistry;
use Noem\State\Middleware\Mesh;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criteria: PresentationRegistry extends Mesh for presentation storage
 * Intent: Leverages Mesh infrastructure enabling ChainMail integration and middleware patterns
 * Criticality: contract
 */
final class ExtendsMeshTest extends TestCase
{
    public function testPresentationRegistryExtendsMesh(): void
    {
        // This test requires JsonSchemaFeature to be available for validation
        // Create a mock schema feature context
        $schemaFeature = $this->createMock(\Noem\State\Feature\JsonSchema\JsonSchemaFeature::class);

        // We'll need to pass schema information to registry
        // For now, test the class hierarchy
        $this->assertTrue(
            is_subclass_of(PresentationRegistry::class, Mesh::class),
            'PresentationRegistry must extend Mesh'
        );
    }
}
