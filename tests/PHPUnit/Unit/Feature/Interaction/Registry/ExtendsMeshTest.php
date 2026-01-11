<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Interaction\Registry;

use Noem\State\Feature\Interaction\InteractionRegistry;
use Noem\State\Middleware\Mesh;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(InteractionRegistry::class)]
class ExtendsMeshTest extends TestCase
{
    public function testExtendsMiddlewareMesh(): void
    {
        $registry = new InteractionRegistry();
        $this->assertInstanceOf(Mesh::class, $registry);
    }
}
