<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Middleware\Mesh;

use Noem\State\Middleware\Mesh;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Mesh implements ArrayAccess interface for array-like access
 */
#[Group('middleware')]
#[Group('mesh')]
class ArrayAccessTest extends TestCase
{
    public function testImplementsArrayAccessInterface(): void
    {
        $mesh = new Mesh();

        $this->assertInstanceOf(\ArrayAccess::class, $mesh);
    }

    public function testArrayStyleSetAndGet(): void
    {
        $mesh = new Mesh();

        $mesh['foo'] = 'bar';
        $this->assertSame('bar', $mesh['foo']);

        $mesh['key'] = 'value';
        $this->assertSame('value', $mesh['key']);
    }

    public function testArrayStyleWithDifferentTypes(): void
    {
        $mesh = new Mesh();

        $mesh['string'] = 'text';
        $mesh['number'] = 42;
        $mesh['array'] = ['nested' => 'data'];
        $mesh['object'] = (object)['prop' => 'value'];

        $this->assertSame('text', $mesh['string']);
        $this->assertSame(42, $mesh['number']);
        $this->assertSame(['nested' => 'data'], $mesh['array']);
        $this->assertEquals((object)['prop' => 'value'], $mesh['object']);
    }

    public function testArrayStyleUpdate(): void
    {
        $mesh = new Mesh();

        $mesh['key'] = 'original';
        $this->assertSame('original', $mesh['key']);

        $mesh['key'] = 'updated';
        $this->assertSame('updated', $mesh['key']);
    }

    public function testArrayAccessWithNumericKeys(): void
    {
        $mesh = new Mesh();

        $mesh[0] = 'first';
        $mesh[1] = 'second';
        $mesh[10] = 'tenth';

        $this->assertSame('first', $mesh[0]);
        $this->assertSame('second', $mesh[1]);
        $this->assertSame('tenth', $mesh[10]);
    }
}
