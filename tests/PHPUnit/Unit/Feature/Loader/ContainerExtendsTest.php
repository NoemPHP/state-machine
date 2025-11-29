<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Loader;

use Noem\State\Feature\Loader\Container;
use Noem\State\Middleware\Mesh;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Container extends Mesh
 */
#[Group('loader')]
#[Group('container')]
class ContainerExtendsTest extends TestCase
{
    public function testContainerExtendsMesh(): void
    {
        $container = new Container();
        
        $this->assertInstanceOf(
            Mesh::class,
            $container,
            'Container should extend Mesh'
        );
        
        // Verify it inherits Mesh functionality
        $this->assertInstanceOf(\ArrayAccess::class, $container);
        $this->assertInstanceOf(\Iterator::class, $container);
    }
}
