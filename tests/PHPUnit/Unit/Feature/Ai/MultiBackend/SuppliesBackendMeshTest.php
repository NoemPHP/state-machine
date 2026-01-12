<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Feature\Ai\MultiBackend;

use Noem\State\Feature\Ai\AiFeature;
use Noem\State\Middleware\ChainMail;
use Noem\State\Middleware\Mesh;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: AiFeature supplies Mesh containing backend instances via ChainMail
 *
 * Intent: Provides backend mesh through ChainMail dependency injection, enabling features
 * to access multiple AI backends through array-like interface
 */
#[Group('ai'), Group('backend-mesh-infrastructure')]
class SuppliesBackendMeshTest extends TestCase
{
    #[Test]
    public function suppliesBackendMeshViaChainMail(): void
    {
        $chainMail = new ChainMail();

        $feature = new AiFeature();
        $feature($chainMail);

        $backends = $chainMail->get(Mesh::class);
        $this->assertInstanceOf(Mesh::class, $backends);
    }
}
