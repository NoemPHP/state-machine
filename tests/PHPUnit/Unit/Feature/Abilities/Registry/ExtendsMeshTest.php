<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Abilities\Registry;

use Noem\State\Feature\Abilities\AbilityRegistry;
use Noem\State\Middleware\Mesh;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: AbilityRegistry extends Mesh for ability storage
 *
 * Intent: Leverages Mesh infrastructure for ability definitions,
 * enabling ChainMail integration and middleware patterns
 *
 * Criticality: contract
 */
#[Group('abilities')]
#[Group('ability-registry')]
class ExtendsMeshTest extends TestCase
{
    public function testAbilityRegistryExtendsMesh(): void
    {
        $registry = new AbilityRegistry();

        // Verify AbilityRegistry is an instance of Mesh
        $this->assertInstanceOf(Mesh::class, $registry);

        // Verify it implements ArrayAccess (inherited from Mesh)
        $this->assertInstanceOf(\ArrayAccess::class, $registry);

        // Verify it implements Iterator (inherited from Mesh)
        $this->assertInstanceOf(\Iterator::class, $registry);
    }
}
